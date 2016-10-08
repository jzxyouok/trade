<?php

namespace MyApp\Controllers\Api;

use MyApp\Models\Auth;
use MyApp\Models\Utils;
use Endroid\QrCode\QrCode;
use PHPGangsta_GoogleAuthenticator;

class SsoController extends ControllerBase
{

    private $authModel;
    private $utilsModel;


    public function initialize()
    {
        $this->authModel = new Auth();
        $this->utilsModel = new Utils();
    }


    public function indexAction()
    {
        $this->loginAction();
    }


    public function loginAction()
    {
        //$password = password_hash($password, PASSWORD_DEFAULT);
        if ($_POST) {
            $username = trim($this->request->getPost('username', 'email'));
            $password = trim($this->request->getPost('password', 'string'));
            $captcha = $this->request->getPost('captcha', 'alphanum');
            $ipAddress = $this->request->getClientAddress();
            $user_agent = $this->request->getUserAgent();
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';


            // 安全检查 TODO::验证码
            if (!$this->authModel->checkLoginTimes($ipAddress)) {
                Utils::tips('warning', 'Login Failed Too Much Time');
            }


            // 查询用户
            $userData = $this->authModel->getUser($username);
            if (!$userData) {
                Utils::tips('warning', 'User Is Not Exist');
            }


            // 验证密码
            $verifyResult = password_verify($password, $userData['password']);

            // 登录日志
            $location = $this->utilsModel->getLocation($ipAddress);
            $log = array(
                'user_id' => $userData['id'],
                'ip' => $ipAddress,
                'location' => $location,
                'user_agent' => $user_agent,
                'referer' => $referer,
                'result' => $verifyResult ? 1 : 0,
            );
            $this->authModel->logsLogin($log);

            if (!$verifyResult) {
                Utils::tips('warning', 'Password Error');
            }

            if ($userData['status'] != 1) {
                Utils::tips('warning', 'The User Is Limited');
            }


            // 设置SESSION
            $this->session->set('user_id', $userData['id']);
            $this->session->set('username', $userData['username']);
            $this->session->set('name', $userData['name']);


            // 生成Ticket
            $ticket = $this->authModel->createTicket($userData['id']);


            // 回调地址
            if (strpos($referer, 'redirect=') === false) {
                $redirect = $_SERVER['HTTP_ORIGIN'] . '/login';
            } else {
                $redirect = urldecode(substr($referer, strpos($referer, 'redirect=') + 9));
            }
            if (strpos($redirect, '?')) {
                $redirect .= '&ticket=' . $ticket;
            } else {
                $redirect .= '?ticket=' . $ticket;
            }


            // 检查令牌
            if (empty($userData['secret_key'])) {
                $this->session->set('isLogin', 1);
                $this->authModel->securityCheck($userData, $location);
                header("Location:" . $redirect);
            } else {
                $this->session->set('redirect', $redirect);
                header("Location: /api/sso/OTPAuth");
            }
            exit();
        }


        $this->view->pick('sso/index');
    }


    public function OTPAuthAction()
    {
        $redirect = $this->session->get('redirect');
        if (!$redirect) {
            header('Location: /api/sso/login');
            exit();
        }
        if ($_POST) {
            $code = $this->request->get('code', 'int');

            $user_id = $this->session->get('user_id');
            $user = $this->authModel->getUser($user_id);

            $otp = new PHPGangsta_GoogleAuthenticator();
            $checkResult = $otp->verifyCode($user['secret_key'], $code, 2);    // 2 = 2*30sec clock tolerance
            $this->session->set('redirect', null);
            if (!$checkResult) {
                Utils::tips('warning', 'Authenticator Code Is Error');
            }
            $this->session->set('isLogin', 1);
            header("Location:" . $redirect);
            exit();
        }
        $this->view->pick('sso/OTPAuth');
    }


    public function logoutAction()
    {
        $this->session->destroy();
    }


    public function qrAction()
    {
        $username = $this->session->get('username');
        if (!$username) {
            exit('No Permission');
        }

        // 二维码生成与验证
        $otp = new PHPGangsta_GoogleAuthenticator();
        if ($_POST) { // 验证 开启二次验证是否正确
            $code = $this->request->get('code', 'int');
            $secret_key = $this->session->get('secret_key');
            $checkResult = $otp->verifyCode($secret_key, $code, 2);
            if (!$checkResult) {
                Utils::outputJSON(array('code' => 0, 'message' => 'Verify Success'));
                $user_id = $this->session->get('user_id');
                $this->authModel->setOTPKey($user_id, $secret_key);
            }
            Utils::outputJSON(array('code' => 1, 'message' => 'Verify Failed'));
        }
        $secret_key = $otp->createSecret(32);
        $this->session->set('secret_key', $secret_key);
        $username = urlencode('账号：') . $username;
        $url = "otpauth://totp/{$username}?secret={$secret_key}&issuer=" . urlencode('XXTIME.COM');
        $qrCode = new QrCode();
        $qrCode
            ->setText($url)
            ->setSize(200)
            ->setPadding(10)
            ->setErrorCorrection('low')
            ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
            ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
            //->setLabel('xxtime.com')
            //->setLabelFontSize(8)
            ->setImageType(QrCode::IMAGE_TYPE_PNG);
        header('Content-Type: ' . $qrCode->getContentType());
        $qrCode->render();
        exit;
    }


    public function verifyAction()
    {
        $ticket = $this->request->get('ticket', 'string');
        $user = $this->authModel->getUserByTicket($ticket);
        if (!$user) {
            Utils::outputJSON(array('code' => 1, 'message' => 'failed'));
        }
        $user = array(
            'user_id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name']
        );
        Utils::outputJSON(array('code' => 0, 'message' => 'success', 'data' => $user));
    }


    public function resourcesAction()
    {
        $app = $this->request->get('app', 'int');
        $ticket = $this->request->get('ticket', 'string');
        $user = $this->authModel->getUserByTicket($ticket);
        if (!$user) {
            Utils::outputJSON(array('code' => 1, 'message' => 'failed'));
        }

        $result['aclAll'] = $this->authModel->getAclResource(10000, $app);
        $result['aclAllow'] = $this->authModel->getAclResource($user['id'], $app);
        $result['menuTree'] = $this->utilsModel->list2tree($this->authModel->getResources($user['id'], $app));
        Utils::outputJSON(array('code' => 0, 'message' => 'success', 'data' => $result));
    }

}
