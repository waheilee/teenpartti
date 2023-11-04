<?php

namespace app\api\controller;


use socket\QuerySocket;
use think\Controller;
use think\Request;

class Js extends Controller
{

    public function refresh(Request $request)
    {
        $token = $request->get('token');
        $roleId = intval($this->decry($token));
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleId);
        $gameMoney = $m['iGameWealth'] ?? 0;
        $balance = bcdiv($gameMoney, bl, 3);
        $num = floor($balance * 100) / 100;
        header('Content-Type: text/plain');
        return json($num);
    }

    //解密
    private function decry($str, $key = 'btiplus')
    {
        return think_decrypt($str, $key);
    }
}