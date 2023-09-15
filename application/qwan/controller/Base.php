<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/1/4
 * Time: 11:54
 */
namespace app\qwan\controller;

use think\Controller;

class Base extends Controller
{


    public $key = '';
    public $headurl ='';
    public $gameurl ='';
    public $config = [];

    public function _initialize()
    {
        $qwancfg = config('qwan');
        $this->key = $qwancfg['key'];
        $this->headurl = $qwancfg['headurl'];
        $this->config = $qwancfg;
        $this->gameurl = $qwancfg['gameurl'];
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