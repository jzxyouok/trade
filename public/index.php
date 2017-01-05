<?php

/**
 * Link: https://github.com/phalcon/mvc
 */

define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/app');

use Phalcon\Mvc\Application;

include APP_DIR . "/bootstrap/loader.php";

include APP_DIR . "/bootstrap/services.php";

$application = new Application($di);

echo $application->handle()->getContent();