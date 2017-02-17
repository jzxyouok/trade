<?php


namespace MyApp\Controllers;


use MyApp\Models\Trade;
use MyApp\Models\Utils;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Symfony\Component\Yaml\Yaml;
use Xxtime\PayTime\PayTime;
use Phalcon\Logger\Adapter\File as FileLogger;

class TradeController extends ControllerBase
{

    private $_trade;


    private $tradeModel;


    public $_user_id;


    public function initialize()
    {
        parent::initialize();

        $this->tradeModel = new Trade();

        $this->_user_id = $this->request->get('user_id', 'alphanum');
        if (!$this->_user_id) {
            $jwt = $this->request->get('access_token', 'string');
            $account = $this->tradeModel->verifyAccessToken($jwt);
            $this->_user_id = $account['open_id'];
        }
    }


    /**
     * 异步通知
     */
    public function notifyAction()
    {
        // 日志
        $logger = new FileLogger(APP_DIR . '/logs/notify' . date("Ym") . '.log');
        $uri = strpos($_SERVER['REQUEST_URI'], '?') ? substr($_SERVER['REQUEST_URI'], 0,
            strpos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI'];
        $logger->info($uri . '?' . urldecode(http_build_query($_REQUEST)));
        unset($_GET['_url']); // 必须去掉_url


        // 网关
        $gateway = trim($this->dispatcher->getParam('param'), '/');


        // Apple && Google
        if (in_array($gateway, ['apple', 'google'])) {
            $service = Services::pay($gateway);
            $service->notify();
            exit();
        }


        $payTime = new PayTime(ucfirst($gateway));
        $config = Yaml::parse(file_get_contents(APP_DIR . '/config/trade.yml'));
        $payTime->setOptions($this->tradeModel->getFullPath($config[$gateway]));
        $response = $payTime->notify();


        // 结果处理
        $transactionId = $response['transactionId'];
        if (!$response['isSuccessful']) {
            $logger->error($transactionId . '|' . $response['message']);
            $logger->close();
            exit('failed');
        }

        $trade = $this->tradeModel->getTrade($transactionId);
        if (!$trade) {
            $logger->error($transactionId . '|' . 'no trade info');
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


        $response = $this->tradeModel->noticeTo($trade, $response['transactionReference']);
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


        // 直接储值
        if ($this->_trade['gateway'] && $this->_trade['product_id']) {

            $config = Yaml::parse(file_get_contents(APP_DIR . '/config/trade.yml'));

            $gateway = $this->_trade['gateway'];

            // 检查配置
            if (!isset($config[$gateway])) {
                $this->response->setJsonContent(['code' => 1, 'msg' => 'no config about the gateway'])->send();
                exit();
            }

            // 创建订单
            $result = $this->tradeModel->createTrade($this->_trade);
            if (!$result) {
                $this->response->setJsonContent(['code' => 1, 'msg' => 'create trade failed'])->send();
                exit();
            }

            // PayTime
            $payTime = new PayTime(ucfirst($gateway));
            $payTime->setOptions($this->tradeModel->getFullPath($config[$gateway]));
            $payTime->purchase([
                'transactionId' => $result['transaction'],
                'amount'        => $result['amount'],
                'currency'      => $result['currency'],
                'productId'     => $result['product_id'],
                'productDesc'   => $this->_trade['subject'] ? urlencode($this->_trade['subject']) : $result['product_id']
            ]);
            $payTime->send();
            exit();
        }


        // tips
        $app = $this->tradeModel->getAppConfig($this->_trade['app_id']);
        $this->view->tips = isset($app['trade_tip']) ? $app['trade_tip'] : '';


        // 选择网关
        if (!$this->_trade['gateway']) {
            $this->view->gateways = $this->tradeModel->getGateways($this->_trade['app_id']);
            if (!$this->view->gateways) {
                exit('error, no gateway');
            }
            $this->view->pick("trade/gateway");
            return true;
        }


        // 产品选择
        $this->view->products = $this->tradeModel->getProducts($this->_trade['app_id'], $this->_trade['gateway']);
        $this->view->pick("trade/standard");
    }


    /**
     * SDK下单
     */
    public function createAction()
    {
        // 检查网关
        $gateway = $this->request->get('gateway');
        if (!$gateway) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'invalid argv [gateway]'])->send();
            exit();
        }

        $this->initParams();

        $result = $this->tradeModel->createTrade($this->_trade);
        if (!$result) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'create trade failed'])->send();
            exit();
        }

        // TODO :: 未完待续
    }


    /**
     * 整理参数
     */
    private function initParams()
    {
        $this->_trade['transaction'] = $this->tradeModel->createTransaction($this->_user_id);

        // 重要参数
        $this->_trade['app_id'] = $this->request->get('app_id', 'alphanum');
        $this->_trade['gateway'] = $this->request->get('gateway', 'alphanum');

        // 关键参数
        $this->_trade['user_id'] = $this->_user_id;
        $this->_trade['custom'] = $this->request->get('custom', 'string');
        $this->_trade['amount'] = $this->request->get('amount', 'float');
        $this->_trade['currency'] = $this->request->get('currency', 'alphanum');
        $this->_trade['product_id'] = $this->request->get('product_id', 'string');
        $this->_trade['subject'] = $this->request->get('subject', 'string');

        // 统计参数
        $this->_trade['uuid'] = $this->request->get('uuid', 'string');
        $this->_trade['adid'] = $this->request->get('adid', 'string');
        $this->_trade['device'] = $this->request->get('device', 'string');
        $this->_trade['channel'] = $this->request->get('channel', 'string');

        $this->_trade['ip'] = $this->request->getClientAddress();

        // 检查参数
        if (!$this->_trade['app_id']) {
            Utils::outputJSON(array('code' => 1, 'msg' => 'Invalid Param [app_id]'));
        }
        if (!$this->_trade['subject']) {
            $this->_trade['subject'] = $this->_trade['product_id'];
        }
    }
}
