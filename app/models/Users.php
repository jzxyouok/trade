<?php

namespace MyApp\Models;


use Phalcon\Mvc\Model;
use Phalcon\DI;
use Phalcon\Db;

class Users extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbData');
        $this->setSource("users");
    }

}