<?php
namespace MyApp\Services\Pay;


use MyApp\Models\Trade;
use Phalcon\Mvc\Controller;

class Apple extends Controller
{

    private $tradeModel;


    private $_receipt;


    private $_isSandbox = false;


    // 交易通知
    public function notify()
    {
        $this->tradeModel = new Trade();


        // 整理参数
        $app_id = $this->request->get('app_id', 'string');
        $user_id = $this->request->get('user_id', 'string');
        $custom = $this->request->get('custom', 'string');
        $ipAddress = $this->request->getClientAddress();
        if (!$user_id) {
            $jwt = $this->request->get('access_token', 'string');
            $account = $this->tradeModel->verifyAccessToken($jwt);
            $user_id = $account['open_id'];
        }
        if (!$app_id || !$user_id) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'argv missing'])->send();
            exit();
        }


        // 验证
        $response = $this->verify();
        if ($response === false) {
            // TODO :: 日志
            $this->response->setJsonContent(['code' => 1, 'msg' => "order verify failed"])->send();
            exit();
        }
        $transactionReference = $response['transactionReference'];
        $product_id = $response['product_id'];


        // 检查产品
        $product = $this->tradeModel->getProductById($product_id);
        if (!$product) {
            // todo :: 日志
            $this->response->setJsonContent(['code' => 1, 'msg' => "no available product $product_id"])->send();
            exit();
        }


        // 检查苹果订单 TODO :: 事务或者数据库使用唯一索引防止高并发导致的问题
        $trade = $this->tradeModel->getTradeByReference('apple', $transactionReference);
        if (!$trade) {
            $trade_data = [
                "transaction" => $this->tradeModel->createTransaction($user_id),
                "app_id"      => $app_id,
                "user_id"     => $user_id,
                "amount"      => $product['price'],
                "currency"    => $product['currency'],
                "gateway"     => 'apple',
                "product_id"  => $product_id,
                "custom"      => $custom,
                "status"      => 'paid',
                "trade_no"    => $transactionReference,
                "ip"          => $ipAddress,
                "uuid"        => '',
                "adid"        => '',
                "device"      => '',
                "channel"     => '',
                "create_time" => date('Y-m-d H:i:s') // 不使用SQL自动插入时间，避免时区不统一
            ];
            $trade = $this->tradeModel->createTrade($trade_data);
        } else {
            if (in_array($trade['status'], ['complete', 'sandbox'])) {
                $this->response->setJsonContent(['code' => 0, 'msg' => 'success'])->send();
                exit();
            }
            if ($trade['status'] != 'paid') {
                $this->response->setJsonContent(['code' => 1, 'msg' => $trade['status']])->send();
                exit();
            }
        }


        // 通知厂商
        $reponse = $this->tradeModel->noticeTo($trade, $trade['trade_no']);


        // 沙箱模式
        if ($this->_isSandbox) {
            $this->tradeModel->updateTradeStatus($trade['transaction'], 'sandbox');
        }


        // 输出
        if (!$reponse) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'notice to CP failed'])->send();
            exit();
        }

        $this->response->setJsonContent(['code' => 0, 'msg' => 'success'])->send();
        exit();
    }


    /**
     * 交易验证
     * @link https://developer.apple.com/library/ios/releasenotes/General/ValidateAppStoreReceipt/Chapters/ValidateRemotely.html
     * @link https://developer.apple.com/library/content/releasenotes/General/ValidateAppStoreReceipt/Chapters/ReceiptFields.html#//apple_ref/doc/uid/TP40010573-CH106-SW1
     * @return array|bool
     */
    public function verify()
    {
        $this->_receipt = $this->request->get('receipt');
        if (!$this->_receipt) {
            return false;
        }
        $this->_receipt = str_replace(' ', '+', $this->_receipt);

        // 正式环境验证
        $url = 'https://buy.itunes.apple.com/verifyReceipt';
        $data = json_encode(array('receipt-data' => $this->_receipt));
        $response = file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'timeout' => 30,
                'method'  => 'POST',
                'header'  => 'Content-Type:application/x-www-form-urlencoded;',
                'content' => $data
            )
        )));
        $verify = json_decode($response, true);
        if (!$verify) {
            return false;
        }


        // 沙箱环境验证
        if ($verify['status'] == 21007) {
            $url = 'https://sandbox.itunes.apple.com/verifyReceipt';
            $response = file_get_contents($url, false, stream_context_create(array(
                'http' => array(
                    'timeout' => 30,
                    'method'  => 'POST',
                    'header'  => 'Content-Type:application/x-www-form-urlencoded;',
                    'content' => $data
                )
            )));
            $this->_isSandbox = true;
            $verify = json_decode($response, true);
        }
        if ($verify['status'] != 0) {
            return false;
        }

        $result = array(
            'transactionReference' => $verify['receipt']['original_transaction_id'],
            'product_id'           => $verify['receipt']['product_id']
        );

        return $result;
    }

}