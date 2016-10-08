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

    private $dbConnection;


    public function initialize()
    {
        $this->setConnectionService('dbBackend');
        // $this->dbConnection = DI::getDefault()->get('dbBackend');
    }


    /**
     * 获取用户信息
     * @param string $username
     * @return mixed
     */
    public function getUser($username = '')
    {
        if (intval($username) > 0) {
            $sql = "SELECT * FROM `users` WHERE id=:username";
        } else {
            $sql = "SELECT * FROM `users` WHERE username=:username";
        }
        $bind = array('username' => $username);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        return $data;
    }


    /**
     * 用户信息 根据Ticket返回
     * @param string $ticket
     * @return mixed
     */
    public function getUserByTicket($ticket = '')
    {
        $dateTime = date('Y-m-d H:i:s', time() - 60);
        $sql = "SELECT u.* FROM `users` u, `tickets` t WHERE u.id=t.userID AND t.ticket=:ticket AND t.createTime>'$dateTime'";
        $bind = array('ticket' => $ticket);
        // TODO :: 此处如使用$this->dbConnection时,外部程序使用file_get_contents(VerifyURL)调用时报错,直接访问VerifyURL没问题
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetch();
        return $data;
    }


    /**
     * 插入登录日志
     * @param array $data
     */
    public function logsLogin($data = [])
    {
        $data['createTime'] = date('Y-m-d H:i:s');
        DI::getDefault()->get('dbBackend')->insertAsDict("logsLogin", $data);
    }


    /**
     * 设置二次验证secretKey
     * @param int $userID
     * @param string $secretKey
     * @return mixed
     */
    public function setOTPKey($userID = 0, $secretKey = '')
    {
        $sql = "UPDATE `users` SET `secretKey`=:secretKey WHERE id=:id AND `secretKey`=''";
        $bind = array('id' => $userID, 'secretKey' => $secretKey);
        return DI::getDefault()->get('dbBackend')->execute($sql, $bind);
    }


    /**
     * 检查登录失败次数
     * @param string $IP
     * @return bool
     */
    public function checkLoginTimes($IP = '')
    {
        $dateTime = date('Y-m-d H:i:s', time() - 600);
        $sql = "SELECT COUNT(1) count FROM `logsLogin` WHERE IP=:IP AND result=0 AND createTime>'$dateTime'";
        $bind = array('IP' => $IP);
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
        if (!$userData['phone']) {
            return false;
        }

        // 是否配置SMS接口
        $config = DI::getDefault()->get('config');
        if (!$config->sms->appID) {
            return false;
        }

        $dateTime = date('Y-m-d H:i:s', time() - 86400 * 90);
        $sql = "SELECT t.location, COUNT(1) times
              FROM(SELECT location FROM `logsLogin` WHERE userID=:userID AND location IS NOT null AND result=1 AND createTime>'$dateTime' ORDER BY id DESC LIMIT 300) t
              GROUP BY t.location
              ORDER BY times DESC";
        $bind = array('userID' => $userData['id']);
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
            'time' => (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('H点i分'),
            'location' => $local
        ];
        include BASE_DIR . $config->application->pluginsDir . 'alidayu/TopSdk.php';
        $c = new \TopClient;
        $c->appkey = $config->sms->appID;
        $c->secretKey = $config->sms->appKey;
        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        $req->setSmsType("normal");
        $req->setSmsFreeSignName($config->sms->signName);
        $req->setSmsParam(json_encode($params));
        $req->setRecNum($userData['phone']);
        $req->setSmsTemplateCode($config->sms->tempSafety);
        $c->execute($req);
    }


    /**
     * 生成票据Ticket
     * @param int $userID
     * @return mixed
     */
    public function  createTicket($userID = 0)
    {
        $random = new Random();
        $ticket = $random->base64Safe(64);
        $data = [
            'userID' => $userID,
            'ticket' => $ticket,
            'createTime' => date('Y-m-d H:i:s')
        ];
        DI::getDefault()->get('dbBackend')->insertAsDict("tickets", $data);
        return $ticket;
    }


    /**
     * 获取角色ID
     * @param int $userID
     * @return array
     */
    public function getRoleID($userID = 0)
    {
        $sql = "SELECT `roleID` FROM `userRole` WHERE userID=:userID";
        $bind = array('userID' => $userID);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        $data = $query->fetchAll();
        if (!$data) {
            return [];
        }
        return array_column($data, 'roleID');
    }


    /**
     * 获取私有资源
     * @param int $userID
     * @param string $app
     * @return array
     */
    public function getResources($userID = 0, $app = '')
    {
        // 超级管理员
        if ($userID == 10000) {
            $sql = "SELECT res.id, res.name, res.resource, res.type, res.parent, res.icon
                FROM `resources` res
                WHERE res.status=1 AND res.app=:app
                ORDER BY res.sort DESC";
            $bind = array('app' => $app);
            $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
            $query->setFetchMode(Db::FETCH_ASSOC);
            return $query->fetchAll();
        }


        $roleID = $this->getRoleID($userID);
        if (!$roleID) {
            return [];
        }
        $roleID = '"' . implode('","', $roleID) . '"';
        $sql = "SELECT res.id, res.name, res.resource, res.type, res.parent, res.icon
                FROM `resources` res, `roleResource` rel
                WHERE rel.resourceID=res.id AND res.status=1 AND rel.roleID IN ($roleID) AND res.app=:app
                ORDER BY res.sort DESC";
        $bind = array('app' => $app);
        $query = DI::getDefault()->get('dbBackend')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetchAll();
    }


    /**
     * 获取私有资源acl格式
     * @param int $userID
     * @param string $app
     * @return array
     */
    public function getAclResource($userID = 0, $app = '')
    {
        $data = $this->getResources($userID, $app);
        if (!$data) {
            return [];
        }
        $result = [];
        foreach ($data as $value) {
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

}
