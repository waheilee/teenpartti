<?php

namespace app\btiplus\controller;

use think\Controller;
use think\Response;
class Base
{
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

    public function apiReturnText($errorCode, $errorMsg, $balance, $trxId = "")
    {
        header('Content-Type: text/plain');
        if (!empty($trxId)) {
            $data = 'error_code=' . $errorCode . PHP_EOL .
                'error_message=' . $errorMsg . PHP_EOL .
                'balance=' . $balance . PHP_EOL .
                'trx_id=' . $trxId;
        } else {
            $data = 'error_code=' . $errorCode . PHP_EOL .
                'error_message=' . $errorMsg . PHP_EOL .
                'balance=' . $balance . PHP_EOL;
        }
        // 创建一个Response实例
        return $this->setHeader($data);

    }

    public function setHeader($data)
    {
        $response = Response::create($data, 'text/plain', 200);

        // 设置字符集
        $response->header('Content-Type', 'text/plain; charset=utf-8');

        return $response;
    }



}