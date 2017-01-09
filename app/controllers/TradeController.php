<?php


namespace MyApp\Controllers;

use MyApp\Models\Trade;
use MyApp\Models\Utils;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Xxtime\PayTime\Core\PayTime;
use Xxtime\Util;
use Redis;
use Phalcon\Logger\Adapter\File as FileLogger;


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
        // 日志
        $logger = new FileLogger(BASE_DIR . $this->config->application->logsDir . 'notify' . date("Ym") . '.log');
        $uri = strpos($_SERVER['REQUEST_URI'], '?') ? substr($_SERVER['REQUEST_URI'], 0,
            strpos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI'];
        $logger->info($uri . '?' . urldecode(http_build_query($_REQUEST)));


        // 回调
        $gateway = trim($this->dispatcher->getParam('param'), '/');
        $PayTime = new PayTime(ucfirst($gateway));
        $PayTime->setConfigFile(APP_DIR . '/config/trade.yml');
        $response = $PayTime->notify();


        // 结果处理
        $transactionId = $response->transactionId();
        if (!$response->isSuccessful()) {
            $logger->error($transactionId . ',' . $response->message());
            $logger->close();
            exit('failed');
        }

        $trade = $this->tradeModel->getTrade($transactionId);


        if (!$trade) {
            $logger->error($transactionId . ',' . 'no trade info');
            $logger->close();
            exit('failed');
        }
        $logger->close();


        if ($trade['status'] == 'complete') {
            exit('success');
        }


        if (!in_array($trade['status'], ['pending', 'paid'])) {
            exit($trade['status']);
        }


        $response = $this->tradeModel->noticeTo($trade, $response->transactionReference());
        // TODO :: 多种充值网关响应支持
        if ($response) {
            exit('success');
        }
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
            $PayTime = new PayTime(ucfirst($this->_order['gateway']) . '_Wap');
            $PayTime->setConfigFile(APP_DIR . '/config/trade.yml');
            $PayTime->purchase([
                'transactionId' => $this->_order['transaction'],
                'amount'        => $this->_order['amount'],
                'currency'      => $this->_order['currency'],
                'productId'     => $this->_order['product_id'],
                'productDesc'   => $this->_order['subject']
            ])->send();
            exit();
        }
        $this->view->pick("payment/standard");
    }


    /**
     * SDK下单
     */
    public function createAction()
    {
        // 检查网关
        $gateway = $this->request->get('gateway');
        if (!$gateway) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'Invalid Param [gateway]'])->send();
            exit();
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
