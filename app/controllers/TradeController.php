<?php


namespace MyApp\Controllers;

use MyApp\Models\Trade;
use MyApp\Models\Utils;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Xxtime\Util;
use Redis;


class TradeController extends ControllerBase
{

    private $_order;
    private $tradeModel;

    public function initialize()
    {
        parent::initialize();
        $this->tradeModel = new Trade();
    }


    /**
     * 异步通知
     */
    public function notifyAction()
    {
        $uri = strpos($_SERVER['REQUEST_URI'], '?') ? substr($_SERVER['REQUEST_URI'], 0,
            strpos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI'];
        writeLog($uri . '?' . urldecode(http_build_query($_REQUEST)), 'NOTICE' . date('Ym'));
        $gateway = trim($this->dispatcher->getParam('param'), '/');
        Services::pay($gateway)->notice();
    }


    /**
     * WEB支付引导
     */
    public function indexAction()
    {
        $this->initParams();
        if ($this->_order['gateway']) {
            $result = $this->tradeModel->createTrade($this->_order);
            if (!$result) {
                $this->response->setJsonContent(['code' => 1, 'msg' => 'create trade failed'])->send();
                exit();
            }
            Services::pay($this->_order['gateway'])->adapter($this->_order);
            exit();
        }
        $this->view->pick("payment/standard");
    }


    /**
     * SDK下单
     */
    public function createAction()
    {
        $gateway = $this->request->get('gateway');
        if (!$gateway) {
            Utils::outputJSON(array('code' => 1, 'msg' => 'Invalid Param [gateway]'));
        }
        $this->initParams();
        $result = $this->tradeModel->createTrade($this->_order);
        if (!$result) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'create trade failed'])->send();
            exit();
        }
        Services::pay($gateway)->make($this->_order);
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
        $user_id = $this->request->get('user_id', 'alphanum');
        $this->_order['transaction'] = Util::createTransaction($this->getSequence(), $user_id);

        // 重要参数
        $this->_order['app_id'] = $this->request->get('app_id', 'alphanum');
        $this->_order['gateway'] = $this->request->get('gateway', 'alphanum');

        // 关键参数
        $this->_order['user_id'] = $user_id;
        $this->_order['end_user'] = $this->request->get('end_user', 'string');
        $this->_order['amount'] = $this->request->get('amount', 'float');
        $this->_order['currency'] = $this->request->get('currency', 'alphanum');
        $this->_order['product_id'] = $this->request->get('product_id', 'string');
        $this->_order['subject'] = $this->request->get('subject', 'string');

        // 统计参数
        $this->_order['uuid'] = $this->request->get('uuid', 'string');
        $this->_order['adid'] = $this->request->get('adid', 'string');
        $this->_order['device'] = $this->request->get('device', 'string');
        $this->_order['channel'] = $this->request->get('channel', 'string');

        $this->_order['ip'] = $this->request->getClientAddress();

        // 检查参数
        if (!$this->_order['app_id']) {
            Utils::outputJSON(array('code' => 1, 'msg' => 'Invalid Param [app_id]'));
        }
    }
}