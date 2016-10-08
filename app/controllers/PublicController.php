<?php


namespace MyApp\Controllers;

use MyApp\Models\Utils;
use Phalcon\Mvc\Controller;


class PublicController extends Controller
{

    public function indexAction()
    {
    }


    public function loginAction()
    {
        // 管理后台的登录处理
        $ticket = $this->request->get('ticket', 'string');
        if (!$ticket) {
            $callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $LoginURL = $this->config->sso->BaseURL . '?redirect=' . urlencode($callback);
            header('Location:' . $LoginURL);
            exit();
        }

        // 验证ticket
        $verifyURL = $this->config->sso->BaseURL . '/verify?ticket=' . $ticket;
        $result = file_get_contents($verifyURL);
        $result = json_decode($result, true);

        if ($result['code'] != 0) {
            Utils::tips('warning', 'Login Failed');
        }


        // TODO::拿Ticket换取资源 增加APPKEY
        $resourceURL = $this->config->sso->BaseURL . '/resources?app=' . $this->config->sso->APPID . '&ticket=' . $ticket;
        $resources = json_decode(file_get_contents($resourceURL), true);
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
