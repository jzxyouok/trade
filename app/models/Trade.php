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
        $key = DI::getDefault()->get('config')->setting->secret_key;
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
        $sql = "SELECT id,transaction,app_id,user_id,currency,amount,amount_usd,status,gateway,product_id,custom,ip
FROM `transactions` WHERE transaction=:transaction LIMIT 1";
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
        $sql = "SELECT tx.id,tx.transaction,tx.app_id,tx.user_id,tx.currency,tx.amount,tx.amount_usd,tx.status,tx.gateway,tx.product_id,tx.custom,`more`.trade_no,`more`.key_string
FROM `transactions` tx
RIGHT JOIN `trans_more` `more`
ON tx.transaction=`more`.trans_id
WHERE `more`.trade_no=:trade_no AND `more`.gateway=:gateway
LIMIT 1";
        $bind = array('gateway' => $gateway, 'trade_no' => $Reference);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetch();
    }


    /**
     * 更新网关订单
     * @param string $transaction
     * @param array $data
     * @return mixed
     */
    public function setTradeReference($transaction = '', $data = [])
    {
        return DI::getDefault()->get('dbData')->updateAsDict(
            "trans_more",
            $data,
            array(
                'conditions' => 'trans_id = ?',
                'bind'       => array($transaction),
                'bindTypes'  => array(\PDO::PARAM_STR)
            )
        );
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
     * 更新订单内容【充值回调】
     * TODO :: 目前精确匹配, 非模糊匹配
     * @param string $app_id
     * @param $transaction
     * @param array $data
     * @return array|bool
     */
    public function updateTradeAmount($app_id = '', $transaction, $data = [])
    {
        $product = $this->getProductByAmount($app_id, $data['gateway'], $data['amount'], $data['currency']);
        if (!$product) {
            writeLog("getProductByAmount can`t find product", 'error' . date('Ym'));
            return false;
        }

        // 更新
        $modify = [
            "amount"     => sprintf('%.2f', $data['amount']),
            "currency"   => $data['currency'],
            "product_id" => $product['product_id']
        ];
        $result = DI::getDefault()->get('dbData')->updateAsDict(
            "transactions",
            $modify,
            array(
                'conditions' => 'transaction = ?',
                'bind'       => array($transaction),
                'bindTypes'  => array(\PDO::PARAM_INT)
            )
        );
        if (!$result) {
            return false;
        }
        return $modify;
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
     * @param array $more
     * @return array|bool
     * @throws Exception
     */
    public function createTrade($tradeData = [], $more = [])
    {
        if (empty($tradeData['app_id']) || empty($tradeData['gateway']) || empty($tradeData['user_id'])) {
            throw new Exception('missing parameter');
        }

        // 检查产品, 根据product_id
        if (isset($tradeData['product_id'])) {
            $sql = "SELECT id, price, currency FROM `products` WHERE status=1 AND product_id=:product_id";
            $bind = array('product_id' => $tradeData['product_id']);
            $query = DI::getDefault()->get('dbData')->query($sql, $bind);
            $query->setFetchMode(Db::FETCH_ASSOC);
            $product = $query->fetch();

            if (!$product) {
                $msg = "invalid product: {$tradeData['product_id']}";
                writeLog("APP:{$tradeData['app_id']}, {$msg}", 'error' . date('Ym'));
                throw new Exception('invalid product');
            }
        } /**
         *
         * TODO :: 额度支付
         */
        elseif (isset($tradeData['amount'])) {
            $product['price'] = $tradeData['amount'];
            throw new Exception('not support');
        }


        // 创建订单【卡类支付创建空订单】
        $trade = array(
            "transaction" => $this->createTransaction($tradeData['user_id']),
            "app_id"      => $tradeData['app_id'],
            "user_id"     => $tradeData['user_id'],
            "amount"      => isset($product['price']) ? $product['price'] : 0,
            "currency"    => isset($product['currency']) ? $product['currency'] : 'USD',
            "gateway"     => strtolower($tradeData['gateway']),
            "product_id"  => $tradeData['product_id'],
            "custom"      => $tradeData['custom'],
            "status"      => isset($tradeData['status']) ? $tradeData['status'] : null,
            "ip"          => $tradeData['ip'],
            "uuid"        => strtolower($tradeData['uuid']),
            "adid"        => strtolower($tradeData['adid']),
            "device"      => $tradeData['device'],
            "channel"     => $tradeData['channel'],
            "create_time" => date('Y-m-d H:i:s')    // 不使用SQL自动插入时间，避免时区不统一
        );
        $more['trans_id'] = $trade['transaction'];
        $more['gateway'] = $trade['gateway'];
        try {
            DI::getDefault()->get('dbData')->begin();
            DI::getDefault()->get('dbData')->insertAsDict("transactions", array_filter($trade));
            DI::getDefault()->get('dbData')->insertAsDict("trans_more", array_filter($more));
            DI::getDefault()->get('dbData')->commit();
        } catch (Exception $e) {
            DI::getDefault()->get('dbData')->rollback();
            throw new Exception('failed');
        }
        return $trade;
    }


    /**
     * 发货通知; 只能是pending或paid状态
     * @param array $tradeInfo
     * @param null $transactionReference
     * @param array $raw
     * @return bool
     */
    public function noticeTo($tradeInfo = [], $transactionReference = null, $raw = [])
    {
        // 付款状态
        if ($tradeInfo['status'] == 'pending') {

            $amount_usd = $this->changeToUSD($tradeInfo['amount'], $tradeInfo['currency']);

            try {
                if (!$raw) {
                    $raw = '';
                }
                if (is_array($raw)) {
                    $raw = http_build_query($raw);
                }

                DI::getDefault()->get('dbData')->begin();

                $sql = "UPDATE transactions SET status='paid', amount_usd=:amount_usd WHERE transaction=:transaction";
                $bind = array('amount_usd' => $amount_usd, 'transaction' => $tradeInfo['transaction']);
                DI::getDefault()->get('dbData')->execute($sql, $bind);

                if ($transactionReference) {
                    $sql = "UPDATE trans_more SET trade_no=:reference, data=:data WHERE trans_id=:transaction";
                    $bind = array(
                        'reference'   => $transactionReference,
                        'data'        => $raw,
                        'transaction' => $tradeInfo['transaction']
                    );
                } else { // there is no transactionReference when notify; exp: MyCard
                    $sql = "UPDATE trans_more SET data=:data WHERE trans_id=:transaction";
                    $bind = array('data' => $raw, 'transaction' => $tradeInfo['transaction']);
                }

                DI::getDefault()->get('dbData')->execute($sql, $bind);

                DI::getDefault()->get('dbData')->commit();
            } catch (Exception $e) {
                writeLog('transaction update failed: ' . $tradeInfo['transaction'] . '|' . $transactionReference);
                DI::getDefault()->get('dbData')->rollback();
                return false;
            }
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
        $sql = "SELECT app_id,secret_key,notify_url,trade_tip FROM `apps` WHERE app_id=:app_id";
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
        $k = $currency . 'USD';
        if (!isset(DI::getDefault()->get('config')->exchange->$k)) {
            writeLog("no exchange config: {$currency}", 'error' . date('Ym'));
            return 0;
        }
        return $amount * DI::getDefault()->get('config')->exchange->$k;
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
     * 决策产品 按额度决策
     * 精确匹配
     * @param string $app_id
     * @param string $gateway
     * @param int $amount
     * @param string $currency
     * @return mixed
     */
    public function getProductByAmount($app_id = '', $gateway = '', $amount = 0, $currency = 'USD')
    {
        $sql = "SELECT id,`name`,product_id,price,currency,coin,remark,image FROM `products`
WHERE status=1 AND app_id=:app_id AND gateway=:gateway AND price=:amount AND currency=:currency";
        $bind = array('app_id' => $app_id, 'gateway' => $gateway, 'amount' => $amount, 'currency' => $currency);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetch();
    }


    /**
     * 获取网关
     * @param int $app_id
     * @param null $gateway
     * @return bool
     */
    public function getGateways($app_id = 0, $gateway = null)
    {
        if (!$gateway) {
            $sql = "SELECT id,parent,`name`,remark,gateway,sub,currency,tips FROM `gateways` WHERE app_id=:app_id AND parent = 0 ORDER BY sort DESC";
            $bind = array('app_id' => $app_id);
        } else {
            $sql = "SELECT id,parent,`name`,remark,gateway,sub,currency,tips FROM `gateways` WHERE app_id=:app_id AND parent !=0 AND gateway=:gateway ORDER BY sort DESC";
            $bind = array('app_id' => $app_id, 'gateway' => $gateway);
        }
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetchAll();
        if (!$data) {
            return false;
        }
        return $data;
    }


    /**
     * 获取终端网关信息
     * @param int $app_id
     * @param null $gateway
     * @param null $sub
     * @return bool
     */
    public function getFinalGateway($app_id = 0, $gateway = null, $sub = null)
    {
        if (!$app_id || !$gateway) {
            return false;
        }
        if ($sub) {
            $sql = "SELECT id,`type`,`sandbox`,`name`,remark,gateway,sub,currency FROM `gateways` WHERE app_id=:app_id AND gateway=:gateway AND sub=:sub LIMIT 1";
            $bind = array('app_id' => $app_id, 'gateway' => $gateway, 'sub' => $sub);
        } else {
            $sql = "SELECT id,`type`,`sandbox`,`name`,remark,gateway,sub,currency FROM `gateways` WHERE app_id=:app_id AND gateway=:gateway AND parent = 0 LIMIT 1";
            $bind = array('app_id' => $app_id, 'gateway' => $gateway);
        }
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        return $data;
    }


    /**
     * 获取贴士信息
     * @param int $app_id
     * @param string $gateway
     * @return string
     */
    public function getTips($app_id = 0, $gateway = '')
    {
        $sql = "SELECT `name`,remark,tips FROM `gateways` WHERE app_id=:app_id AND gateway=:gateway AND parent = 0 AND sub='' LIMIT 1";
        $bind = array('app_id' => $app_id, 'gateway' => $gateway);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        if (!$data) {
            return '';
        }
        return $data['tips'];
    }


    /**
     * 获取配置文件绝对路径
     * @param array $config
     * @return array
     */
    public function getFullPath($config = [])
    {
        $keyWord = ['private_key', 'public_key'];
        foreach ($keyWord as $word) {
            if (array_key_exists($word, $config) && strpos($config[$word], '/') !== 0) {
                $config[$word] = BASE_DIR . '/' . $config[$word];
            }
        }
        return $config;
    }

}
