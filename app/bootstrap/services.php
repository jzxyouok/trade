<?php

use Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Mvc\View\Engine\Volt as VoltEngine,
    Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter,
    Phalcon\Http\Response\Cookies,
    Phalcon\Events\Manager as EventsManager,
    Phalcon\Crypt,
    Phalcon\Logger\Adapter\File as FileLogger,
    Phalcon\Cache\Frontend\Data as FrontData,
    Phalcon\Cache\Backend\File as BackFile,
    Phalcon\Cache\Backend\Redis as BackRedis,
    MyApp\Plugins\SecurityPlugin;


//$di = new Phalcon\Di();
$di = new FactoryDefault();


$di->set('config', function () use ($config) {
    return $config;
}, true);


$di->set('router', function () {
    return require __DIR__ . '/routes.php';
}, true);


$di->set('crypt', function () use ($di) {
    $crypt = new Crypt();
    $crypt->setKey($di['config']->setting->cryptKey);
    return $crypt;
}, true);


$di->set('url', function () use ($di) {
    $url = new UrlResolver();
    $url->setBaseUri('/api/');
    return $url;
}, true);


$di->set('view', function () use ($di) {
    $view = new View();
    $view->setViewsDir(APP_DIR . '/views/');
    $view->registerEngines(array(
        '.html'  => function ($view, $di) {
            $volt = new VoltEngine($view, $di);
            $volt->setOptions(array(
                'compiledPath'      => APP_DIR . '/cache/',
                'compiledSeparator' => '_'
            ));
            return $volt;
        },
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
    ));
    return $view;
}, true);


$di->set('modelsMetadata', function () {
    return new MetaDataAdapter();
}, true);


// link https://docs.phalconphp.com/zh/latest/reference/cache.html
$di->set('modelsCache', function () use ($di) {
    $frontCache = new FrontData(array("lifetime" => $di['config']->setting->cacheTime));
    // Redis Cache
    if (isset($di['config']->redis)) {
        $cache = new BackRedis($frontCache, array(
            'prefix' => $di['config']->redis->prefix,
            'host'   => $di['config']->redis->host,
            'port'   => $di['config']->redis->port,
            'index'  => $di['config']->redis->index
        ));
        return $cache;
    }
    // File Cache
    $cache = new BackFile($frontCache,
        array('prefix' => 'cache_', 'cacheDir' => APP_DIR . '/cache/'));
    return $cache;
}, true);


$di->set('session', function () {
    $session = new SessionAdapter();
    $session->start();
    return $session;
}, true);


// Dispatcher
$di->set('dispatcher', function () use ($di) {
    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('MyApp\Controllers');
    if ($di['config']->setting->securityPlugin) {
        $di['eventsManager']->attach('dispatch', new SecurityPlugin);
        $dispatcher->setEventsManager($di['eventsManager']);
    }
    return $dispatcher;
}, true);


// Database connection
$di->set('dbData', function () use ($di) {
    $connection = new DbAdapter(array(
        'host'     => $di['config']->db_data->host,
        'username' => $di['config']->db_data->username,
        'password' => $di['config']->db_data->password,
        'dbname'   => $di['config']->db_data->dbname,
        'charset'  => $di['config']->db_data->charset
    ));
    $connection->setEventsManager($di['eventsManager']);
    return $connection;
}, true);


$di->set('dbBackend', function () use ($di) {
    $connection = new DbAdapter(array(
        'host'     => $di['config']->db_backend->host,
        'username' => $di['config']->db_backend->username,
        'password' => $di['config']->db_backend->password,
        'dbname'   => $di['config']->db_backend->dbname,
        'charset'  => $di['config']->db_backend->charset
    ));
    $connection->setEventsManager($di['eventsManager']);
    return $connection;
}, true);


$di->set('dbLog', function () use ($di) {
    $connection = new DbAdapter(array(
        'host'     => $di['config']->db_log->host,
        'username' => $di['config']->db_log->username,
        'password' => $di['config']->db_log->password,
        'dbname'   => $di['config']->db_log->dbname,
        'charset'  => $di['config']->db_log->charset
    ));
    $connection->setEventsManager($di['eventsManager']);
    return $connection;
}, true);


// Database Event
// https://docs.phalconphp.com/zh/latest/reference/dispatching.html#dispatch-loop-events
$di['eventsManager']->attach('db', function ($event, $connection) use ($di) {
    if ($event->getType() == 'beforeQuery') {
        if ($di['config']->setting->recordSql) {
            $logger = new FileLogger(APP_DIR . '/logs/' . "logsSql.log");
            $logger->log($connection->getSQLStatement());
        }
        if (preg_match('/drop|alter/i', $connection->getSQLStatement())) {
            return false;
        }
    }
});