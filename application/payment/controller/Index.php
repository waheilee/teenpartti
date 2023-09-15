<?php

namespace app\payment\controller;


use redis\Redis;
use think\Controller;
use think\config;
use think\db;
use think\session;
use app\common\Api;

class Index extends Main
{
    public function index()
    {

        return $this->fetch();
    }

    public function getservertime(){
        $date =date('Y-m-d H:i:s',time());
        return json(['servertime'=>$date]);
    }



    public function main()
    {

        $res = Api::getInstance()->sendRequest(['id' => $this->userid], 'player', 'getproxy');
        $userInfo = $res['data'];
        $this->assign('info', $userInfo);
        $this->assign('laypath',$this->laypath);
        return $this->fetch();
    }


    public function changepsw()
    {
        return $this->fetch();
    }


    public function savepsw()
    {

    }





}
