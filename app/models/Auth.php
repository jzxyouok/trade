<?php

namespace MyApp\Models;


use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;
use Phalcon\Security\Random;

/**
 * AuthController
 *
 * 本类仅用作管理后台调用,包含两部分
 * 1. 控制后台的RBAC权限控制
 * 2. 控制后台的SSO单点登录
 *
 * 勿将本类应用于用户前端业务
 * 前端用户数据交互可使用Users模型
 */
class Auth extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbBackend');
    }


    /**
     * 生成票据Ticket
     * @param int $user_id
     * @return mixed
     */
    public function  createTicket($user_id = 0)
    {
        $random = new Random();
        $ticket = $random->base64Safe(64);
        $data = [
            'user_id'     => $user_id,
            'ticket'      => $ticket,
            'create_time' => date('Y-m-d H:i:s')
        ];
        DI::getDefault()->get('dbBackend')->insertAsDict("tickets", $data);
        return $ticket;
    }


    /**
     * 用户信息 根据Ticket返回
     * @param string $ticket
     * @return mixed
     */
    public function getUserByTicket($ticket = '')
    {
        $dateTime = date('Y-m-d H:i:s', time() - 300);
        $sql = "SELECT u.* FROM `users` u, `tickets` t WHERE u.id=t.user_id AND t.ticket=:ticket AND t.create_time>'$dateTime'";
        $bind = array('ticket' => $ticket);
        // TODO :: 此处如使用$this->dbConnection时,外部程序使用file_get_contents(VerifyURL)调用时报错,直接访问VerifyURL没问题
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        return $data;
    }


    /**
     * 获取用户信息
     * @param string $username
     * @return mixed
     */
    public function getUser($username = '')
    {
        if (intval($username) > 0) {
            $sql = "SELECT id,username,password,name,status,mobile,secret_key,avatar,create_time,update_time FROM `users` WHERE id=:username";
        } else {
            $sql = "SELECT id,username,password,name,status,mobile,secret_key,avatar,create_time,update_time FROM `users` WHERE username=:username";
        }
        $bind = array('username' => $username);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        return $data;
    }


    /**
     * 获取角色ID
     * @param int $user_id
     * @return array
     */
    public function getRoleID($user_id = 0)
    {
        $sql = "SELECT `role_id` FROM `role_user` WHERE user_id=:user_id";
        $bind = array('user_id' => $user_id);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetchAll();
        if (!$data) {
            return [];
        }
        return array_column($data, 'role_id');
    }


    /**
     * 获取私有资源
     * @param int $user_id
     * @param string $app
     * @return array
     */
    public function getResources($user_id = 0, $app = '')
    {
        // 超级管理员
        if ($user_id == 1000) {
            $sql = "SELECT res.id, res.name, res.resource, res.type, res.parent, res.icon
                FROM `resources` res
                WHERE res.status=1 AND res.app=:app
                ORDER BY res.sort DESC";
            $bind = array('app' => $app);
            $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
            $query->setFetchMode(Db::FETCH_ASSOC);
            return $query->fetchAll();
        }


        $role_id = $this->getRoleID($user_id);
        if (!$role_id) {
            return [];
        }
        $role_id = '"' . implode('","', $role_id) . '"';
        $sql = "SELECT res.id, res.name, res.resource, res.type, res.parent, res.icon
                FROM `resources` res, `role_resource` rel
                WHERE rel.resource_id=res.id AND res.status=1 AND rel.role_id IN ($role_id) AND res.app=:app
                ORDER BY res.sort DESC";
        $bind = array('app' => $app);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetchAll();
    }


    /**
     * 资源格式化为ACL格式
     * @param array $resource
     * @return array
     */
    public function getAclFormat($resource = [])
    {
        $result = [];
        foreach ($resource as $value) {
            $resource = explode('/', trim($value['resource'], '/'));
            if (in_array($resource['0'], array('api', 'admin'))) {
                $controller = "{$resource['0']}/{$resource['1']}";
                $action = isset($resource['2']) ? $resource['2'] : 'index';
            } else {
                $controller = $resource['0'];
                $action = isset($resource['1']) ? $resource['1'] : 'index';
            }
            $result[$controller][] = $action;
        }
        return $result;
    }


    /**
     * 设置二次验证secret_key
     * @param int $user_id
     * @param string $secret_key
     * @return mixed
     */
    public function setOTPKey($user_id = 0, $secret_key = '')
    {
        $sql = "UPDATE `users` SET `secret_key`=:secret_key WHERE id=:id AND `secret_key`=''";
        $bind = array('id' => $user_id, 'secret_key' => $secret_key);
        return DI::getDefault()->get('dbBackend')->execute($sql, $bind);
    }


    /**
     * 登录日志
     * @param array $data
     */
    public function loginLog($data = [])
    {
        $data['create_time'] = date('Y-m-d H:i:s');
        DI::getDefault()->get('dbBackend')->insertAsDict("logs_login", $data);
    }


    /**
     * 检查登录失败次数
     * @param string $ip
     * @return bool
     */
    public function checkLoginTimes($ip = '')
    {
        $dateTime = date('Y-m-d H:i:s', time() - 600);
        $sql = "SELECT COUNT(1) count FROM `logs_login` WHERE ip=:ip AND result=0 AND create_time>'$dateTime'";
        $bind = array('ip' => $ip);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        if ($data['count'] < 5) {
            return true;
        }
        return false;
    }


    /**
     * 安全检查
     * @param array $userData
     * @param string $location
     * @return bool
     */
    public function securityCheck($userData = [], $location = '')
    {
        if (!$location) {
            return false;
        }
        if (!$userData['mobile']) {
            return false;
        }

        // 是否配置SMS接口
        $config = DI::getDefault()->get('config');
        if (!$config->sms->appID) {
            return false;
        }

        $dateTime = date('Y-m-d H:i:s', time() - 86400 * 90);
        $sql = "SELECT t.location, COUNT(1) times
              FROM(SELECT location FROM `logs_login` WHERE user_id=:user_id AND location IS NOT null AND result=1 AND create_time>'$dateTime' ORDER BY id DESC LIMIT 300) t
              GROUP BY t.location
              ORDER BY times DESC";
        $bind = array('user_id' => $userData['id']);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetchAll();
        if (count($data) <= 1) {
            return true;
        }
        if ($data['0']['location'] == $location) {
            return true;
        }

        if (($data['1']['location'] == $location) && ($data['1']['times'] > 2)) {
            return true;
        }

        // 短信通知
        $location = explode(' ', $location);
        $local = !empty($location['1']) ? $location['1'] : '';
        $local .= !empty($location['2']) ? $location['2'] : '';
        $params = [
            'time'     => (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('H点i分'),
            'location' => $local
        ];
        include APP_DIR . '/plugins/aliyun/alidayu/TopSdk.php';
        $c = new \TopClient;
        $c->appkey = $config->sms->app_id;
        $c->secretKey = $config->sms->app_key;
        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        $req->setSmsType("normal");
        $req->setSmsFreeSignName($config->sms->sign);
        $req->setSmsParam(json_encode($params));
        $req->setRecNum($userData['mobile']);
        $req->setSmsTemplateCode($config->sms->tmp_safety);
        $c->execute($req);
    }

}