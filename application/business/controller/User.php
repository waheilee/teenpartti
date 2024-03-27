<?php

namespace app\business\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\User as userModel;
use redis\Redis;
use think\Controller;
use think\Db;
use think\Loader;


class User extends Controller
{


    public function __construct()
    {
        parent::__construct();
        $curr_lang = cookie('think_var');
        $laypath = "/public/layuiAdmin/layuiadmin";
        switch ($curr_lang) {
            case "en-us":
                $laypath = "/public/layuiAdmin/layuiadmin_en";
                $date_lang = ",lang: 'en'";
                break;
            case "thai":
                $laypath = "/public/layuiAdmin/layuiadmin_th";
                $date_lang = ",lang: 'en'";
                break;
            default:
                $laypath = "/public/layuiAdmin/layuiadmin";
                break;
        }

        $this->assign('laypath', $laypath);
    }

    /**
     * 首页
     */
    public function index()
    {
        if (session('business_ProxyChannelId')) $this->redirect('business/index/index');

        $lang = input('lang') ?: 'zh-cn';
        $curr_lang = cookie('think_var');

        if ($lang != $curr_lang) {
            cookie('think_var', $lang);
        }
        $this->assign('currlang', $lang);
        if (config('is_version_65') == 1) {
            return $this->fetch('login_new');
        } else {
           return $this->fetch('login'); 
        }
    }


    /**
     * 登录
     */
    public function login()
    {
            //首次访问触发
            if (!$this->request->isAjax()) {
                if (session('business_ProxyChannelId')) {
                    $this->redirect('business/index/index');
                } else {
                    $this->logOut();
                }
            }
            $post = $this->request->param();
            if (!$post) {
                return json(['code' => 3, 'msg' => lang('账号有误')]);
            }
            //判断验证码
            $username = $post['username'] ?? '';
            $password = $post['password'] ??'';

            $userInfo = (new \app\model\GameOCDB)->getTableObject('T_ProxyChannelConfig')->where('LoginAccount',$username)->find();
            if (!$userInfo) {
                return json(['code' => 3, 'msg' => lang('账号有误')]);
            }

            if (md5($password) !== $userInfo['PassWord']) {
                return json(['code' => 3, 'msg' => lang('密码错误')]);
            } else {
                session('business_LoginAccount', $userInfo['LoginAccount']);
                session('business_ProxyChannelId', $userInfo['ProxyChannelId']);
                session('business_OperatorId', $userInfo['OperatorId']);
                session('business_Proxytype', $userInfo['type']);
                return json(['code' => 0]);
            }
    }



    //注销
    public function logOut()
    {
        session('business_LoginAccount', null);
        session('business_ProxyChannelId', null);
        session('business_Proxytype', null);
        cookie('business_LoginAccount', null);
        cookie('business_ProxyChannelId', null);
        cookie('business_Proxytype', null);
        // cookie('auth', null);
        $domain = $this->request->domain();
        $domain .= "/business/user/index";
        echo "<script>parent.location.href = '" . $domain . "'</script>";
        die;
    }

    

    public function apiReturn($code, $data = [], $msg = '', $count = 0, $other = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg' => $msg,
            'count' => $count,
            'other' => $other
        ]);
    }
}
