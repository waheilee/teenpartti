<?php

namespace app\agent\controller;

use app\model\AccountDB;
use app\model\BankDB;
use app\model\Operatelog;
use think\captcha\Captcha;
use think\Controller;

class Login extends Controller
{
    public $extime;

    public function index() {
        $lang = input('lang', 'null');
        if(in_array($lang,['zh-cn','en-us','thai'])){
            cookie('think_var',$lang);
        }
        else
        {
            $this->redirect(url('login/index').'?lang=zh-cn');
            //$lang ='en-us';
            //cookie('think_var',$lang);
        }

        $laypath ="/public/layuiAdmin/layuiadmin";
        switch ($lang){
            case "en-us":
                $laypath ="/public/layuiAdmin/layuiadmin_en";
                break;
            case "thai":
                $laypath ="/public/layuiAdmin/layuiadmin_th";
                break;
            default:
                $laypath ="/public/layuiAdmin/layuiadmin";
                break;
        }
        $this->assign('lang',$lang);
        $this->assign('laypath',$laypath);
        return $this->fetch('login');
    }


    public function verify()
    {
        // 实例化验证码类
        ob_clean();
        $captcha = new Captcha(config('captcha'));
        return $captcha->entry();
    }


    public function geeturl(){
        $data =$this->request->param(); //传入请求数据,使用false参数以获取原始数据
        if(!is_null($data) && !geetest_check($data)){
            //验证失败
            return json()->data(false)->code(403); // 自定义
        }
    }
    /**
     * 登录
     */
    public function login()
    {
        $post = $this->request->post();
        $mobile = $post['mobile'];
        //$code = $post["vercode"];
        $password=$post['password'];

//        $captcha = new Captcha();
//        if(!$captcha->check($code)){
//            return json(['code' => 3, 'msg' => lang('login_error_code')]);
//        }
        //$res = Api::getInstance()->sendRequest(['username' => $mobile], 'player', 'getproxybyname');
        //$userInfo =$res['data'];

//        $account = new Account();
//        $userInfo =  $account->getRow(['accountname'=>$mobile]);
        $account=new AccountDB();
        $userInfo =  $account->GetRow(['accountname'=>$mobile]);
        if (empty($userInfo)) {
            return json(['code' => 3, 'msg' => lang('login_error_user_unexist')]);
        }

        if($userInfo['Locked']>0){
            return json(['code' => 3, 'msg' => lang('login_error_user_forbbiden')]);
        }

        $newpsw =strtolower(md5($password));//md5(md5(strtolower($password)).$userInfo['salt']);
        if ( $newpsw!= $userInfo['Password']) {
            return json(['code' => 3, 'msg' => lang('login_error_user_wrongpass')]);
        } else {

            session('proxyid', $userInfo['AccountID']);
            session('proxyname', $userInfo['AccountName']);
            session('agentinfo',$userInfo);

            $userbank = new BankDB();
            $where = ['RoleID' => $userInfo['AccountID']];
            $where['SuperPlayerLevel'] =array(['=',100],['=',1000],'or');
            $isproxy = $userbank->getCount($where);
            if($isproxy==0){
                return json(['code' => 3, 'msg' => '当前玩家非代理，请升级为代理后操作']);
            }
            return json(['code' => 0]);
        }

    }


    function generateSalt()
    {
        $str = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';
        $salt = strtolower(substr(str_shuffle($str), 10, 6));
        return $salt;
    }


//    public function sendcode()
//    {
//
//        $mobile = input("mobile");
//        if (!$mobile) {
//            return json(['status' => -1, 'msg' => '参数错误！', 'data' => 5]);
//        }
//        $num = $this->randnum();
//        $content = $num . "有效期2分钟，验证码请不要随意告知他人，工作人员不会向您索取。";
//        $ret = sendSms($mobile, $content);
//        if ($ret == 'true') {
//            session("yzm", $num);
//            $extime=time();
//            return json(['status' => 0, 'msg' => '短信发送成功', 'data' => 6]);
//        } else {
//            return json(['status' => -1, 'msg' => '短信发送成功', 'data' => 6]);
//        }
//
//    }

    public function randnum()
    {
        $arr = array();
        while (count($arr) < 4) {
            $arr[] = rand(1, 9);
            $arr = array_unique($arr);
        }
        return implode("", $arr);
    }

    public function randstr()
    {
        //取随机10位字符串
//        $strs="QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
        $strs="QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm";
        $name=substr(str_shuffle($strs),mt_rand(0,strlen($strs)-11),6);
        return $name;
    }

    //注销
    public function logOut()
    {
        session('proxyid', null);
        session('rate', null);
        session('merchantid', null);
        session('proxyname', null);
        session('merchantinfo', null);
        $lang=cookie('think_var');
        $this->redirect(url('login/index').'?lang='.$lang);
    }


}
