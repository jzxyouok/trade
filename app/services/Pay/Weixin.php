<?php

namespace MyApp\Services\Pay;

use MyApp\Models\Orders;
use MyApp\Models\Utils;
use Phalcon\DI;
use Phalcon\Mvc\Controller;
use Xxtime\Lalit\Array2XML;
use Xxtime\Util;
use DateTime;
use DateTimeZone;

class Weixin extends Controller
{
    private $transaction;


    // link: https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_7&index=3
    public function notice()
    {
        $dataXML = file_get_contents("php://input");
        $data = simplexml_load_string(
            $dataXML
            , null
            , LIBXML_NOCDATA
        );
        $data = (array)$data;

        // 检查
        if (($data['return_code'] != 'SUCCESS') || ($data['result_code'] != 'SUCCESS')) {
            $this->outputError(str_replace(["\n", ' '], '', $dataXML));
        }

        $trade_no = isset($data['transaction_id']) ? $data['transaction_id'] : null;
        $amount = isset($data['total_fee']) ? $data['total_fee'] / 100 : null;
        $currency = isset($data['fee_type']) ? $data['fee_type'] : '';
        $this->transaction = isset($data['out_trade_no']) ? $data['out_trade_no'] : 0;
        if (!$trade_no || !$amount || !$currency || !$this->transaction) {
            $this->outputError('Notice Data Error');
        }


        // 查询订单
        $ordersModel = new Orders();
        $orderDetail = $ordersModel->findFirst(array(
                "conditions" => "transaction = :tx:",
                "bind" => array("tx" => $this->transaction))
        );
        if (!$orderDetail) {
            $this->outputError('Invalid Order ID');
        }


        // 验签
        if ($data['sign'] != $this->createSign($orderDetail->app_id, $data)) {
            $this->outputError("APP:$orderDetail->app_id, Signature Verify Failed");
        }


        // 检查订单状态
        switch ($orderDetail->status) {
            case 'pending':
                break;
            case 'paid':
                if ($ordersModel->noticeTo($orderDetail)) {
                    $this->finished();
                }
                $this->failed();
                break;
            case 'complete':
            case 'sandbox':
                $this->finished();
                break;
            default:
                $this->finished();
        }

        $orderDetail->status = 'paid';
        $orderDetail->gateway = 'weixin';
        $orderDetail->trade_no = $trade_no;
        $orderDetail->amount = $amount;
        $orderDetail->currency = $currency;
        $orderDetail->amount_usd = $ordersModel->changeToUSD($amount, $orderDetail->currency);
        $orderDetail->save();

        if ($ordersModel->noticeTo($orderDetail)) {
            $this->finished();
        }
        $this->failed();
    }


