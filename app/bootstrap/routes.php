<?php
/**
 * Package  routes.php
 * Author:  joe@xxtime.com
 * Date:    2015-07-20
 * Time:    下午10:32
 * Link:    http://www.xxtime.com
 * link:    http://docs.phalconphp.com/zh/latest/reference/routing.html
 */
use Phalcon\Mvc\Router;


$router = new Router(false);
$router->removeExtraSlashes(true);


// 通用路由
$router->add(
    '/:controller/:action/:params',
    array(
        'namespace'  => 'MyApp\Controllers',
        'controller' => 1,
        'action'     => 2,
        'params'     => 3
    )
);

$router->add(
    '/:controller',
    array(
        'namespace'  => 'MyApp\Controllers',
        'controller' => 1
    )
);


// 多应用路由    Matches "/123/news/show/1"
$router->add(
    "/([0-9]+)/:controller/:action/:params",
    array(
        'namespace'  => 'MyApp\Controllers',
        'app'        => 1,
        'controller' => 2,
        'action'     => 3,
        'params'     => 4
    )
);
$router->add(
    "/([0-9]+)/:controller",
    array(
        'namespace'  => 'MyApp\Controllers',
        'app'        => 1,
        'controller' => 2
    )
);


// 接口路由
$router->add(
    '/api/:controller/:action/:params',
    array(
        'namespace'  => 'MyApp\Controllers\Api',
        'controller' => 1,
        'action'     => 2,
        'params'     => 3
    )
);

$router->add(
    '/api/:controller',
    array(
        'namespace'  => 'MyApp\Controllers\Api',
        'controller' => 1
    )
);

$router->add(
    '/api',
    array(
        'namespace'  => 'MyApp\Controllers\Api',
        'controller' => 'Index'
    )
);


// 后台路由
$router->add(
    '/admin/:controller/:action/:params',
    array(
        'namespace'  => 'MyApp\Controllers\Admin',
        'controller' => 1,
        'action'     => 2,
        'params'     => 3
    )
);

$router->add(
    '/admin/:controller',
    array(
        'namespace'  => 'MyApp\Controllers\Admin',
        'controller' => 1
    )
);

$router->add(
    '/admin',
    array(
        'namespace'  => 'MyApp\Controllers\Admin',
        'controller' => 'Index'
    )
);


// 公用方法路由
$router->add(
    "/login",
    array(
        'controller' => 'public',
        'action'     => 'login'
    )
);

$router->add(
    "/logout",
    array(
        'controller' => 'public',
        'action'     => 'logout'
    )
);


// 文章
$router->add(
    "/article/([0-9]+)(|\.(html|htm))",
    array(
        'namespace'  => 'MyApp\Controllers',
        'controller' => 'article',
        'action'     => 'view',
        'id'         => 1
    )
);


// 标签
$router->add(
    "/tag/:params",
    array(
        'namespace'  => 'MyApp\Controllers',
        'controller' => 'tag',
        'action'     => 'view',
        'param'      => 1
    )
);


// 业务路由
$router->add(
    "/order/make",
    array(
        'namespace' => 'MyApp\Controllers',
        'controller' => 'payment',
        'action' => 'make'
    )
);

$router->add(
    "/notice/:params",
    array(
        'namespace' => 'MyApp\Controllers',
        'controller' => 'payment',
        'action' => 'notice',
        'param' => 1
    )
);


return $router;