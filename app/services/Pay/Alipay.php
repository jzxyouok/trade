<?php

namespace MyApp\Services\Pay;

use Phalcon\DI;
use MyApp\Models\Orders;
use Phalcon\Mvc\Controller;
use Xxtime\Util;

class Alipay extends Controller
{

    public function notice()
    {
        $transaction = $this->request->get('out_trade_no');
        $seq = $this->request->get('trade_no');
        $app_id = $this->request->get('app_id');
        $status = $this->request->get('trade_status');
        $amount = $this->request->get('total_amount');

        // 验签
        $this->verify();

        // 检查AppID
        if ($app_id != $this->config->pay->alipayAppID) {
            Util::output(array('code' => 1, 'msg' => 'Invalid AliPay AppID'));
        }

        // 查询订单
        $ordersModel = new Orders();
        $orderDetail = $ordersModel->findFirst("transaction=$transaction");
        if (!$orderDetail) {
            Util::output(array('code' => 1, 'msg' => 'Invalid Order ID'));
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


        $orderDetail->status = 'PAID';
        $orderDetail->gateway = 'alipay';
        $orderDetail->seq = $seq;
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
        if (empty($order['subject'])) {
            Util::output(array('code' => 1, 'msg' => 'Invalid Param [subject]'));
        }
        include BASE_DIR . $this->config->application->pluginsDir . 'alipay/AopSdk.php';
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->config->pay->alipayAppID;
        $aop->rsaPrivateKeyFilePath = APP_DIR . '/config/files/alipayPrivateKey.pem';
        $aop->alipayPublicKey = $this->config->pay->alipayPublicKey;
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


    private function verify()
    {
    }


    private function finished()
    {
        exit('success');
    }


    private function failed()
    {
        exit('failed');
    }

}