    // TODO :: 未完成, 商户没有H5支付权限
    public function adapter($order = [])
    {
        $this->transaction = $order['transaction'];
        $app_id = $order['app_id'];
        $k_app = "APP{$app_id}_wxAppID";
        $k_mchid = "APP{$app_id}_wxMCHID";

        // 检查必要参数
        $body = $this->request->get('subject', 'string');
        if (!$body) {
            $this->outputError('Invalid Param [subject]');
        }

        // 整理参数
        $data = array(
            'appid' => $this->config->pay->$k_app,
            'mch_id' => $this->config->pay->$k_mchid,
            'device_info' => 'WEB',
            'nonce_str' => Util::random(32),
            'body' => $body,
            'out_trade_no' => $order['transaction'],
            'fee_type' => $order['currency'],
            'total_fee' => intval($order['amount'] * 100),
            'spbill_create_ip' => $order['ip'],
            'time_start' => (new DateTime('now', new DateTimeZone('Asia/Shanghai')))->format('YmdHis'),
            'time_expire' => (new DateTime('1 days', new DateTimeZone('Asia/Shanghai')))->format('YmdHis'),
            'notify_url' => $this->config->pay->notify_url_weixin,
            'trade_type' => 'MWEB',
            //'attach' => '',
            //'detail' => '',
            //'limit_pay' => 'no_credit'
        );
        $data['sign'] = $this->createSign($app_id, $data);

        // 准备数据
        $xml = Array2XML::createXML('xml', $data);
        $xmlData = $xml->saveXML();

        // 请求
        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com/pay/unifiedorder');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'XXTIME.COM');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['POWER:XXTIME.COM']);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response !== false) {
                break;
            }
        }
        if (!$response) {
            $this->outputError("APP:$app_id, Response Error");
        }

        $resData = simplexml_load_string(
            $response
            , null
            , LIBXML_NOCDATA
        );
        $resData = (array)$resData;

        // 记录失败日志
        if (($resData['return_code'] != 'SUCCESS') || ($resData['result_code'] != 'SUCCESS')) {
            $this->outputError("APP:$app_id, " . str_replace("\n", '', $response));
        }
    }


    // link: https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_1
    public function make($order = [])
    {
        $this->transaction = $order['transaction'];
        $app_id = $order['app_id'];
        $k_app = "APP{$app_id}_wxAppID";
        $k_mchid = "APP{$app_id}_wxMCHID";

        // 检查必要参数
        $body = $this->request->get('subject', 'string');
        if (!$body) {
            $this->outputError('Invalid Param [subject]');
        }

        // 整理参数
        $data = array(
            'appid' => $this->config->pay->$k_app,
            'mch_id' => $this->config->pay->$k_mchid,
            'device_info' => 'WEB',
            'nonce_str' => Util::random(32),
            'body' => $body,
            'out_trade_no' => $order['transaction'],
            'fee_type' => $order['currency'],
            'total_fee' => intval($order['amount'] * 100),
            'spbill_create_ip' => $order['ip'],
            'time_start' => (new DateTime('now', new DateTimeZone('Asia/Shanghai')))->format('YmdHis'),
            'time_expire' => (new DateTime('1 days', new DateTimeZone('Asia/Shanghai')))->format('YmdHis'),
            'notify_url' => $this->config->pay->notify_url_weixin,
            'trade_type' => 'APP',
            //'attach' => '',
            //'detail' => '',
            //'limit_pay' => 'no_credit'
        );
        $data['sign'] = $this->createSign($app_id, $data);

        // 准备数据
        $xml = Array2XML::createXML('xml', $data);
        $xmlData = $xml->saveXML();

        // 请求
        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com/pay/unifiedorder');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'XXTIME.COM');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['POWER:XXTIME.COM']);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response !== false) {
                break;
            }
        }
        if (!$response) {
            $this->outputError("APP:$app_id, Response Error");
        }

        $resData = simplexml_load_string(
            $response
            , null
            , LIBXML_NOCDATA
        );
        $resData = (array)$resData;

        // 记录失败日志
        if (($resData['return_code'] != 'SUCCESS') || ($resData['result_code'] != 'SUCCESS')) {
            $this->outputError("APP:$app_id, " . str_replace("\n", '', $response));
        }


        // 验签
        if ($resData['sign'] != $this->createSign($app_id, $resData)) {
            $this->outputError("APP:$app_id, Signature Verify Failed");
        }


        $output = array(
            'code' => 0,
            'msg' => 'success',
            'data' => array(
                'wx_app_id' => $resData['appid'],
                'wx_mch_id' => $resData['mch_id'],
                'wx_trade_type' => $resData['trade_type'],
                'wx_prepay_id' => $resData['prepay_id'],
            )
        );
        Utils::outputJSON($output);
    }


    private function createSign($app_id = 0, $data = [])
    {
        unset($data['sign']);
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= "$key=$value&";
        }
        $k_mchkey = "APP{$app_id}_wxMCHKEY";
        return strtoupper(md5($string . 'key=' . $this->config->pay->$k_mchkey));
    }


    private function finished()
    {
        exit('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>');
    }


    private function failed()
    {
        exit('failed');
    }


    private function outputError($msg = '')
    {
        writeLog("TX:{$this->transaction}, {$msg}", 'error' . date('Ym'));
        Utils::outputJSON(array('code' => 1, 'msg' => $msg));
    }

}
