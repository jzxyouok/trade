<?php

namespace MyApp\Models;


use Firebase\JWT\JWT;
use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;
use Xxtime\Util;
use Exception;

class Trade extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbData');
        $this->setSource("transactions");
    }


    /**
     * 验证access_token
     * @param string $jwt
     * @return array|bool
     */
    public function verifyAccessToken($jwt = '')
    {
        $key = DI::getDefault()->get('config')->setting->cryptKey;
        try {
            JWT::$leeway = 300; // 允许误差秒数
            $decoded = JWT::decode($jwt, $key, array('HS256'));
            return [
                'open_id' => $decoded->open_id,
                'name'    => $decoded->name,
                'gender'  => $decoded->gender,
                'photo'   => $decoded->photo,
            ];
        } catch (Exception $e) {
            return false;
        }
    }


    /**
     * 获取订单信息
     * @param string $transaction
     * @return mixed
     */
    public function getTrade($transaction = '')
    {
        $sql = "SELECT * FROM `transactions` WHERE transaction=:transaction";
        $bind = array('transaction' => $transaction);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetch();
    }


    /**
     * 获取订单信息
     * @param string $gateway
     * @param string $Reference
     * @return mixed
     */
    public function getTradeByReference($gateway = '', $Reference = '')
    {
        $sql = "SELECT * FROM `transactions` WHERE gateway=:gateway AND trade_no=:trade_no";
        $bind = array('gateway' => $gateway, 'trade_no' => $Reference);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetch();
    }


    /**
     * 更新订单状态
     * @param string $transaction
     * @param string $status
     * @return bool
     */
    public function updateTradeStatus($transaction = '', $status = '')
    {
        if (!$transaction || !$status) {
            return false;
        }
        $sql = "UPDATE `transactions` SET status=:status WHERE transaction=:transaction";
        $bind = array('status' => $status, 'transaction' => $transaction);
        return DI::getDefault()->get('dbData')->execute($sql, $bind);
    }


    /**
     * 创建订单号
     * @param int $code
     * @return bool|string
     */
    public function createTransaction($code = 0)
    {
        $config = DI::getDefault()->get('config');
        $redis = new \Redis();
        $redis->connect($config->redis->host, $config->redis->port);
        $redis->select(1);
        $sequence = $redis->incr('sequence');

        $main = date('YmdHi');
        if ($code) {
            $main .= str_pad(substr($code, -2), 2, '0', STR_PAD_LEFT);
        }
        $sequence = str_pad($sequence, 6, '0', STR_PAD_LEFT);
        $rand = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $main .= substr($sequence, -3, 3) . substr($rand, 0, 3) . substr($sequence, -6, 3) . substr($rand, 3, 3);
        return $main;
    }


    /**
     * 创建订单
     * @param array $tradeData
     * @return bool
     */
    public function createTrade($tradeData = [])
    {
        // 检查产品 TODO:: 卡类支付暂不适用
        if (isset($tradeData['product_id'])) {
            $sql = "SELECT id, price, currency FROM `products` WHERE status=1 AND product_id=:product_id";
            $bind = array('product_id' => $tradeData['product_id']);
            $query = DI::getDefault()->get('dbData')->query($sql, $bind);
            $query->setFetchMode(Db::FETCH_ASSOC);
            $data = $query->fetch();

            if (!$data) {
                $msg = "Invalid Product Config: {$tradeData['product_id']}";
                writeLog("APP:{$tradeData['app_id']}, {$msg}", 'error' . date('Ym'));
                return false;
            }
        } elseif (isset($tradeData['amount'])) {
            // TODO :: 额度支付
            $data['price'] = $tradeData['amount'];
            return false;
        }


        // 创建订单
        $trade = array(
            "transaction" => isset($tradeData['transaction']) ? $tradeData['transaction'] : $this->createTransaction($tradeData['user_id']),
            "app_id"      => $tradeData['app_id'],
            "user_id"     => $tradeData['user_id'],
            "amount"      => $data['price'],
            "currency"    => $data['currency'] ? $data['currency'] : 'CNY',
            "gateway"     => strtolower($tradeData['gateway']),
            "product_id"  => $tradeData['product_id'],
            "custom"      => $tradeData['custom'],
            "status"      => isset($tradeData['status']) ? $tradeData['status'] : null,
            "trade_no"    => isset($tradeData['trade_no']) ? $tradeData['trade_no'] : null,
            "ip"          => $tradeData['ip'],
            "uuid"        => strtoupper($tradeData['uuid']),
            "adid"        => strtoupper($tradeData['adid']),
            "device"      => $tradeData['device'],
            "channel"     => $tradeData['channel'],
            "create_time" => date('Y-m-d H:i:s')    // 不使用SQL自动插入时间，避免时区不统一
        );
        $result = DI::getDefault()->get('dbData')->insertAsDict("transactions", array_filter($trade));
        if (!$result) {
            return false;
        }
        return $trade;
    }


    /**
     * 发货通知; 只能是pending或paid状态
     * @param array $tradeInfo
     * @param string $transactionReference
     * @return bool
     */
    public function noticeTo($tradeInfo = [], $transactionReference = null)
    {
        // 付款状态
        if ($tradeInfo['status'] == 'pending') {
            $sql = "UPDATE transactions SET status='paid', trade_no=:transactionReference WHERE transaction=:transaction";
            $bind = array(
                'transaction'          => $tradeInfo['transaction'],
                'transactionReference' => $transactionReference
            );
            DI::getDefault()->get('dbData')->execute($sql, $bind);
        }


        // 准备通知
        $appConfig = $this->getAppConfig($tradeInfo['app_id']);

        $data = array(
            'transaction' => $tradeInfo['transaction'],
            'gateway'     => $tradeInfo['gateway'],
            'amount'      => $tradeInfo['amount'],
            'currency'    => $tradeInfo['currency'],
            'product_id'  => $tradeInfo['product_id'],
            'user_id'     => $tradeInfo['user_id'],
            'custom'      => $tradeInfo['custom'],
            'timestamp'   => time(),
        );
        $data['sign'] = Util::createSign($data, $appConfig['secret_key']);


        // 通知
        for ($i = 0; $i < 3; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $appConfig['notify_url']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'XXTIME.COM');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['POWER:XXTIME.COM']);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response !== false) {
                break;
            }
        }

        // 日志
        DI::getDefault()->get('dbData')->insertAsDict(
            "notify_logs",
            array(
                "transaction" => $tradeInfo['transaction'],
                "notify_url"  => $appConfig['notify_url'],
                "request"     => http_build_query($data),
                "response"    => $response,
                "create_time" => date('Y-m-d H:i:s')
            )
        );

        // 完成
        if (strtolower($response) == 'success') {
            $dateTime = date('Y-m-d H:i:s');
            $sql = "UPDATE transactions SET status='complete', complete_time=:dateTime WHERE transaction=:transaction";
            $bind = array(
                'transaction' => $tradeInfo['transaction'],
                'dateTime'    => $dateTime
            );
            return DI::getDefault()->get('dbData')->execute($sql, $bind);
        }
        return false;
    }


    /**
     * 应用配置
     * @param int $app_id
     * @return appData
     */
    public function getAppConfig($app_id = 0)
    {
        $sql = "SELECT app_id,secret_key,notify_url,trade_method,trade_tip FROM `apps` WHERE app_id=:app_id";
        $bind = array('app_id' => $app_id);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        if (!$data) {
            writeLog('no app config:' . $app_id);
        }
        return $data;
    }


    /**
     * 转换美金
     * @param int $amount
     * @param string $currency
     * @return int
     */
    public function changeToUSD($amount = 0, $currency = '')
    {
        if ($currency == 'USD') {
            return $amount;
        }

        global $config;
        $k = $currency . 'USD';
        if (!isset($config->exchange->$k)) {
            writeLog("No Exchange Config: {$currency}", 'error' . date('Ym'));
            return 0;
        }
        return $amount * $config->exchange->$k;
    }


    /**
     * 获取产品
     * @param int $app_id
     * @param string $gateway
     * @return mixed
     */
    public function getProducts($app_id = 0, $gateway = '')
    {
        $sql = "SELECT id,name,product_id,price,currency,coin,remark,image FROM `products` WHERE status=1 AND app_id=:app_id";
        $bind = array('app_id' => $app_id);
        if ($gateway) {
            $sql .= " AND gateway=:gateway";
            $bind['gateway'] = $gateway;
        }
        $sql .= " ORDER BY sort DESC, price";
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $result = $query->fetchAll();

        // 去掉渠道查询
        if (!$result) {
            $sql = "SELECT id,name,product_id,price,currency,coin,remark,image FROM `products` WHERE status=1 AND app_id=:app_id AND gateway='' ORDER BY sort DESC, price";
            $bind = array('app_id' => $app_id);
            $query = DI::getDefault()->get('dbData')->query($sql, $bind);
            $query->setFetchMode(Db::FETCH_ASSOC);
            $result = $query->fetchAll();
        }

        // 需要php支持国际化与字符编码 --enable-intl
        // http://php.net/manual/zh/book.intl.php
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter("zh-CN", \NumberFormatter::CURRENCY);
            foreach ($result as $key => $value) {
                $result[$key]['price_format'] = $formatter->formatCurrency($value['price'], $value['currency']);
            }
        }

        return $result;
    }


    /**
     * 获取产品
     * @param string $product_id
     * @return mixed
     */
    public function getProductById($product_id = '')
    {
        $sql = "SELECT id,name,product_id,price,currency,coin,remark,image FROM `products` WHERE status=1 AND product_id=:product_id ";
        $bind = array('product_id' => $product_id);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetch();
    }


    /**
     * 获取网关
     * @param int $app_id
     * @return array|bool
     */
    public function getGateways($app_id = 0)
    {
        $sql = "SELECT trade_method FROM `apps` WHERE app_id=:app_id ";
        $bind = array('app_id' => $app_id);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        if (!$data) {
            return false;
        }
        $ways = [
            'alipay' => [
                'title'  => '支付宝',
                'remark' => '推荐有支付宝账号的用户使用',
            ],
            'weixin' => [
                'title'  => '微信支付',
                'remark' => '',
            ],
            'paypal' => [
                'title'  => 'PayPal',
                'remark' => '',
            ],
        ];
        return array_intersect_key($ways, array_flip(explode(',', $data['trade_method'])));
    }


    /**
     * 获取配置文件绝对路径
     * @param array $config
     * @return array
     */
    public function getFullPath($config = [])
    {
        $keyWord = ['privateKey', 'publicKey'];
        foreach ($keyWord as $word) {
            if (array_key_exists($word, $config) && strpos($config[$word], '/') !== 0) {
                $config[$word] = BASE_DIR . '/' . $config[$word];
            }
        }
        return $config;
    }

}
