<?php

namespace app\btiplus\controller;

use think\Controller;

class Base
{
    public $Merchant_ID ='';
    public $Currency ='';
    public $Casino_Key ='';
    public $API_Token= "";
    public $API_Host ='';

    public $language = '';
    public $country  = '';
    public $url = '';

    public $config = [];

    public function __construct()
    {
        //从API合并转发测试服 接送到请求，使用测试配置
        if (request()->ip() == '177.71.145.60' && request()->action() != 'createuser') {
            $this->Merchant_ID = config('pplay_test.Merchant_ID');
            $this->Currency    = config('pplay_test.Currency');
            $this->API_Token   = config('pplay_test.API_Token');
            $this->GAME_URL    = config('pplay_test.GAME_URL');
            $this->API_Host    = config('pplay_test.API_Host');
            $this->language    = config('pplay_test.language');
            $this->country     = config('pplay_test.country');
            $this->url         = $this->API_Host;
        } else {
            $this->Merchant_ID = config('pplay.Merchant_ID');
            $this->Currency    = config('pplay.Currency');
            $this->API_Token   = config('pplay.API_Token');
            $this->GAME_URL    = config('pplay.GAME_URL');
            $this->API_Host    = config('pplay.API_Host');
            $this->language    = config('pplay.language');
            $this->country     = config('pplay.country');
            $this->url         = $this->API_Host;
        }
         
    }


    public function failjson($errorCode,$msg){
        $data = json_encode([
            'error_code'=>$errorCode,
            'error_message' => $msg
        ]);
        save_log('bti_plus', '===='.request()->url().'====响应失败数据====' . $data);
        return json([
            'error_code'=>$errorCode,
            'error_message' => $msg
        ]);
    }
    public function failjsonpp($msg){
        $data = json_encode([
            'error' => 100,
            'description' => $msg
        ]);
        save_log('bti_plus', '===='.request()->url().'====响应失败数据====' . $data);
        return json([
            'error' => 100,
            'description' => $msg
        ]);
    }

    public function succjson($data){
        $data = json_encode([
            'error' => 0,
            'code'=>0,
            'msg'=>'success',
            'description' => 'success',
            'data'=>$data
        ]);
        save_log('bti_plus', '===='.request()->url().'====响应成功数据====' . $data);
        return json([
            'error' => 0,
            'code'=>0,
            'msg'=>'success',
            'description' => 'success',
            'data'=>$data
        ]);
    }


    public function apiReturn($code, $data = [], $msg = '')
    {
        return json([
            'error' => $code,
            'data' => $data,
            'code'=>0,
            'msg'=>'success',
            'description'  => $msg

        ]);
    }

}