<?php

/**
 * MyCard网关
 * 特殊处理
 */
namespace MyApp\Controllers;


use MyApp\Models\Trade;
use MyApp\Models\Utils;
use MyApp\Services\Services;
use Phalcon\Mvc\Dispatcher;
use Symfony\Component\Yaml\Yaml;
use Xxtime\PayTime\PayTime;
use Exception;

class MycardController extends ControllerBase
{

    private $tradeModel;

    private $_gateway = 'mycard';


    public function initialize()
    {
        parent::initialize();
        $this->tradeModel = new Trade();
    }


    /**
     * MyCard Telecom支付
     * @throws Exception
     */
    public function telecomAction()
    {
        $app_id = $this->request->get('app_id');
        $channel = $this->request->get('channel');
        $product_id = $this->request->get('product_id');
        $user_id = $this->request->get('user_id');
        $custom = $this->request->get('custom');


        // 检查
        if (!$app_id) {
            Utils::tips('warn', 'error');
        }


        // 配置
        $config = Yaml::parse(file_get_contents(APP_DIR . '/config/mycard.yml'));
        foreach ($config as $key => $value) {
            $channelList[$key] = $value['name'];
        }


        // 赋值
        $this->view->channelList = $channelList;
        $this->view->tips = '';
        $this->view->app_id = $app_id;
        $this->view->user_id = $user_id;
        $this->view->custom = $custom;
        $this->view->channel = $channel;
        $this->view->products = [];


        // 请求MyCard
        if ($product_id && $channel) {
            $data = [
                'app_id'     => $app_id,
                'gateway'    => 'mycard',
                'user_id'    => $user_id,
                'product_id' => $product_id,
                'custom'     => $custom,
            ];
            $tradeResult = $this->tradeModel->createTrade($data);
            $server_id = $config[$channel]['type'][intval($tradeResult['amount'])];


            // PayTime
            $options = $this->getConfigOptions();
            $payTime = new PayTime('Mycard_telecom');
            $payTime->setOptions($options);
            $payTime->purchase([
                'transactionId' => $tradeResult['transaction'],
                'amount'        => $tradeResult['amount'],
                'currency'      => $tradeResult['currency'],
                'productId'     => $tradeResult['product_id'],
                'productDesc'   => $tradeResult['product_id'],
                'userId'        => $user_id,
                'custom'        => $server_id,
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


        // 子网关
        if ($channel) {
            try {
                $allow_products = $config[$channel]['type'];
            } catch (Exception $e) {
                Utils::tips('warn', 'no config');
            }

            $products = $this->tradeModel->getProducts($app_id, 'mycard');
            if (!$products) {
                Utils::tips('warn', 'no product');
            }

            foreach ($products as $key => $value) {
                if (!array_key_exists(intval($value['price']), $allow_products)) {
                    unset($products[$key]);
                }
            }
            if (!$products) {
                Utils::tips('warn', 'no product');
            }
            $this->view->products = $products;
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