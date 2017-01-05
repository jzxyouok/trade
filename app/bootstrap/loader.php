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
$configFile = APP_DIR . "/config/system";
if (file_exists($configFile . '.yml')) {
    $config = new Config(Yaml::parse(file_get_contents($configFile . '.yml')));
} else {
    $config = new Ini($configFile . '.ini');
}


// loader
$loader = new Loader();
$loader->registerNamespaces(array(
    'MyApp\Controllers\Api'   => BASE_DIR . $config->application->controllersDir . 'api/',
    'MyApp\Controllers\Admin' => BASE_DIR . $config->application->controllersDir . 'admin/',
    'MyApp\Controllers'       => BASE_DIR . $config->application->controllersDir,
    'MyApp\Models'            => BASE_DIR . $config->application->modelsDir,
    'MyApp\Services'          => BASE_DIR . $config->application->servicesDir,
    'MyApp\Plugins'           => BASE_DIR . $config->application->pluginsDir,
    'MyApp\Libraries'         => BASE_DIR . $config->application->librariesDir,
))->register();


// load common files
include_once BASE_DIR . $config->application->pluginsDir . 'Common.php';
if ($config->setting->sandbox == true) {
    include BASE_DIR . $config->application->pluginsDir . 'Exception.php';
}


// sandbox
switch ($config->setting->sandbox) {
    case true:
        error_reporting(E_ALL); //error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        break;
    default:
        header_remove('X-Powered-By');
        error_reporting(0);
}