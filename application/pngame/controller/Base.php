<?php

namespace app\pngame\controller;

use think\Controller;

class Base extends Controller
{
    public $operator_id ='';
    public $securykey ='';
    public $config = [];

    public function _initialize()
    {
        $tgcfg = config('pngame');
        $this->config = $tgcfg;
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