<?php


namespace MyApp\Controllers;


use Phalcon\Mvc\Controller;

class PublicController extends Controller
{

    public function indexAction()
    {
    }


    public function loginAction()
    {
    }


    public function logoutAction()
    {
    }


    public function tipsAction()
    {
        $flashData = json_decode(trim($this->cookies->get('flash')->getValue()), true);
        $this->view->tips = $flashData;
        $this->view->pick("public/tipsMobile");
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