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


    private $_gateway;


    private $tradeModel;


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
        $this->_gateway = trim($this->dispatcher->getParam('param'), '/');


        // 苹果谷歌单独处理
        if (in_array($this->_gateway, ['apple', 'google'])) {
            $service = Services::pay($this->_gateway);
            $service->notify();
            exit();
        }


        $payTime = new PayTime(ucfirst($this->_gateway));
        $options = $this->getConfigOptions();
        $payTime->setOptions($options);
        $response = $payTime->notify();


        // 结果处理
        if (!$response['isSuccessful']) {
            if (!isset($response['transactionId'])) {
                $error_log = $response['message'];
            } else {
                $error_log = $response['transactionId'] . '|' . $response['message'];
            }
            $logger->error($error_log);
            $logger->close();
            exit('failed');
        }
        $transactionId = $response['transactionId'];

        // 获取订单信息
        $trade = $this->tradeModel->getTrade($transactionId);
        if (!$trade) {
            $logger->error($transactionId . '|' . 'no trade info');
            $logger->close();
            exit('failed');
        }
        $logger->close();


        // 检查订单状态
        if ($trade['status'] == 'complete') {
            exit('success');
        }
        if (!in_array($trade['status'], ['pending', 'paid'])) {
            exit($trade['status']);
        }


        // 通知CP-SERVER
        $raw = isset($response['raw']) ? $response['raw'] : '';
        $result = $this->tradeModel->noticeTo($trade, $response['transactionReference'], $raw);


        // 输出
        if ($result) {
            // 检查沙箱
            if (!empty($response['sandbox']) && $response['sandbox'] === true) {
                $this->tradeModel->updateTradeStatus($transactionId, 'sandbox');
            }
            $payTime->success();

            exit('success');
        }

        exit('notice to cp failed');
    }


    /**
     * WEB支付引导
     */
    public function indexAction()
    {
        $this->initParams();


        // 直接储值
        if ($this->_gateway && $this->_trade['product_id']) {

            // 创建订单
            $result = $this->tradeModel->createTrade($this->_trade);
            if (!$result) {
                $this->response->setJsonContent(['code' => 1, 'msg' => 'create trade failed'])->send();
                exit();
            }

            // PayTime
            $options = $this->getConfigOptions();
            $payTime = new PayTime(ucfirst($this->_gateway));
            $payTime->setOptions($options);
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
        $app = $this->tradeModel->getAppConfig($this->_app);
        $this->view->tips = isset($app['trade_tip']) ? $app['trade_tip'] : '';


        // 选择网关
        if (!$this->_gateway) {
            $this->view->gateways = $this->tradeModel->getGateways($this->_app);
            if (!$this->view->gateways) {
                $this->response->setJsonContent(['code' => 1, 'msg' => 'no gateway'])->send();
                exit();
            }
            $this->view->pick("trade/gateway");
            return true;
        }


        // 产品选择
        $this->view->products = $this->tradeModel->getProducts($this->_app, $this->_gateway);
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
        $this->_trade['app_id'] = $this->_app = $this->request->get('app_id', 'alphanum');
        $this->_trade['gateway'] = $this->_gateway = $this->request->get('gateway', 'alphanum');

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


    /**
     * 获取配置选项
     * @return mixed
     */
    private function getConfigOptions()
    {
        $config = Yaml::parse(file_get_contents(APP_DIR . '/config/trade.yml'));

        if (!isset($config[$this->_gateway])) {
            $this->response->setJsonContent(['code' => 1, 'msg' => 'no config about the gateway'])->send();
            exit();
        }

        if (isset($config[$this->_gateway][$this->_app])) {
            $options = $config[$this->_gateway][$this->_app];
        } else {
            $options = $config[$this->_gateway];
        }

        return $this->tradeModel->getFullPath($options);
    }
}
