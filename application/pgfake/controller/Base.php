<?php

namespace app\pgfake\controller;

use think\Controller;

class Base
{
    public $Merchant_ID ='';
    public $Currency ='';
    public $Secret_Key ='';
    public $Operator_Token= "";
    public $API_Host ='';
    public $GAME_URL ='';
    public $language = '';
    public $country  = '';
    public $url = '';

    public $config = [];
    public $intotime = '';
    
    public function __construct()
    {
        //从API合并转发测试服 接送到请求，使用测试配置
        if (request()->ip() == '177.71.145.60' && request()->action() != 'createuser') {
            $this->Merchant_ID    = config('pgfake_test.Merchant_ID');
            $this->Currency       = config('pgfake_test.Currency');
            $this->Operator_Token = config('pgfake_test.Operator_Token');
            $this->Secret_Key     = config('pgfake_test.Secret_Key');
            $this->API_Host       = config('pgfake_test.API_Host');
            $this->GAME_URL       = config('pgfake_test.GAME_URL');
            $this->language       = config('pgfake_test.language');
            $this->country        = config('pgfake_test.country');
            $this->url            = $this->API_Host;
        } else {
            $this->Merchant_ID    = config('pgfake.Merchant_ID');
            $this->Currency       = config('pgfake.Currency');
            $this->Operator_Token = config('pgfake.Operator_Token');
            $this->Secret_Key     = config('pgfake.Secret_Key');
            $this->API_Host       = config('pgfake.API_Host');
            $this->GAME_URL       = config('pgfake.GAME_URL');
            $this->language       = config('pgfake.language');
            $this->country        = config('pgfake.country');
            $this->url            = $this->API_Host;
        } 
    }


    public function failjson($msg){
        $log_data = json_encode([
            'code'=>100,
            'msg'=>$msg,
        ]);
        //save_log('pggame', '===='.request()->url().'====响应失败数据====' . $log_data);
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
        //save_log('pggame', '===='.request()->url().'====响应成功数据====' . $log_data);
        return json([
            'code'=>0,
            'msg'=>'success',
            'data'=>$log_data
        ]);
    }


     public function apiReturn($data=null,$code="0",$msg='success'){
        $log_data = json_encode([
            'data'=>$data,
            'error'=>[
                'code' =>$code,
                'message'  =>$msg
            ]
        ]);
        //save_log('pggame', '===='.request()->url().'====响应数据====' . $log_data);
        if ($data) {
            return json([
                'data'=>$data,
                'error'=>null
            ]);
        } else {
            return json([
                'data'=>null,
                'error'=>[
                    'code' =>$code,
                    'message'  =>$msg
                ]
            ]);
        }
    }
}