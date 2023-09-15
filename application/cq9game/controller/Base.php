<?php

namespace app\cq9game\controller;

use think\Controller;

class Base extends Controller
{
    public $operator_id ='';
    public $securykey ='';
    public $config = [];

    public function _initialize()
    {
        $tgcfg = config('cq9game');
        $this->config = $tgcfg;
    }


    public function failjson($msg){
        $log_data = json_encode([
            'code'=>100,
            'msg'=>$msg,
        ]);
        save_log('cq9game', '===='.request()->url().'====响应失败数据====' . $log_data);
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
        save_log('cq9game', '===='.request()->url().'====响应成功数据====' . $log_data);
        return json([
            'code'=>0,
            'msg'=>'success',
            'data'=>$log_data
        ]);
    }
}