<?php

namespace MyApp\Models;


use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;

class Product extends Model
{

    public function initialize()
    {
    }


    /**
     * 获取产品
     * TODO :: 增加缓存
     * @param int $app_id
     * @param string $gateway
     * @return mixed
     */
    public function getList($app_id = 0, $gateway = '')
    {
        $sql = "SELECT `name`,product_id,price,currency,coin,remark,image FROM `products` WHERE app_id=:app_id AND gateway=:gateway AND status=1 ORDER BY sort DESC";
        $bind = array('app_id' => $app_id, 'gateway' => $gateway);
        $query = DI::getDefault()->get('dbData')->query($sql, $bind);
        $query->setFetchMode(Db::FETCH_ASSOC);
        return $query->fetchAll();
    }

}