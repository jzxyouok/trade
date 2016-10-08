<?php


namespace MyApp\Controllers;

use MyApp\Models\Orders;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Xxtime\Util;
use Redis;


class PaymentController extends ControllerBase
{

    private $_order;
    private $ordersModel;

    public function initialize()
    {
        parent::initialize();
        $this->ordersModel = new Orders();
    }


    /**
     * 异步通知
     */
    public function noticeAction()
    {
        $gateway = trim($this->dispatcher->getParam('param'), '/');
        Services::pay($gateway)->notice();
    }


    /**
     * 支付引导
     */
    public function indexAction()
    {
        $this->initParams();
        if ($this->_order['gateway']) {
            $this->ordersModel->makeOrder($this->_order);
            Services::pay($this->_order['gateway'])->adapter($this->_order);
            exit();
        }
        $this->view->pick("payment/standard");
    }


    /**
     * 获取序列号
     * @return int
     */
    private function getSequence()
    {
        global $config;
        $redis = new Redis();
        $redis->connect($config->redis->host, $config->redis->port);
        $redis->select(1);
        return $redis->incr('sequence');
    }


    /**
     * 整理参数
     */
    private function initParams()
    {
        $user_id = $this->request->get('user_id', 'int');
        $this->_order['transaction'] = Util::createTransaction($this->getSequence(), $user_id);
        $this->_order['app_id'] = $this->_app;
        $this->_order['user_id'] = $user_id;
        $this->_order['amount'] = $this->request->get('amount', 'float');
        $this->_order['currency'] = $this->request->get('currency', 'alphanum');
        $this->_order['product_id'] = $this->request->get('product_id', 'string');
        $this->_order['end_user'] = $this->request->get('end_user', 'string');
        $this->_order['gateway'] = $this->request->get('gateway', 'alphanum');

        $this->_order['uuid'] = $this->request->get('uuid', 'string');
        $this->_order['idfa'] = $this->request->get('idfa', 'string');
        $this->_order['os'] = $this->request->get('os', 'string');
        $this->_order['channel'] = $this->request->get('channel', 'string');
        $this->_order['ip'] = $this->request->getClientAddress();
        $this->_order['extra'] = $this->request->get('extra', 'string');

        // 其他参数
        $this->_order['subject'] = $this->request->get('subject', 'string');


        // 检查参数
        if (!$this->_order['amount']) {
            Util::output(array('code' => 1, 'msg' => 'Invalid Param [amount]'));
        }
    }
}
