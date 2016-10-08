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
        $this->_app = $this->dispatcher->getParam("app");


        // 设置时区
        ini_set("date.timezone", $this->config->setting->timezone);


        // 日志记录
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

    }


    public function afterExecuteRoute(Dispatcher $dispatcher)
    {
    }

}
