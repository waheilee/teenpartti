<?php

namespace app\fcgame\controller;

use think\Controller;

class Base extends Controller
{

    public $config = [];

    public function _initialize()
    {
        if (request()->ip() == '177.71.145.60' && request()->action() != 'createuser') {
            $tgcfg = config('fcgame_test');
        } else {
            $tgcfg = config('fcgame');
        }
        $this->config = $tgcfg;
    }


    public function failjson($msg){
        $log_data = json_encode([
            'code'=>100,
            'msg'=>$msg,
        ]);
        save_log('fcgame', '===='.request()->url().'====响应失败数据====' . $log_data);
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
        save_log('fcgame', '===='.request()->url().'====响应成功数据====' . $log_data);
        return json([
            'code'=>0,
            'msg'=>'success',
            'data'=>$log_data
        ]);
    }


    public function apiReturn($code, $msg = '',$data = [],$logname='fcgame')
    {
        $retdata = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ];
        save_log($logname, '==='.request()->url().'===响应成功数据===' . json_encode($retdata));
        return json($retdata);
        exit();
    }

}