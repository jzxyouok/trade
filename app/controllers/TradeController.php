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
     * WEB支付引导
     */
    public function indexAction()
    {
        $this->initParams();


        // 选择网关
        if (!$this->_gateway) {
            // tips
            $app = $this->tradeModel->getAppConfig($this->_app);
            $this->view->tips = isset($app['trade_tip']) ? $app['trade_tip'] : '';

            // 网关列表
            $this->view->gateways = $this->tradeModel->getGateways($this->_app);
            if (!$this->view->gateways) {
                Utils::tips('error', _('error gateway'));
            }

            // 模板
            $this->view->pick("trade/gateway");
            return true;
        }


        // 判断当前是否终极网关
        if (!$this->_trade['sub']) {
            $this->view->gateways = $this->tradeModel->getGateways($this->_app, $this->_gateway);
            if ($this->view->gateways) {
                $this->view->tips = $this->tradeModel->getTips($this->_app, $this->_gateway);
                $this->view->pick("trade/gateway");
                return true;
            }
        }


        // 以下已经决策出终极网关
        $gateway = $this->tradeModel->getFinalGateway($this->_app, $this->_gateway, $this->_trade['sub']);
        if (!$gateway) {
            Utils::tips('error', _('error gateway'));
        }


        // 产品选择
        if ((!$this->_trade['product_id']) && ($gateway['type'] == 'wallet')) {
            $this->view->tips = '';
            $this->view->products = $this->tradeModel->getProducts($this->_app, $this->_gateway);
            $this->view->pick("trade/standard");
            return true;
        }


        // 创建订单
        $result = $this->tradeModel->createTrade($this->_trade);
        if (!$result) {
            Utils::tips('error', _('create trade failed'));
        }

        // PayTime
        $options = $this->getConfigOptions($gateway['sandbox']);
        $options['sandbox'] = $gateway['sandbox'];  // 是否沙箱
        $options['type'] = $gateway['type'];        // 支付类型
        $gateway_name = $this->_gateway;
        if ($this->_trade['sub']) {
            $gateway_name = $this->_gateway . '_' . $this->_trade['sub'];
        }
        $payTime = new PayTime(ucfirst($gateway_name));
        $payTime->setOptions($options);
        $payTime->purchase([
            'transactionId' => $result['transaction'],
            'amount'        => $result['amount'],
            'currency'      => $result['currency'],
            'productId'     => $result['product_id'],
            'productDesc'   => $this->_trade['subject'] ? urlencode($this->_trade['subject']) : $result['product_id'],
            'custom'        => $this->_app
        ]);


        // 响应处理
        try {
            $response = $payTime->send();

            // start call service process, only MyCard can get here now
            $service = Services::pay($this->_gateway);
            $service->process($result['transaction'], $response);
            // end call

            if (isset($response['redirect'])) {
                $payTime->redirect();
            }
        } catch (\Exception $e) {
            // TODO :: error log
            Utils::tips('error', $e->getMessage());
        }
        exit();
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
        $this->_trade['sub'] = $this->request->get('sub', 'alphanum');

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
            Utils::tips('error', _('missing parameter') . ' app_id');
        }
        if (!$this->_trade['user_id']) {
            Utils::tips('error', _('missing parameter') . ' user_id');
        }
        if (!$this->_trade['subject']) {
            $this->_trade['subject'] = $this->_trade['product_id'];
        }
    }


    /**
     * 获取配置选项
     * @param int $sandbox
     * @return mixed
     * @throws \Exception
     */
    private function getConfigOptions($sandbox = 0)
    {
        if (!$sandbox) {
            $config = Yaml::parse(file_get_contents(APP_DIR . '/config/trade.yml'));
        } else {
            try {
                $config = Yaml::parse(file_get_contents(APP_DIR . '/config/sandbox.trade.yml'));
            } catch (\Exception $e) {
                throw new \Exception('can`t find file sandbox.trade.yml');
            }
        }

        if (!isset($config[$this->_gateway])) {
            throw new \Exception('no config about the gateway');
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