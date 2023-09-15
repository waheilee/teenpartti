<?php

namespace app\agent\controller;

use app\model\SubAccount;
use redis\Redis;
use think\Controller;
use think\config;
use think\db;
use think\session;
use app\common\Api;
use app\model\Advice;

class Index extends Main
{
    public function index()
    {

        $this->assign('proxyname', session('proxyname'));

        return $this->fetch();
    }



    public function main()
    {

        $this->assign('laypath',$this->laypath);
        $this->assign('proxyid',$this->proxyid);
        $this->assign('proxyname',$this->proxyname);
        return $this->fetch();
    }


}
