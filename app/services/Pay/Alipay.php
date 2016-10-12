<?php

namespace MyApp\Services\Pay;

use MyApp\Models\Utils;
use MyApp\Models\Orders;
use Phalcon\DI;
use Phalcon\Mvc\Controller;
use Xxtime\Util;

class Alipay extends Controller
{
    private $transaction;


    public function notice()
    {
        $this->transaction = $this->request->get('out_trade_no');
        $trade_no = $this->request->get('trade_no');
        $app_id = $this->request->get('app_id');
        $status = $this->request->get('trade_status');
        $amount = $this->request->get('total_amount');

        // 验签
        if (!$this->verify()) {
            $this->outputError('Signature Verify Failed');
        }

        // 检查AppID
        $k = 'APP' . $app_id . '_alipayAppID';
        if ($app_id != $this->config->pay->$k) {
            $this->outputError('Invalid AliPay AppID');
        }

        // 查询订单
        $ordersModel = new Orders();
        $orderDetail = $ordersModel->findFirst("transaction={$this->transaction}");
        if (!$orderDetail) {
            $this->outputError('Invalid Order ID');
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

        switch ($status) {
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED':
                break;
            case 'TRADE_CLOSED':
                $orderDetail->status = 'CLOSED';
                $orderDetail->save();
                $this->finished();
                break;
            default:
                $this->finished();
        }


        $orderDetail->status = 'paid';
        $orderDetail->gateway = 'alipay';
        $orderDetail->trade_no = $trade_no;
        $orderDetail->amount = $amount;
        $orderDetail->currency = 'CNY';
        $orderDetail->save();

        if ($ordersModel->noticeTo($orderDetail)) {
            $this->finished();
        }
        $this->failed();
    }


    public function adapter($order = [])
    {
        $k_app = 'APP' . $order['app_id'] . '_alipayAppID';
        $k_key = 'APP' . $order['app_id'] . '_alipayPublicKey';
        if (empty($order['subject'])) {
            Util::output(array('code' => 1, 'msg' => 'Invalid Param [subject]'));
        }
        include BASE_DIR . $this->config->application->pluginsDir . 'alipay/AopSdk.php';
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->config->pay->$k_app;
        $aop->rsaPrivateKeyFilePath = APP_DIR . "/config/files/{$order['app_id']}AlipayPrivateKey.pem";
        $aop->alipayPublicKey = $this->config->pay->$k_key;
        $aop->apiVersion = '1.0';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        //$aop->notify_url = 'https://pay.xxtime.com/notice/alipay';
        //$aop->return_url = 'https://pay.xxtime.com/notice/alipay';
        $request = new \AlipayTradeWapPayRequest ();
        $params = array(
            'out_trade_no' => $order['transaction'],
            'subject' => $order['subject'],
            'body' => $order['subject'],
            'timeout_express' => '120m',
            'total_amount' => $order['amount'],
            'product_code' => 'QUICK_WAP_PAY',

        );
        $request->setBizContent(json_encode($params));
        $result = $aop->pageExecute($request);
        echo $result;
    }


    public function make($order = [])
    {
        Utils::outputJSON($order);
    }


    private function verify()
    {
        $req = $_REQUEST;
        if (empty($req['sign'])) {
            $this->outputError('No Param [sign]');
        }
        $signature = base64_decode(str_replace(' ', '+', $req['sign']));
        unset($req['sign'], $req['sign_type']);
        ksort($req);
        $verifyData = '';
        foreach ($req as $key => $value) {
            $verifyData .= "$key=$value&";
        }

        $app_id = $this->request->get('app_id');
        $k = 'APP' . $app_id . '_alipayPublicKey';
        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->config->pay->$k, 64, "\n") .
            '-----END PUBLIC KEY-----';
        $pub_key_id = openssl_get_publickey($public_key);
        $result = openssl_verify($verifyData, $signature, $pub_key_id, OPENSSL_ALGO_SHA1);
        if ($result == 1) {
            return true;
        } elseif ($result == 0) {
            return false;
        } else {
            return false;
        }
    }


    private function finished()
    {
        exit('success');
    }


    private function failed()
    {
        exit('failed');
    }


    private function outputError($msg = '')
    {
        writeLog("TX:{$this->transaction}, {$msg}", 'Error' . date('Ym'));
        Util::output(array('code' => 1, 'msg' => $msg));
    }

}
