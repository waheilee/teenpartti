<?php

namespace app\spribe\controller;

use think\Controller;

class Base extends Controller
{
    public $operator_id ='';
    public $securykey ='';
    public $config = [];

    public function _initialize()
    {
        $tgcfg = config('spribe');
        $this->config = $tgcfg;
    }


    public function failjson($msg){
        $log_data = json_encode([
            'code'=>100,
            'msg'=>$msg,
        ]);
        save_log('spribe', '===='.request()->url().'====响应失败数据====' . $log_data);
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
        save_log('spribe', '===='.request()->url().'====响应成功数据====' . $log_data);
        return json([
            'code'=>0,
            'msg'=>'success',
            'data'=>$log_data
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