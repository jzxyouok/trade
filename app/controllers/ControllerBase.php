<?php


namespace MyApp\Controllers;

use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Logger\Adapter\File as FileLogger;
use Phalcon\Logger;


class ControllerBase extends Controller
{

    public $_app;
    public $_user_id;


    public function beforeExecuteRoute(Dispatcher $dispatcher)
    {
    }


    public function initialize()
    {

        // set appId
        $this->_app = $this->dispatcher->getParam("app");


        // set userId
        $this->_user_id = $this->session->get('user_id');


        // set timezone
        ini_set("date.timezone", $this->config->setting->timezone);


        // record request
        if ($this->config->setting->recordRequest) {
            if (isset($_REQUEST['_url'])) {
                $_url = $_REQUEST['_url'];
                unset($_REQUEST['_url']);
            } else {
                $_url = '/';
            }
            $log = empty($_REQUEST) ? $_url : ($_url . '?' . urldecode(http_build_query($_REQUEST)));
            $logger = new FileLogger(BASE_DIR . $this->config->application->logsDir . date("Ym") . '.log');
            $logger->log($log, Logger::INFO);
        }


        // check auth
        if ($this->config->setting->securityPlugin) {
            if (!$this->_user_id || !$this->session->get('isLogin')) {
                header('Location:/login');
                exit;
            }
        }

    }


    public function afterExecuteRoute(Dispatcher $dispatcher)
    {
    }

}
