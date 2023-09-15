<?php

namespace app\admin\controller;

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
        if (session('userid')) $this->redirect('admin/index/index');

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
        $userModel = new userModel();
//        $userInfo = $userModel->getRow(['username' => $username, 'mobile' => $mobile]);
        $userInfo = $userModel->getRow(['username' => $username]);
        if (!$userInfo) {
            return json(['code' => 2, 'msg' => lang('账号密码有误，请重新输入')]);
        }
        $pwd = $this->rsacheck($password);
        if (!$pwd) {
            return json(['code' => 13, 'msg' => lang('密码错误')]);
        }
        if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
            return json(['code' => 3, 'msg' => lang('账号密码有误，请重新输入')]);
        }
        if ($userInfo['status'] != 1) {
            return json(['code' => 4, 'msg' => lang('您的账号已被禁用，请联系管理员处理')]);
        }

        //生成谷歌验证秘钥，保存
        $secret = $userInfo['google_verify'];
        Loader::import('PHPGangsta.GoogleAuthenticator', EXTEND_PATH);
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $isshow = false;
        if (!$secret || !$userInfo['last_login_time'] || !$userInfo['last_login_ip']) {
            if (!$secret) {
                $secret = $ga->createSecret();
                Redis::getInstance()->set('googlevery' . $username, $secret);
                //$userModel->updateById($userInfo['id'], ['google_verify' => $secret]);
            }
            if (config('app_status') == 'product') {
                $isshow = true;
            }
        }
        if ($isshow) {
            $qrCodeUrl = $ga->getQRCodeGoogleUrl(config('app_name').'_' . $username, $secret, 'googleVerify', ['width' => 100, 'height' => 100]);
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
        $this->checkLong();


        //首次访问触发
        if (!$this->request->isAjax()) {
            if (session('userid')) {
                $this->redirect('admin/index/index');
            } else {
                $this->logOut();
            }
        }
        //ajax请求
        if (session('userid')) {
            return json(['code' => 100]);
            //$this->redirect('index/index');
        }
        $post = $this->request->post();
        if (empty($post)) {
            return json(['code' => 200]);
            //$this->redirect('user/index');
        }
        $validate = validate('User');
        $validate->scene('login');

        if (!$validate->check($post)) {
            return json(['code' => 3, 'msg' => $validate->getError()]);
            //$this->error($validate->getError());
        } else {
//            if ($post['username'] != 'admin' && config('app_status') == 'product') {
//                $ip = getClientIP();
//                $ipres = Db::name('ip')
//                    ->where("ip", $ip)
//                    ->field("id")
//                    ->find();
//                if (!$ipres) {
//                    return json(['code' => 3, 'msg' => 'ip不在白名单里']);
//                } else {
//                    Db::name('ip')
//                        ->where('id', $ipres['id'])
//                        ->update(['last_login_time' => date('Y-m-d H:i:s')]);
//                }
//            }
            //判断验证码
            $username = $post['username'];
//            $mobile = $post['mobile'];
            $mobile = '1234567';
            $secret = '';
            $userModel = new userModel();
//            $userInfo = $userModel->getRow(['username' => $username, 'mobile' => $mobile]);
            $userInfo = $userModel->getRow(['username' => $username]);
            if (!$userInfo) {
                return json(['code' => 3, 'msg' => lang('账号有误')]);
                //$this->error('账号或手机号有误');
            }

            if ($userInfo['status'] != 1) {
                return json(['code' => 3, 'msg' => lang('您的账号已被禁用，请联系管理员处理')]);
            }


            $pwd = $this->rsacheck($_POST['password']);

            if (!$pwd) {
                return json(['code' => 13, 'msg' => lang('密码错误')]);
            }

            if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
                return json(['code' => 3, 'msg' => lang('密码错误')]);
//                $this->error('密码错误');
            } else {
                //判断验证码(正式环境验证)
                if (config('app_status') == 'product') {
                    $code = $post['code'];
//                    if ($post['choose'] == 2) {
//
//                    } else {
//                        //手机验证码
//                        if (!$this->smsverify($mobile, $code)) {
//                            return json(['code' => 3, 'msg' => '验证码输入有误，请重新输入']);
//                        }
//                    }
                    if (!$userInfo['last_login_time'] || !$userInfo['google_verify']) {
                        $secret = Redis::getInstance()->get('googlevery' . $username);
                    } else
                        $secret = $userInfo['google_verify'];

                    Loader::import('PHPGangsta.GoogleAuthenticator', EXTEND_PATH);
                    $ga = new \PHPGangsta_GoogleAuthenticator();
                    $checkResult = $ga->verifyCode($secret, $code, 1);
                    if (!$checkResult) {
                        return json(['code' => 3, 'msg' => lang('验证码输入有误，请重新输入')]);
                    }
                }

                session('username', $userInfo['username']);
                session('userid', $userInfo['id']);
                session('session_start_time', time());//记录会话开始时间！判断会话时间的重点！重点！重点！
                if (IsNullOrEmpty($userInfo['PackID'])) $userInfo['PackID'] = "0";
                session('PackID', $userInfo['PackID']);
                if (false) {//记住我//$post['remember'] == 1
                    $salt = random_str(16);
                    //第二分身标识
                    $identifier = md5($salt . $userInfo['username'] . $salt);
                    //永久登录标识
                    $token = md5(uniqid(rand(), true));
                    //永久登录超时时间(1周)
                    $expire = 7 * 24 * 3600;
                    $timeout = sysTime() + $expire;
                    //存入cookie
                    cookie('auth', "$identifier:$token", $expire);
                    $save_data = [
                        'last_login_ip' => $_SERVER['REMOTE_ADDR'],
                        'last_login_time' => date('Y-m-d H:i:s', time()),
                        'token' => $token,
                        'identifier' => $identifier,
                        'timeout' => $timeout,
                        'session_id' => session_id()
                    ];

                    if (!empty($secret)) {
                        if (!$userInfo['last_login_time'] || !$userInfo['google_verify']) {
                            $save_data['google_verify'] = $secret;
                        }
                    }

                    Db::name('user')
                        ->where('username', $post['username'])
                        ->update();
                } else {
                    $save_data = [
                        'last_login_ip' => $_SERVER['REMOTE_ADDR'],
                        'last_login_time' => date('Y-m-d H:i:s', time()),
                        'session_id' => session_id()
                    ];

                    if (!empty($secret)) {
                        if (!$userInfo['last_login_time'] || !$userInfo['google_verify']) {
                            $save_data['google_verify'] = $secret;
                        }
                    }
                    Db::name('user')
                        ->where('username', $post['username'])
                        ->update($save_data);
                }
                return json(['code' => 0]);

            }
        }
    }


    //是否记住我
    public function checkLong()
    {
        $isLong = $this->checkRemember();
        if ($isLong === false) {

        } else {
            session("username", $isLong['username']);
            session("userid", $isLong['id']);
            Db::name('user')
                ->where('id', $isLong['id'])
                ->update([
                    'session_id' => session_id()
                ]);
        }
    }

    //验证用户是否永久登录（记住我）
    public function checkRemember()
    {
        $arr = array();
        $now = sysTime();
        $auth = cookie('auth');
//        var_dump($auth);
//        die;
        if (!$auth) {
            return false;
        }
        list($identifier, $token) = explode(':', $auth);

        if (ctype_alnum($identifier) && ctype_alnum($token)) {
            $arr['identifier'] = $identifier;
            $arr['token'] = $token;
        } else {
            return false;
        }

        $userModel = new userModel();
        $info = $userModel->getRow(['identifier' => $arr['identifier']]);
        if ($info != null) {
            //账号禁用判断
            if ($info['status'] != 1) {
                return false;
            }
            if ($arr['token'] != $info['token']) {
                return false;
            } else if ($now > $info['timeout']) {
                return false;
            } else {
                return $info;
            }
        } else {
            return false;
        }
    }

    //注销
    public function logOut()
    {
        session('username', null);
        session('userid', null);
        //session(null);
        cookie('username', null);
        cookie('auth', null);
        $domain = $this->request->domain();
        $domain .= "/admin/user/index";
        echo "<script>parent.location.href = '" . $domain . "'</script>";
        die;
    }

    public function userlist()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $username = trim(input('username')) ? trim(input('username')) : '';
            $where = [];
            $where['a.username'] = ['like', '%' . $username . '%'];
            $count = Db::name('User')
                ->alias('a')
                ->join('game_auth_group_access b', 'b.uid=a.id', 'LEFT')
                ->join('game_auth_group c', 'c.id=b.group_id')
                ->field('a.PackID,a.username, a.id, a.mobile, a.status, a.create_time, a.last_login_time, c.title')
                ->where($where)
                ->count();
            $data = Db::name('User')
                ->alias('a')
                ->join('game_auth_group_access b', 'b.uid=a.id', 'LEFT')
                ->join('game_auth_group c', 'c.id=b.group_id')
                ->field('a.PackID,a.username, a.id, a.mobile, a.status, a.create_time, a.last_login_time, c.title ')
                ->where($where)
                ->order('id asc')
                ->page($page, $limit)
                ->select();

            foreach ($data as $k => &$v) {
                $v['title'] = lang($v['title']);
            }
            unset($v);
            return json(['code' => 0, 'data' => $data, 'msg' => '', 'count' => $count, 'sql' => Db::getlastSql()]);
        }
        return $this->fetch();
    }


    //增加用户
    public function addUser()
    {
        $request = $this->request;
        if ($request->isPost()) {
            $post = $request->post();
            $group_id = $post['group_id'];
            unset($post['group_id']);
            $validate = validate('User');
            $res = $validate->check($post);
            if ($res !== true) {
                $this->error($validate->getError());
            } else {

                unset($post['check_password']);
                $salt = generateSalt();
                $post['password'] = md5($salt . $post['password']);
                $post['salt'] = $salt;
                $post['last_login_ip'] = '0.0.0.0';
                $post['create_time'] = date('Y-m-d H:i:s', time());
                $db = Db::name('user')->insert($post);
                $userId = Db::name('user')->getLastInsID();
                Db::name('auth_group_access')
                    ->insert(['uid' => $userId, 'group_id' => $group_id]);


                $logData = [
                    'userid' => session('userid'),
                    'username' => session('username'),
                    'action' => __METHOD__,
                    'request' => json_encode($post),
                    'logday' => date('Ymd'),
                    'recordtime' => date('Y-m-d H:i:s'),
                    'status' => 1
                ];
                GameLog::log($logData);
                $this->success('success');
            }
        } else {
            $auth_group = Db::name('auth_group')
                ->field('id,title')
                ->where('status', 1)
                ->order('id desc')
                ->select();
            return $this->fetch('add', ['auth_group' => $auth_group]);
        }

    }

    //编辑提交
    public function editUser($id)
    {
        $request = $this->request;
        if ($request->isPost()) {
            $post = $this->request->post();
            if ($post['id'] == 1) {
                if (session('userid') !== 1) $this->error('系统管理员无法修改');
            }
            $group_id = $post['group_id'];
            $curr_group_id = Db::table('game_auth_group_access')->where('uid',session('userid'))->value('group_id');
            if ($curr_group_id != 1 && $group_id == 1) {
                $this->error('没有权限');
            }
            
            unset($post['group_id']);
            $validate = validate('User');
            if (empty($post['password']) && empty($post['check_password'])) {
                $res = $validate->scene('edit')->check($post);
                if ($res !== true) $this->error($validate->getError());
                else {
                    unset($post['password']);
                    unset($post['check_password']);

                    $db = Db::name('user')
                        ->where('id', $post['id'])
                        ->update(
                            [
                                'username' => $post['username'],
                                //'mobile' => $post['mobile'],
                                'PackID' => $post['PackID'], //分包权限
                            ]);

//                    session('PackID', $post['PackIDS']);
                    Db::name('auth_group_access')
                        ->where('uid', $post['id'])
                        ->update(['group_id' => $group_id]);

                    $logData = [
                        'userid' => session('userid'),
                        'username' => session('username'),
                        'action' => __METHOD__,
                        'request' => json_encode($post),
                        'logday' => date('Ymd'),
                        'recordtime' => date('Y-m-d H:i:s'),
                        'status' => 1
                    ];
                    GameLog::log($logData);
                    $this->success('编辑成功');
                }
            } else {
                $res = $validate->scene('editPassword')->check($post);
                if ($res !== true) {
                    $this->error($validate->getError());
                } else {
                    $logData = [
                        'userid' => session('userid'),
                        'username' => session('username'),
                        'action' => __METHOD__,
                        'request' => json_encode($post),
                        'logday' => date('Ymd'),
                        'recordtime' => date('Y-m-d H:i:s'),
                        'status' => 1
                    ];
                    Db::name('auth_group_access')
                        ->where('uid', $post['id'])
                        ->update(['group_id' => $group_id]);
                    unset($post['check_password']);
                    unset($post['']);
                    $salt = generateSalt();
                    $post['password'] = md5($salt . $post['password']);
                    $post['salt'] = $salt;
                    $db = Db::name('user')
                        ->where('id', $post['id'])
                        ->update($post);
                    GameLog::log($logData);
                    $this->success('编辑成功');
                }
            }
        } else {
            $data = Db::name('User')->alias('a')->join('auth_group_access b', 'b.uid=a.id', 'left')
                ->field('a.*,b.group_id')->where('id', $id)->find();
            $auth_group = Db::name('auth_group')->field('id,title')->order('id desc')->select();

//            $Packlist = Main::GetRoomList(); //取得分包列表 OperatorId PackageName
            $userpack = explode(',', $data['PackID']); //角色表的PackID 转数组
            //分包授权 勾选
//            foreach ($Packlist as $key => &$value) {
//                in_array($value['OperatorId'], $userpack) ? $Packlist[$key]['checked'] = " checked " : $Packlist[$key]['checked'] = "";
//            }

//            $this->assign('PackList', $Packlist);
            $this->assign('auth_group', $auth_group);
            $this->assign('data', $data);
            return $this->fetch('edit');
        }

    }

    //删除用户
    public function deleteUser()
    {
        $id = $this->request->post('id');
        $username = Db::name('user')
            ->where('id', $id)
            ->value('username');
        if ((int)$id !== 1) {
            if ($username !== session('username')) {
                $db = Db::name('user')
                    ->where('id', $id)
                    ->delete();
                $logData = [
                    'userid' => session('userid'),
                    'username' => session('username'),
                    'action' => __METHOD__,
                    'request' => json_encode($this->request->post()),
                    'logday' => date('Ymd'),
                    'recordtime' => date('Y-m-d H:i:s'),
                    'status' => 1
                ];
                GameLog::log($logData);
                $this->success('删除成功');
            } else {
                $this->error('无法删除当前登录用户');
            }
        } else {
            $this->error('超级管理员无法删除');
        }
    }

    public function sendSms()
    {
        $mobile = input('mobile');
        if (!$mobile) {
            return json(['code' => 1, 'msg' => '参数错误！']);
        }
        $num = randnum();

        $res = Api::getInstance()->sendRequest([
            'mobile' => $mobile,
            'code' => $num,
            'ipaddress' => getClientIP(),
            'timestamp' => sysTime()
        ], 'syncdata', 'sendsms');
        if ($res['code'] == 0 && $res['data']) {
            return json(['code' => 0, 'msg' => '发送成功！']);
        } else {
            return json(['code' => 1, 'msg' => '发送失败，请稍后再试！']);
        }
    }

    public function smsverify($mobile, $code)
    {
        $res = Api::getInstance()->sendRequest([
            'mobile' => $mobile,
            'code' => $code
        ], 'syncdata', 'checksms');
        return ($res['code'] == 0 && $res['data']) ? true : false;
    }

    //ip白名单add_whitelistip
    public function whitelistip()
    {
        if ($this->request->isAjax()) {

            $where = [];
            if (input('ip')) {
                $where = ["ip" => trim(input('ip'))];
            }

            $page = $this->request->get('page') ? intval($this->request->get('page')) : 1;
            $limit = $this->request->get('limit') ? intval($this->request->get('limit')) : 10;
            $data = Db::name('ip')
                ->where($where)
                ->field("*")
                ->page($page, $limit)
                ->select();
            $count = Db::name('ip')
                ->where($where)
                ->count();
            return $this->apiReturn(0, $data, $data, $count);

        }
        return $this->fetch();
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

    //添加ip白名单add_whitelistip
    public function addWhite()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $post = [];
            $post['ip'] = $request["ip"];
            $post['remark'] = $request["remark"];
            $post['last_login_time'] = date('Y-m-d H:i:s');
            $post['create_time'] = date('Y-m-d H:i:s');
            $post['type'] = '';


            $where = [];
            if (input('ip')) {
                $where = ["ip" => trim(input('ip'))];
            }
            $resfind = Db::name('ip')
                ->where($where)
                ->field("id")
                ->find();
            if ($resfind) {
                return json(['code' => 0, 'msg' => lang('ip已添加到白名单')]);
            }

            $res = Db::name('ip')->insert($post);
            if ($res) {
                return json(['code' => 0, 'msg' => lang('添加成功')]);
            } else {
                return json(['code' => 5, 'msg' => lang('添加失败')]);
            }

        }
        return $this->fetch();
    }


    public function editWhite()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $res = Db::name('ip')
                ->where('id', $request['id'])
                ->update([
                    'ip' => $request['ip'],
                    'remark' => $request['remark'],
                ]);
            if ($res) {
                return 0;
            } else {
                return 3;
            }
        }

        $id = input('id');
        $ip = input('ip');
        $remark = input('remark');
//        $ip   = intval(input('rate')) ? intval(input('rate')) : 0;
        $this->assign('id', $id);
        $this->assign('ip', $ip);
        $this->assign('remark', $remark);
        return $this->fetch();
    }

    public function deleteWhite()
    {
        $request = $this->request->request();

        $res = Db::name('ip')->where('id', $request['id'])->delete();
        if ($res) {
            return 0;
        } else {
            return 3;
        }
    }


    public function unbindgoogle()
    {
        $id = input('id', 0);
        $user = Db::name('user')
            ->where('id', $id)->find();

        if (!empty($user['google_verify']) || $user['google_verify'] != '') {
            $result = Db::name('user')->where(['id' => $id])->update(['google_verify' => '']);
        }
        return json(['code' => 1, 'msg' => lang('解绑成功')]);
    }
}
