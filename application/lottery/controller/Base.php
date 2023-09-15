<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/1/4
 * Time: 11:54
 */
namespace app\lottery\controller;

use think\Controller;

class Base extends Controller
{
    public $merchant_code ='';
    public $config = [];

    public function _initialize()
    {
        $qwancfg = config('lottery');
        $this->merchant_code = $qwancfg['MerchantCode'];
        $this->config = $qwancfg;
    }

    public function jsonReturn($rc,$msg=''){
        return json([
            'rc' => $rc,
            'rmsg' => $msg
        ]);
    }
    public function errorinfo($msg){
        return json([
            'code' => 100,
            'msg' => $msg
        ]);
    }


    public function apiReturn($code, $data = [], $msg = '', $count = 0, $other = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg'  => $msg,
            'count' => $count,
            'other' => $other
        ]);
    }

}