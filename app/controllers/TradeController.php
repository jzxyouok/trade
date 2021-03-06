<?php


namespace MyApp\Controllers;


use MyApp\Models\Trade;
use MyApp\Models\Utils;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Symfony\Component\Yaml\Yaml;
use Xxtime\PayTime\PayTime;
use Phalcon\Logger\Adapter\File as FileLogger;
use Exception;

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
            if ($jwt) {
                $account = $this->tradeModel->verifyAccessToken($jwt);
                $this->_user_id = $account['open_id'];
            }
        }
    }


    /**
     * WEB支付引导
     */
    public function indexAction()
    {
        try {
            $this->initParams();
        } catch (Exception $e) {
            Utils::tips('error', $e->getMessage());
        }


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


        /**
         * 个别网关单独处理
         * TODO :: 不够优美
         */
        if ($this->_gateway == 'mycard' && $this->_trade['sub'] == 'telecom') {
            $req = array_merge($_GET, $_POST);
            unset($req['_url']);
            header('Location: /mycard/telecom?' . http_build_query($req));
            exit();
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
        try {
            $tradeResult = $this->tradeModel->createTrade($this->_trade);
        } catch (Exception $e) {
            Utils::tips('warn', _($e->getMessage()));
        }


        // 获取配置
        try {
            $options = $this->getConfigOptions($gateway['sandbox']);
        } catch (Exception $e) {
            Utils::tips('error', $e->getMessage());
        }
        $options['sandbox'] = $gateway['sandbox'];  // 是否沙箱
        $options['type'] = $gateway['type'];        // 支付类型
        $gateway_name = $this->_gateway;
        if ($this->_trade['sub']) {
            $gateway_name = $this->_gateway . '_' . $this->_trade['sub'];
        }


        // PayTime
        $payTime = new PayTime(ucfirst($gateway_name));
        $payTime->setOptions($options);
        $payTime->purchase([
            'transactionId' => $tradeResult['transaction'],
            'amount'        => $tradeResult['amount'],
            'currency'      => $tradeResult['currency'],
            'productId'     => $tradeResult['product_id'],
            'productDesc'   => $this->_trade['subject'] ? urlencode($this->_trade['subject']) : $tradeResult['product_id'],
            'userId'        => $this->_user_id,
            'custom'        => $this->_app,
        ]);


        // 响应处理
        try {
            $response = $payTime->send();

            // start call service process, only MyCard can get here now
            $service = Services::pay($this->_gateway);
            $service->process($tradeResult['transaction'], $response);
            // end call

            if (isset($response['redirect'])) {
                $payTime->redirect();
            }
        } catch (Exception $e) {
            // TODO :: error log
            Utils::tips('warn', $e->getMessage());
        }
    }


    /**
     * 卡片CDK页面
     * 目前支持MyCard
     */
    public function cardAction()
    {
        $transactionId = $this->request->get('transaction');
        $gateway = $this->request->get('gateway');

        if ($_POST) {
            $parameter = [
                'transaction' => $transactionId,
                'user_id'     => '',
                'auth'        => $this->request->get('auth'),
                'card_no'     => $this->request->get('card_no'),
                'card_pwd'    => $this->request->get('card_pwd'),
            ];


            // 获取配置
            try {
                $options = $this->getConfigOptions();
            } catch (Exception $e) {
                Utils::tips('error', $e->getMessage());
            }
            $options['sandbox'] = 0;
            $options['type'] = 'card';
            $gateway_name = $gateway . '_card';


            // PayTime
            $payTime = new PayTime(ucfirst($gateway_name));
            $payTime->setOptions($options);

            // 失败或者异常需要记录卡号
            try {
                $response = $payTime->card($parameter);
                if (!$response['isSuccessful']) {
                    throw new Exception('failed');
                }
                $trade_info = $this->tradeModel->getTrade($parameter['transaction']);
                $raw = isset($response['raw']) ? $response['raw'] : '';
                $result = $this->tradeModel->noticeTo($trade_info, $response['transactionReference'], $raw);
                if (!$result) {
                    throw new Exception('failed');
                }
                Utils::tips('success', _('success'));
            } catch (Exception $e) {
                // 记录卡号
                $log = $parameter['card_no'] . '|' . $parameter['card_pwd'];
                writeLog($log);
            }
            Utils::tips('warn', _('failed'));
        }

        $transaction = $this->tradeModel->getTrade($transactionId);
        $this->view->tips = $this->tradeModel->getTips($transaction['app_id'], $gateway);
    }


    /**
     * APP SDK下单
     */
    public function createAction()
    {
        // 初始化参数
        try {
            $this->initParams();
        } catch (Exception $e) {
            $this->response->setJsonContent(
                [
                    'code' => 1,
                    'msg'  => $e->getMessage()
                ],
                JSON_UNESCAPED_UNICODE
            )->send();
            exit();
        }


        // 检查网关
        if (!$this->_trade['gateway']) {
            $this->response->setJsonContent(['code' => 1, 'msg' => _('missing parameter')])->send();
            exit();
        }


        // 检查产品
        if (!$this->_trade['product_id']) {
            $this->response->setJsonContent(['code' => 1, 'msg' => _('missing parameter')])->send();
            exit();
        }


        // 创建订单
        try {
            $tradeResult = $this->tradeModel->createTrade($this->_trade);
        } catch (Exception $e) {
            $this->response->setJsonContent(['code' => 1, 'msg' => _($e->getMessage())])->send();
            exit();
        }


        // PayTime
        $options = $this->getConfigOptions();
        $options['sandbox'] = false;  // 是否沙箱
        $options['type'] = 'wallet';  // 支付类型
        $gateway_name = $this->_gateway . '_' . 'App'; // 所有Sdk下单默认子网关都是App

        $payTime = new PayTime(ucfirst($gateway_name));
        $payTime->setOptions($options);
        $payTime->purchase([
            'transactionId' => $tradeResult['transaction'],
            'amount'        => $tradeResult['amount'],
            'currency'      => $tradeResult['currency'],
            'productId'     => $tradeResult['product_id'],
            'productDesc'   => $this->_trade['subject'] ? urlencode($this->_trade['subject']) : $tradeResult['product_id'],
            'custom'        => $this->_app,
            'userId'        => $this->_user_id,
        ]);


        // 结果输出
        try {
            $response = $payTime->send();
            $this->response->setJsonContent(
                [
                    'code'        => 0,
                    'msg'         => _('success'),
                    'transaction' => $response['transactionId'],
                    'product_id'  => $response['productId'],
                    'amount'      => $response['amount'],
                    'currency'    => $response['currency'],
                    'reference'   => $response['transactionReference'],
                    'raw'         => $response['raw'],
                ]
            )->send();
        } catch (Exception $e) {
            $this->response->setJsonContent(
                ['code' => 1, 'msg' => _($e->getMessage())], JSON_UNESCAPED_UNICODE
            )->send();
        }
        exit();
    }


    /**
     * 整理参数
     */
    private function initParams()
    {
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
            throw new Exception(_('missing parameter') . ' app_id');
        }
        if (!$this->_trade['user_id']) {
            throw new Exception(_('missing parameter') . ' user_id');
        }
        if (!$this->_trade['subject']) {
            $this->_trade['subject'] = $this->_trade['product_id'];
        }
    }


    /**
     * 获取配置选项
     * @param int $sandbox
     * @return mixed
     * @throws Exception
     */
    private function getConfigOptions($sandbox = 0)
    {
        if (!$sandbox) {
            $config = Yaml::parse(file_get_contents(APP_DIR . '/config/trade.yml'));
        } else {
            try {
                $config = Yaml::parse(file_get_contents(APP_DIR . '/config/sandbox.trade.yml'));
            } catch (Exception $e) {
                throw new Exception(_('no config'));
            }
        }

        if (!isset($config[$this->_gateway])) {
            throw new Exception(_('no config'));
        }

        if (isset($config[$this->_gateway][$this->_app])) {
            $options = $config[$this->_gateway][$this->_app];
        } else {
            $options = $config[$this->_gateway];
        }

        return $this->tradeModel->getFullPath($options);
    }

}