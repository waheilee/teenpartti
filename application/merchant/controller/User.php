<?php

namespace app\merchant\controller;

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
        if (session('merchant_OperatorId')) $this->redirect('merchant/index/index');

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


    //rsa密码解密
    public function rsacheck($crypttext)
    {
        $txt_en = base64_encode(pack("H*", $crypttext));
        $txt_de = $this->privatekey_decodeing($txt_en, config('rsa')['private'], TRUE);
        return trim($txt_de);
    }

    public function privatekey_decodeing($crypttext, $key_content, $fromjs = FALSE)
    {
        $prikeyid = openssl_get_privatekey($key_content);
        $crypttext = base64_decode($crypttext);
        $padding = $fromjs ? OPENSSL_NO_PADDING : OPENSSL_PKCS1_PADDING;
        if (openssl_private_decrypt($crypttext, $sourcestr, $prikeyid, $padding)) {
            return $fromjs ? trim(strrev($sourcestr), "/0") : "" . $sourcestr;
        }
        return false;
    }

    /**
     * 验证身份
     */
    public function verify()
    {
        $username = input('username') ? input('username') : '';
        $password = input('password') ? input('password') : '';

        //$mobile = input('mobile') ? input('mobile') : '';
        $mobile = 1234567;
        if (!$username || !$password || !$mobile) {
            return json(['code' => 1, 'msg' => lang('请输入账号密码')]);
        }
        $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')->where('OperatorName',$username)->find();
        if (!$userInfo) {
            return json(['code' => 2, 'msg' => lang('账号密码有误，请重新输入')]);
        }
        if (md5($password) !== $userInfo['PassWord']) {
            return json(['code' => 3, 'msg' => lang('账号密码有误，请重新输入')]);
        }
        // if ($userInfo['status'] != 1) {
        //     return json(['code' => 4, 'msg' => lang('您的账号已被禁用，请联系管理员处理')]);
        // }

        //生成谷歌验证秘钥，保存
        $secret = $userInfo['google_verify'];
        Loader::import('PHPGangsta.GoogleAuthenticator', EXTEND_PATH);
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $isshow = false;
        if (!$secret) {
            $secret = $ga->createSecret();
            Redis::getInstance()->set('merchant_googlevery' . $username, $secret);
            //$userModel->updateById($userInfo['id'], ['google_verify' => $secret]);
            if (config('app_status') == 'product' && config('googlecheck')==1) {
                $isshow = true;
            }
        }
        
        if ($isshow) {
            $qrCodeUrl = $ga->getQRCodeGoogleUrl('gameoc_' . $username, $secret, 'googleVerify', ['width' => 100, 'height' => 100]);
            return json(['code' => 0, 'msg' => '', 'data' => $qrCodeUrl, 'isshow' => 1, 'secret' => $secret]);
        } else {
            return json(['code' => 0, 'msg' => '', 'isshow' => 0]);
        }

    }


    /**
     * 登录
     */
    public function login()
    {
            //首次访问触发
            if (!$this->request->isAjax()) {
                if (session('merchant_OperatorId')) {
                    $this->redirect('merchant/index/index');
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
            $code = $post['code'] ??'';
            $mobile = '1234567';
            $secret = '';
            $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')->where('OperatorName',$username)->find();
            if (!$userInfo) {
                return json(['code' => 3, 'msg' => lang('账号有误')]);
            }

            if (md5($password) !== $userInfo['PassWord']) {
                return json(['code' => 3, 'msg' => lang('密码错误')]);
            } else {
                //判断验证码(正式环境验证)
                if (config('app_status') == 'product' && config('googlecheck')==1) {
                    if (!$userInfo['google_verify']) {
                        $secret = Redis::getInstance()->get('merchant_googlevery' . $username);
                    } else
                        $secret = $userInfo['google_verify'];

                    Loader::import('PHPGangsta.GoogleAuthenticator', EXTEND_PATH);
                    $ga = new \PHPGangsta_GoogleAuthenticator();
                    $checkResult = $ga->verifyCode($secret, $code, 1);
                    if (!$checkResult) {
                        return json(['code' => 3, 'msg' => lang('验证码输入有误，请重新输入')]);
                    }
                }
                session('merchant_Id', $userInfo['Id']);
                session('merchant_OperatorName', $userInfo['OperatorName']);
                session('merchant_OperatorId', $userInfo['OperatorId']);
                session('merchant_session_start_time', time());//记录会话开始时间！判断会话时间的重点！重点！重点！
                if (false) {//记住我//$post['remember'] == 1
                   
                } else {

                    $save_data = [
                        'LastLoginIp' => $_SERVER['REMOTE_ADDR'],
                        'LastLoginTime' => date('Y-m-d H:i:s', time()),
                    ];

                    if (!empty($secret)) {
                        if (!$userInfo['google_verify']) {
                            $save_data['google_verify'] = $secret;
                        }
                    }
                    $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')
                        ->where('OperatorName', $username)
                        ->update($save_data);
                }
                return json(['code' => 0]);

            }
    }



    //注销
    public function logOut()
    {
        session('merchant_OperatorName', null);
        session('merchant_OperatorId', null);
        cookie('merchant_OperatorName', null);
        cookie('merchant_OperatorId', null);
        // cookie('auth', null);
        $domain = $this->request->domain();
        $domain .= "/merchant/user/index";
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
