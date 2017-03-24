<?php


namespace MyApp\Controllers;


use Phalcon\Mvc\Dispatcher;

class ReturnController extends ControllerBase
{

    // 默认返回页面 TODO :: 检查交易结果
    public function indexAction()
    {
        $this->view->tips = [
            'type'     => 'success',
            'msg'      => _('success'),
            'message'  => '',
            'seconds'  => 3600,
            'redirect' => '/'
        ];
        $this->view->pick("return/message");
    }


    // 成功返回页面
    public function successAction()
    {
        $this->view->tips = [
            'type'     => 'success',
            'msg'      => _('success'),
            'message'  => '',
            'seconds'  => 3600,
            'redirect' => '/'
        ];
        $this->view->pick("return/message");
    }


    // 取消返回页面
    public function cancelAction()
    {
        $this->view->tips = [
            'type'     => 'warn',
            'msg'      => _('uncompleted'),
            'message'  => '',
            'seconds'  => 3600,
            'redirect' => '/'
        ];
        $this->view->pick("return/message");
    }

}