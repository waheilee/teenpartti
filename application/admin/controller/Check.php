<?php

namespace app\admin\controller;

use app\common\Api;
use app\model\User as userModel;
use redis\Redis;
use think\Controller;

class Check extends Controller
{
    //检查多人登录
    public function checkmultilogin()
    {
        $userid = session('userid');
        $model = new userModel();
        if ($userid) {
            $userInfo = $model->getRowById($userid);
            if ($userInfo['session_id'] != session_id()) {
                session('username', null);
                session('userid', null);
                session(null);
                cookie('username', null);
                cookie('auth', null);
                return json(['code' => 1, 'msg' => lang('您的账号已在其他地方登录')]);
            } else {
                return json(['code' => 0]);
            }
        }
        return json(['code' => 2]);
    }

    //检查60分钟是否有操作,增加到12小时
    public function checknouse()
    {
        if (!session('userid') || (session('lastuse') && sysTime() - session('lastuse') > 3600*12)) {
            session('username', null);
            session('userid', null);
            session(null);
            cookie('username', null);
            cookie('auth', null);
            return json(['code' => 1, 'msg' => lang('您长时间未操作，系统已自动退出')]);
        } else {
            session('lastuse', sysTime() + 3600*12);
            return json(['code' => 0, 'time' => sysTime()]);
        }
    }

    /**
     * 检查是否有新的申请记录
     */
    public function checknewapply()
    {
        if (session('userid')) {
            $data  = ['page' => 1, 'pagesize' => 10, 'roleid' => 0, 'startdate' => '', 'enddate' => '', 'OperateVerifyType' => 0, 'payway' => 0, 'realname' => ''];
            $res   = Api::getInstance()->sendRequest($data, 'charge', 'outlist');
            $count = isset($res['data']['list']) ? count($res['data']['list']) : 0;
            if (!session('userid')) {
                return json(['code' => 1]);
            }
            $key = __METHOD__ . 'userid' . session('userid');
            $cachecount = Redis::get($key);
            $same = 1;
            if ($cachecount != $count) {
                $same = 0;
                Redis::set($key, $count, 3600);
            }
            return json(['code' => 0, 'data' => ['count' => $count, 'same' => $same]]);
        } else {
            return json(['code' => 1]);
        }

    }

}
