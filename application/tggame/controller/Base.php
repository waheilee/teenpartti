<?php

namespace app\tggame\controller;

use think\Controller;

class Base extends Controller
{
    public $operator_id ='';
    public $securykey ='';
    public $config = [];

    public function _initialize()
    {
        $tgcfg = config('tggame');
        $this->config = $tgcfg;
        $this->operator_id = $tgcfg['operator_id'];
        $this->securykey = $tgcfg['securykey'];
    }


    public function failjson($msg){
        return json([
            'code' => 100,
            'msg' => $msg
        ]);
    }

    public function succjson($data){
        return json([
            'code' => 0,
            'msg' => 'success',
            'data'=>$data
        ]);
    }


    public function apiReturn($code, $data = [], $msg = '')
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg'  => $msg

        ]);
    }

}