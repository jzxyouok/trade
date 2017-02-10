<?php


use Phalcon\Loader;
use Phalcon\Config;
use Phalcon\Config\Adapter\Ini;
use Symfony\Component\Yaml\Yaml;


// load composer
if (!file_exists(BASE_DIR . '/vendor/autoload.php')) {
    die('The project needs Composer, please check vendor directory');
}
include_once BASE_DIR . '/vendor/autoload.php';


// load config file
$configFile = APP_DIR . "/config/app";
if (file_exists($configFile . '.yml')) {
    $config = new Config(Yaml::parse(file_get_contents($configFile . '.yml')));
} else {
    $config = new Ini($configFile . '.ini');
}


// loader
$loader = new Loader();
$loader->registerNamespaces(array(
    'MyApp\Controllers\Api'   => APP_DIR . '/controllers/api/',
    'MyApp\Controllers\Admin' => APP_DIR . '/controllers/admin/',
    'MyApp\Controllers'       => APP_DIR . '/controllers/',
    'MyApp\Models'            => APP_DIR . '/models/',
    'MyApp\Services'          => APP_DIR . '/services/',
    'MyApp\Plugins'           => APP_DIR . '/plugins/',
    'MyApp\Libraries'         => APP_DIR . '/libraries/',
))->register();


// load common files
include APP_DIR . '/plugins/' . 'Common.php';


// sandbox
switch ($config->setting->sandbox) {
    case true:
        include APP_DIR . '/plugins/' . 'Exception.php';
        error_reporting(E_ALL);
        break;
    default:
        header_remove('X-Powered-By');
        error_reporting(0);
}