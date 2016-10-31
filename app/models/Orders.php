<?php

namespace MyApp\Models;

use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;
use Xxtime\Util;

class Orders extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbData');
        $this->setSource("transaction");
    }


    /**
     * 创建订单
     * @param array $order
     */
    public function makeOrder($order = [])
    {
        // 检查产品 TODO:: 卡类支付暂不适用
        $sql = "SELECT price, currency FROM `products` WHERE app_id=:app_id AND product_id=:product_id AND gateway=:gateway";
        $bind = array('app_id' => $order['app_id'], 'product_id' => $order['product_id'], 'gateway' => $order['gateway']);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        if (!$data || ($data['price'] != $order['amount'])) {
            $msg = "Invalid Product Config: {$order['product_id']}";
            writeLog("APP:{$order['app_id']}, {$msg}", 'ERROR' . date('Ym'));
            Utils::outputJSON(array('code' => 1, 'msg' => $msg));
        }


        // 下单
        $this->transaction = $order['transaction'];
        $this->app_id = $order['app_id'];
        $this->user_id = $order['user_id'];
        $this->amount = $order['amount'];
        $this->currency = $order['currency'];
        $this->gateway = strtolower($order['gateway']);
        $this->product_id = $order['product_id'];
        $this->end_user = $order['end_user'];
        $this->ip = $order['ip'];
        $this->custom = $order['custom'];
        $this->uuid = strtoupper($order['uuid']);
        $this->adid = strtoupper($order['adid']);
        $this->device = $order['device'];
        $this->channel = $order['channel'];
        $this->create_time = date('Y-m-d H:i:s');
        $this->save();
    }


    /**
     * 发货通知
     * @param null $order_object
     * @return bool
     */
    public function noticeTo($order_object = null)
    {
        $appConfig = $this->getAppConfig($order_object->app_id);

        $data = array(
            'transaction' => $order_object->transaction,
            'gateway' => $order_object->gateway,
            'amount' => $order_object->amount,
            'currency' => $order_object->currency,
            'product_id' => $order_object->product_id,
            'user_id' => $order_object->user_id,
            'end_user' => $order_object->end_user,
            'custom' => $order_object->custom,
            'timestamp' => time(),
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
            "logsNotice",
            array(
                "transaction" => $order_object->transaction,
                "url" => $appConfig['notify_url'],
                "request" => http_build_query($data),
                "response" => $response,
                "create_time" => date('Y-m-d H:i:s')
            )
        );

        if (strtolower($response) == 'success') {
            $order_object->status = 'complete';
            $order_object->complete_time = date('Y-m-d H:i:s');
            $order_object->save();
            return true;
        }
        return false;
    }


    /**
     * 应用配置
     * @param int $app_id
     * @return mixed
     */
    public function getAppConfig($app_id = 0)
    {
        $sql = "SELECT app_id, secret_key, notify_url FROM `apps` WHERE 1=1";
        $query = DI::getDefault()->get('dbData')->query($sql);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetchAll();
        $data = array_column($data, null, 'app_id');
        if (!isset($data[$app_id])) {
            writeLog("APP:{$app_id}, Invalid App Config", 'Error' . date('Ym'));
            Utils::outputJSON(array('code' => 1, 'msg' => "APP:{$app_id}, Invalid App Config"));
            // TODO :: 缓存
        }
        return $data[$app_id];
    }


    public function changeToUSD($amount = 0, $currency = '')
    {
        if ($currency == 'USD') {
            return $amount;
        }

        global $config;
        $k = $currency . 'USD';
        if (!isset($config->exchange->$k)) {
            writeLog("No Exchange Config: {$currency}", 'ERROR' . date('Ym'));
            return 0;
        }
        return $amount * $config->exchange->$k;
    }

}
