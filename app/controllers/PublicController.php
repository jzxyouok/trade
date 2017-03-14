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

        // BASE URL
        if ($this->config->setting->security_plugin == 1) {
            $base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/sso';
        } else {
            $base_url = $this->config->sso->base_url;
        }

        if (!$ticket) {
            // TODO :: https 协议
            $callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $login_url = $base_url . '?redirect=' . urlencode($callback);
            header('Location:' . $login_url);
            exit();
        }

        // 验证ticket
        $verify_url = $base_url . '/verify/' . $ticket;
        $result = file_get_contents($verify_url);
        $result = json_decode($result, true);

        if ($result['code'] != 0) {
            Utils::tips('warning', 'Login Failed');
        }


        // TODO::拿Ticket换取资源 增加APPKEY
        $resource_url = $base_url . '/resources?app=' . $this->config->setting->app_id . '&ticket=' . $ticket;
        $resources = json_decode(file_get_contents($resource_url), true);
        if ($resources['code'] != 0) {
            Utils::tips('warning', 'Error When Get Resources');
        } else {
            unset($resources['code'], $resources['msg']);
            $this->session->set('resources', $resources);
        }


        // 设置SESSION
        $this->session->set('user_id', $result['user_id']);
        $this->session->set('username', $result['username']);
        $this->session->set('name', $result['name']);
        $this->session->set('avatar', $result['avatar']);


        header('Location:/');
        exit();
    }


    public function logoutAction()
    {
        $this->persistent->destroy();
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