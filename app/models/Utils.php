<?php

namespace MyApp\Models;


use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;
use GeoIp2\Database\Reader;

class Utils extends Model
{


    /**
     * 闪存提示
     * @param string $type
     * @param string $message
     * @param string $redirect
     * @param int $seconds
     */
    static public function tips($type = 'info', $message = '', $redirect = '', $seconds = 0)
    {
        $flash = json_encode(
            array(
                'type'     => $type,
                'msg'      => _($type),
                'message'  => _($message),
                'seconds'  => !empty($seconds) ? $seconds : 3,
                'redirect' => $redirect ? $redirect : 'javascript:history.back(-1)'
            )
        );
        DI::getDefault()->get('cookies')->set('flash', $flash, time() + 3);
        DI::getDefault()->get('cookies')->send();
        header('Location:/public/tips');
        exit();
    }


    /**
     * 输出
     * @param array $data
     */
    static public function outputJSON($data = [])
    {
        header("Content-type:application/json; charset=utf-8");
        exit(json_encode($data, JSON_UNESCAPED_UNICODE));
    }


    /**
     * 形象图片
     * @param string $username
     * @return string
     */
    static public function getAvatar($username = '')
    {
        return 'https://secure.gravatar.com/avatar/' . md5(strtolower(trim($username))) . '?s=80&d=identicon';
    }


    /**
     * 把数组转换为树状结构
     * @param array $data
     * @return array
     */
    public function list2tree($data = array())
    {
        if (!$data) {
            return [];
        }
        $result = array();
        foreach ($data as $value) {
            $parent[] = $value['parent'];
            $result[$value['id']] = $value;
        }
        unset($data);
        $parent = array_filter(array_unique($parent));
        $left_item_id = array();
        $left = array();
        foreach ($result as $id => $value) {
            if ($value['parent'] == 0) {
                continue;
            }
            if (!in_array($id, $parent)) {
                // 移动节点,只允许存在父级的节点移动
                if (isset($result[$value['parent']]['id'])) {
                    $result[$value['parent']]['sub'][$id] = $value;
                }
                unset($result[$id]);
            } else {
                $left_item_id[] = $id;
                $left[] = $value;
            }
        }
        $intersect = array_intersect($parent, $left_item_id);
        if ($intersect) {
            $result = $this->list2tree($result);
        }
        return $result;
    }


    /**
     * 位置信息
     * @param string $ipAddress
     * @return string|void
     */
    public function getLocation($ipAddress = '')
    {
        if (in_array($ipAddress, ['127.0.0.1'])) {
            return;
        }
        if (!file_exists(APP_DIR . '/config/GeoLite2-City.mmdb')) {
            return;
        }
        $reader = new Reader(APP_DIR . '/config/GeoLite2-City.mmdb');
        $record = $reader->city($ipAddress);
        $location = $record->country->names['zh-CN'] . ' ' . $record->mostSpecificSubdivision->names['zh-CN'] . ' ' . $record->city->names['zh-CN'];
        $location .= ' ' . $record->location->latitude . ' ' . $record->location->longitude;
        return $location;
    }

}