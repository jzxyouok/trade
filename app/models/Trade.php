<?php

namespace MyApp\Models;

use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;
use Xxtime\Util;

class Trade extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbData');
        $this->setSource("transactions");
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
            "transaction" => $tradeData['transaction'],
            "app_id"      => $tradeData['app_id'],
            "user_id"     => $tradeData['user_id'],
            "amount"      => $data['price'],
            "currency"    => $tradeData['currency'] ? $tradeData['currency'] : 'CNY',
            "gateway"     => strtolower($tradeData['gateway']),
            "product_id"  => $tradeData['product_id'],
            "end_user"    => $tradeData['end_user'],
            "ip"          => $tradeData['ip'],
            "uuid"        => strtoupper($tradeData['uuid']),
            "adid"        => strtoupper($tradeData['adid']),
            "device"      => $tradeData['device'],
            "channel"     => $tradeData['channel'],
            "create_time" => date('Y-m-d H:i:s')    // 不使用SQL自动插入时间，避免时区不统一
        );
        $result = DI::getDefault()->get('dbData')->insertAsDict("transactions", $trade);
        if (!$result) {
            return false;
        }
        return $trade;
    }


    /**
     * 发货通知 TODO :: paid状态处理
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
            'end_user'    => $tradeInfo['end_user'],
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


    /** 获取产品
     * @param int $app_id
     * @param string $gateway
     * @return mixed
     */
    public function getProducts($app_id = 0, $gateway = '')
    {
        $sql = "SELECT id,name,product_id,price,currency,coin,remark,image FROM `products` WHERE status=1 AND app_id=:app_id ";
        $bind = array('app_id' => $app_id);
        if ($gateway) {
            $sql .= "AND gateway=:gateway";
            $bind['gateway'] = $gateway;
        }
        $sql .= "ORDER BY sort DESC, price";
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetchAll();
    }

}
