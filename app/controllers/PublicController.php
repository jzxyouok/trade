<?php


namespace MyApp\Controllers;

use MyApp\Models\Utils;
use Phalcon\Mvc\Controller;


class PublicController extends Controller
{

    public function indexAction()
    {
    }


    // 管理后台的登录处理
    public function loginAction()
    {
        $ticket = $this->request->get('ticket', 'string');
        if (!$ticket) {
            // TODO :: https 协议
            $callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $LoginUrl = $this->config->sso->BaseUrl . '?redirect=' . urlencode($callback);
            header('Location:' . $LoginUrl);
            exit();
        }

        // 验证ticket
        $verifyUrl = $this->config->sso->BaseUrl . '/verify?ticket=' . $ticket;
        $result = file_get_contents($verifyUrl);
        $result = json_decode($result, true);

        if ($result['code'] != 0) {
            Utils::tips('warning', 'Login Failed');
        }


        // TODO::拿Ticket换取资源 增加APPKEY
        $resourceUrl = $this->config->sso->BaseUrl . '/resources?app=' . $this->config->sso->appId . '&ticket=' . $ticket;
        $resources = json_decode(file_get_contents($resourceUrl), true);
        if ($resources['code'] != 0) {
            Utils::tips('warning', 'Error When Get Resources');
        } else {
            $this->session->set('resources', $resources['data']);
        }


        // 设置SESSION
        $this->session->set('user_id', $result['data']['user_id']);
        $this->session->set('username', $result['data']['username']);
        $this->session->set('name', $result['data']['name']);


        header('Location:/');
        exit();
    }


    public function logoutAction()
    {
        $this->session->destroy();
        Utils::tips('info', 'Logout Page');
    }


    public function tipsAction()
    {
        $type = $this->request->get('type', 'string');
        $flashData = json_decode(trim($this->cookies->get('flash')->getValue()), true);
        $this->view->tips = $flashData;
        if ($type == 'ajax') {
            $this->view->pick("public/tipsAjax");
        } else {
            $this->view->pick("public/tips");
        }
    }


    public function show401Action()
    {
        $this->view->message = 'Error 401, No Permission';
        $this->view->pick("public/errors");
    }


    public function show404Action()
    {
        $this->view->message = 'Error 404, Not Found';
        $this->view->pick("public/errors");
    }


    public function exceptionAction()
    {
        $this->view->message = 'Error 400, Exception Occurs';
        $this->view->pick("public/errors");
    }


}
