<?php

namespace app\merchant\controller;

use app\common\Api;
use app\model\User as userModel;
use redis\Redis;
use think\Controller;

class Check extends Controller
{
    //检查多人登录
    public function checkmultilogin()
    {
        $userid = session('merchant_OperatorId');
        if (!$userid) {
            session('merchant_OperatorName', null);
            session('merchant_OperatorId', null);
            return json(['code' => 1, 'msg' => lang('您的账号已在其他地方登录')]);
        }
        return json(['code' => 2]);
    }

    //检查60分钟是否有操作,增加到12小时
    public function checknouse()
    {

    }
}
