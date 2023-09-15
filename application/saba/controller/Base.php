<?php

namespace app\Saba\controller;

use think\Controller;

class Base extends Controller
{
    public $vendor_id ='';
    public $operatorId ='';
    public $Currency  = '';
    public $country  = '';

    public $url = '';

    public $config = [];

    public function _initialize()
    {
        $this->vendor_id = config('saba.vendor_id');
        $this->operatorId = config('saba.operatorId');
        $this->Currency = config('saba.Currency');
        $this->country = config('saba.country');
        $this->url = config('saba.API_Host');
    }


    public function failjson($msg){
        $data = json_encode([
            'code' => 100,
            'msg' => $msg
        ]);
        save_log('saba', '===='.$this->request->url().'====响应失败数据====' . $data);
        return json([
            'code' => 100,
            'msg' => $msg
        ]);
    }

    public function succjson($data){
        $data = json_encode([
            'code' => 0,
            'msg' => 'success',
            'data'=>$data
        ]);
        save_log('saba', '===='.$this->request->url().'====响应成功数据====' . $data);
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