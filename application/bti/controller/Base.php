<?php

namespace app\Bti\controller;

use think\Controller;

class Base extends Controller
{
    public $AgentUserName ='';
    public $AgentPassword ='';
    public $Currency  = '';
    public $country  = '';
    public $language  = '';
    public $url = '';

    public $config = [];

    public function _initialize()
    {
        $this->AgentUserName = config('bti.agentUserName');
        $this->AgentPassword = config('bti.agentPassword');
        $this->Currency = config('bti.Currency');
        $this->country = config('bti.country');
        $this->language = config('bti.language');
        $this->url = config('bti.API_Host');
    }


    public function failjson($msg){
        $data = json_encode([
            'code' => 100,
            'msg' => $msg
        ]);
        save_log('bti', '===='.$this->request->url().'====响应失败数据====' . $data);
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
        save_log('bti', '===='.$this->request->url().'====响应成功数据====' . $data);
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