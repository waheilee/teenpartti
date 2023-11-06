<?php

namespace app\evolution\controller;

use think\Controller;

class Base extends Controller
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

    public function _initialize()
    {
        if (request()->ip() == '177.71.145.60' && request()->action() != 'createuser') {
            $this->Merchant_ID = config('evolution_test.Merchant_ID');
            $this->Currency    = config('evolution_test.Currency');
            $this->Casino_Key  = config('evolution_test.Casino_Key');
            $this->API_Token   = config('evolution_test.API_Token');
            $this->API_Host    = config('evolution_test.API_Host');
            $this->language    = config('evolution_test.language');
            $this->country     = config('evolution_test.country');
            $this->url = $this->API_Host.'/ua/v1/'.$this->Casino_Key.'/'.$this->API_Token;
        } else {
            $this->Merchant_ID = config('evolution.Merchant_ID');
            $this->Currency    = config('evolution.Currency');
            $this->Casino_Key  = config('evolution.Casino_Key');
            $this->API_Token   = config('evolution.API_Token');
            $this->API_Host    = config('evolution.API_Host');
            $this->language    = config('evolution.language');
            $this->country     = config('evolution.country');
            $this->url = $this->API_Host.'/ua/v1/'.$this->Casino_Key.'/'.$this->API_Token;
        }

    }


    public function failjson($msg,$code=100){
        $data = json_encode([
            'code' => $code,
            'msg' => $msg
        ]);
        save_log('evolution', '===='.$this->request->url().'====响应失败数据====' . $data);
        return json([
            'code' => $code,
            'msg' => $msg
        ]);
    }

    public function succjson($data){
        $data = json_encode([
            'code' => 0,
            'msg' => 'success',
            'data'=>$data
        ]);
        save_log('evolution', '===='.$this->request->url().'====响应成功数据====' . $data);
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