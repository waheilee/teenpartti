<?php
namespace app\common;

use app\model\Log;

//操作日志
class GameLog
{
    public static function log($data)
    {
        $model = new Log();
        $model->add($data);
    }

    public static function logData($method, $request, $status = 1, $response = '')
    {
        $res  = [
            'userid' => session('userid'),
            'username' => session('username'),
            'action' => $method,
            'request' => json_encode($request,JSON_UNESCAPED_UNICODE),
            'logday'  => date('Ymd'),
            'recordtime' => date('Y-m-d H:i:s'),
            'status' => $status
        ];
        if ($response) {
            $res['response'] = json_encode($response,JSON_UNESCAPED_UNICODE);
        }
        $model = new Log();
        $model->add($res);
    }
}