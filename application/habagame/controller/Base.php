<?php

namespace app\habagame\controller;

use think\Controller;

class Base extends Controller
{
    public $operator_id ='';
    public $securykey ='';
    public $config = [];

    public function _initialize()
    {
        if (request()->ip() == '177.71.145.60' && request()->action() != 'createuser') {
            $tgcfg = config('habagame_test');
        } else {
            $tgcfg = config('habagame');
        }

        $this->config = $tgcfg;
    }


    public function failjson($msg){
        $log_data = json_encode([
            'code'=>100,
            'msg'=>$msg,
        ]);
        save_log('habagame', '===='.request()->url().'====响应失败数据====' . $log_data);
        return json([
            'code'=>100,
            'msg'=>$msg,
        ]);
    }

    public function succjson($data){
        $log_data = json_encode([
            'code'=>0,
            'msg'=>'success',
            'data'=>$data
        ]);
        save_log('habagame', '===='.request()->url().'====响应成功数据====' . $log_data);
        return json([
            'code'=>0,
            'msg'=>'success',
            'data'=>$log_data
        ]);
    }
}