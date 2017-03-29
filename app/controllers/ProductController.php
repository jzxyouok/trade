<?php


/**
 * 产品接口
 */
namespace MyApp\Controllers;


use MyApp\Models\Product;
use Phalcon\Mvc\Dispatcher;

class ProductController extends ControllerBase
{

    private $productModel;


    public function initialize()
    {
        $this->productModel = new Product();
    }


    public function indexAction()
    {
        $app_id = $this->request->get('app_id', 'alphanum');
        $gateway = $this->request->get('gateway', 'alphanum');
        $data = $this->productModel->getList($app_id, $gateway);
        if (!$data) {
            $this->response->setJsonContent(['code' => 1, 'msg' => _('no products')])->send();
            exit();
        }
        $this->response->setJsonContent(
            [
                'code'    => 0,
                'msg'     => _('success'),
                'content' => $data
            ],
            JSON_UNESCAPED_UNICODE
        )->send();
        exit();
    }

}