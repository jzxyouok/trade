<?php

namespace MyApp\Services\Pay;

use MyApp\Models\Utils;
use MyApp\Models\Orders;
use Phalcon\DI;
use Phalcon\Mvc\Controller;
use Xxtime\Util;
use DateTime;
use DateTimeZone;

class Alipay extends Controller
{
    private $transaction;


    // https://doc.open.alipay.com/docs/doc.htm?treeId=193&articleId=105286&docType=1
    public function notice()
    {
        $this->transaction = $this->request->get('out_trade_no');
        $trade_no = $this->request->get('trade_no');
        $ali_app_id = $this->request->get('app_id');
        $status = $this->request->get('trade_status');
        $amount = $this->request->get('total_amount');

        // 验签
        if (!$this->verify()) {
            $this->outputError('Signature Verify Failed');
        }


        // 查询订单
        $ordersModel = new Orders();
        $orderDetail = $ordersModel->findFirst("transaction={$this->transaction}");
        if (!$orderDetail) {
            $this->outputError('Invalid Order ID');
        }


        // 检查AppID
        $k = 'APP' . $orderDetail->app_id . '_alipayAppID';
        if ($ali_app_id != $this->config->pay->$k) {
            $this->outputError('Invalid AliPay AppID');
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
        $orderDetail->amount_usd = $ordersModel->changeToUSD($amount, $orderDetail->currency);
        $orderDetail->save();

        if ($ordersModel->noticeTo($orderDetail)) {
            $this->finished();
        }
        $this->failed();
    }


    // link https://doc.open.alipay.com/doc2/detail.htm?treeId=203&articleId=105463&docType=1
    // 备注: 支付宝WEB支付,即使签约账号默认也没有开通异步通知,需要联系技术人员开通 @20161028 by Joe 好大的坑啊!
    public function adapter($order = [])
    {
        $k_app = 'APP' . $order['app_id'] . '_alipayAppID';
        $k_key = 'APP' . $order['app_id'] . '_alipayPublicKey';
        if (empty($order['subject'])) {
            Util::output(array('code' => 1, 'msg' => 'Invalid Param [subject]'));
        }
        include BASE_DIR . $this->config->application->pluginsDir . 'alipay/AopSdk.php';
        $aop = new \AopClient ();
        $aop->appId = $this->config->pay->$k_app;
        $aop->rsaPrivateKeyFilePath = APP_DIR . "/config/files/{$order['app_id']}AlipayPrivateKey.pem";
        $aop->alipayPublicKey = $this->config->pay->$k_key;

        $request = new \AlipayTradeWapPayRequest ();
        $request->setNotifyUrl($this->config->pay->notify_url_alipay);
        //$request->setReturnUrl($this->config->pay->notify_url_alipay);
        $params = array(
            'out_trade_no' => $order['transaction'],
            'subject' => $order['subject'],
            'body' => $order['subject'],
            'timeout_express' => '120m',
            'total_amount' => $order['amount'], // 单位为元
            'product_code' => 'QUICK_WAP_PAY',

        );
        $request->setBizContent(json_encode($params, JSON_UNESCAPED_UNICODE));
        $result = $aop->pageExecute($request);
        echo $result;
    }


    // link https://doc.open.alipay.com/doc2/detail.htm?treeId=204&articleId=105465&docType=1
    public function make($order = [])
    {
        $k_app = 'APP' . $order['app_id'] . '_alipayAppID';
        $biz_content = array(
            'subject' => $order['subject'],
            'out_trade_no' => $order['transaction'],
            'timeout_express' => '120m',
            'total_amount' => $order['amount'],
            'product_code' => 'QUICK_MSECURITY_PAY',
            //'seller_id' => '',
            //'body' => '',
        );
        $data = array(
            'app_id' => $this->config->pay->$k_app,
            'method' => 'alipay.trade.app.pay',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA',
            'timestamp' => (new DateTime('now', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $this->config->pay->notify_url_alipay,
            'biz_content' => json_encode($biz_content, JSON_UNESCAPED_UNICODE)
        );
        ksort($data);

        include BASE_DIR . $this->config->application->pluginsDir . 'alipay/AopSdk.php';
        $aop = new \AopClient ();
        $aop->rsaPrivateKeyFilePath = APP_DIR . "/config/files/{$order['app_id']}AlipayPrivateKey.pem";
        $data['sign'] = $aop->rsaSign($data);

        $output = array(
            'code' => 0,
            'msg' => 'success',
            'data' => http_build_query($data)
        );

        // 注意base64加号
        Utils::outputJSON($output);
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
        writeLog("TX:{$this->transaction}, {$msg}", 'ERROR' . date('Ym'));
        Util::output(array('code' => 1, 'msg' => $msg));
    }

}
