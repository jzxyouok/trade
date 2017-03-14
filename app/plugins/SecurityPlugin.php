<?php

namespace MyApp\Plugins;


use Phalcon\Mvc\User\Plugin;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Acl;
use Phalcon\Acl\Role;
use Phalcon\Acl\Resource;
use Phalcon\Acl\Adapter\Memory as AclList;
use Phalcon\Events\Event;
use MyApp\Models\Auth;

// https://docs.phalconphp.com/zh/latest/reference/dispatching.html
// https://docs.phalconphp.com/zh/latest/api/Phalcon_Mvc_Dispatcher.html
class SecurityPlugin extends Plugin
{

    public function beforeDispatch(Event $event, Dispatcher $dispatcher)
    {
        return $this->checkPermission($event, $dispatcher);
    }


    public function beforeException(Event $event, Dispatcher $dispatcher)
    {
        if (!$this->config->setting->sandbox) {
            $dispatcher->forward([
                'namespace'  => 'MyApp\Controllers',
                'controller' => 'public',
                'action'     => 'show404'
            ]);
            return false;
        }
    }


    private function getAcl($dispatcher)
    {
        if (isset($this->persistent->acl)) {
            return $this->persistent->acl;
        }

        // APP
        $app = $dispatcher->getParam("app");
        $app = $app ? $app : '';


        // ACL
        $acl = new AclList();
        $acl->setDefaultAction(Acl::DENY);


        // 用户ID
        $user_id = $this->session->get('user_id');


        // 定义角色
        $roleList = array(
            new Role('Guests', 'Guest Users'),
            new Role('Users', 'Member Users'),
            new Role('Admins', 'Admin Users')
        );


        // 资源
        $publicResources = array(
            'index'   => array('index'),
            'about'   => array('index'),
            'contact' => array('index'),
            'public'  => array('login', 'logout')
        );


        // 资源
        if ($this->config->setting->security_plugin == 1) {
            // 使用自己的权限控制
            $authModel = new Auth();
            $privateResources = $authModel->getAclFormat($authModel->getResources($user_id, $app));
            $allResources = $authModel->getAclFormat($authModel->getResources(1000, $app));
        } else {
            // 使用资源中心的权限控制
            $resources = $this->session->get('resources');
            if (!$resources) {
                header('Location:/login');
                exit();
            }
            $privateResources = $resources['acl_allow'];
            $allResources = $resources['acl_all'];
        }


        // 添加角色
        foreach ($roleList as $role) {
            $acl->addRole($role);
        }
        // 添加资源
        foreach ($publicResources as $resource => $actions) {
            $acl->addResource(new Resource($resource), $actions);
        }
        foreach ($allResources as $resource => $actions) {
            $acl->addResource(new Resource($resource), $actions);
        }


        // 公共权限 Guests
        foreach ($roleList as $role) {
            foreach ($publicResources as $resource => $actions) {
                foreach ($actions as $action) {
                    $acl->allow($role->getName(), $resource, $action);
                }
            }
        }


        // 私有权限 Users
        foreach ($privateResources as $resource => $actions) {
            foreach ($actions as $action) {
                $acl->allow('Users', $resource, $action);
            }
        }


        // 管理员 Admins
        foreach ($allResources as $resource => $actions) {
            foreach ($actions as $action) {
                $acl->allow('Admins', $resource, $action);
            }
        }


        // 此处缓存到SESSION
        $this->persistent->acl = $acl;


        return $this->persistent->acl;
    }


    private function checkPermission(Event $event, Dispatcher $dispatcher)
    {
        $namespaceName = $dispatcher->getNamespaceName();
        if ($namespaceName != 'MyApp\Controllers') {
            $prefix = strtolower(substr($namespaceName, strrpos($namespaceName, '\\') + 1));
            $controller = $prefix . '/' . $dispatcher->getControllerName();
        } else {
            $controller = $dispatcher->getControllerName();
        }
        $action = $dispatcher->getActionName();

        // 不检查public 与 api/sso控制器, 防止forward后二次检查
        if (in_array($controller, ['public', 'api/sso'])) {
            return true;
        }


        $acl = $this->getAcl($dispatcher);
        $user_id = $this->session->get('user_id');
        if (!isset($user_id)) {
            $role = 'Guests';
        } else {
            $role = 'Users';
        }


        // 无权限
        if ($acl->isResource($controller) != $acl->isAllowed($role, $controller, $action)) {
            $dispatcher->forward([
                'namespace'  => 'MyApp\Controllers',
                'controller' => 'public',
                'action'     => 'show401'
            ]);
            return false;
        }
    }

}