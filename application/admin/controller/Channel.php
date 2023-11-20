<?php

namespace app\admin\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\UserDB;
use app\model\GameOCDB;
use think\Db;
use think\Exception;

class Channel extends Main
{

    private $db2;

    public function __construct()
    {
        parent::__construct();
        $this->db2 = config('database_qd.database');
    }

    /**
     * 新增
     */
    public function addchannel()
    {
        if ($this->request->isAjax()) {
            //登录权限
            $status = trim(input('status')) ? trim(input('status')) : '';
            //查看用户游戏权限
            $auth     = trim(input('auth')) ? trim(input('auth')) : '';
            $password = trim(input('password')) ? trim(input('password')) : '';
            $username = trim(input('username')) ? trim(input('username')) : '';
            $nickname = trim(input('nickname')) ? trim(input('nickname')) : '';
            $phone    = trim(input('phone')) ? trim(input('phone')) : '';
            //$qudao    = trim(input('qudao')) ? trim(input('qudao')) : '';
            $request = $this->request->request();

            if (!$password || !$username || !$phone || !$nickname) {
                return $this->apiReturn(1, [], '缺少信息请补全');
            }
            if (!checkmobile($phone)) {
                return $this->apiReturn(1, [], '手机号格式有误');
            }
            $status = $status == 'on' ? 1 : 0;
            $auth   = $auth == 'on' ? 1 : 0;

            $usermodel = Db::connect($this->db2)->name('game_user');
            if ($usermodel->where(['username' => $username])->count()) {
                return $this->apiReturn(2, [], '用户名已存在，请重新输入');
            }
            if ($usermodel->where(['mobile' => $phone])->count()) {
                return $this->apiReturn(3, [], '手机号已存在，请重新输入');
            }
            $salt = generateSalt();
            Db::connect($this->db2)->startTrans();
            try {
                $res = $usermodel->insertGetId([
                    'username'    => $username,
                    'password'    => md5($salt . $password),
                    'salt'        => $salt,
                    'mobile'      => $phone,
                    'status'      => $status,
                    'auth'        => $auth,
                    'nickname'    => $nickname,
                    'create_time' => date('Y-m-d H:i:s'),
                    'groupid'     => 2
                ]);

                //默认加入渠道组
                Db::connect($this->db2)->name('game_auth_group_access')
                    ->insertGetId(['uid' => $res, 'group_id' => 2]);

                $qudao = sprintf("wb%06d", $res);
                //生成apk短地址
//                $apkurl = $this->getShorturl($qudao);
//                $usermodel->where('id', $res)->update(["qudao" => $qudao, 'downloadaddress' => $apkurl]);
                $usermodel->where('id', $res)->update(["qudao" => $qudao]);

                $res = Api::getInstance()->sendRequest(['channelid' => $qudao, 'channelname' => $nickname], 'channel', 'addchannel');
                if ($res['code'] == 0 && $res['data']) {
                    Db::connect($this->db2)->commit();
                    GameLog::logData(__METHOD__, $request, 1);
                    return $this->apiReturn(0, [], '添加成功，短地址在后台生成，请稍后再看');
                } else {
                    throw new Exception('api add wrong');
                }
            } catch (Exception $e) {
                Db::connect($this->db2)->rollback();
                GameLog::logData(__METHOD__, $request, 0, $e->getMessage());
                return $this->apiReturn(4, [], '添加失败');
            }
        }

        return $this->fetch();
    }

    /**
     * 平台每日统计Platform Daily Statistics
     */
    public function channelDayStatic()
    {


        if ($this->request->isAjax()) {
            $page        = intval(input('page')) ? intval(input('page')) : 1;
            $limit       = intval(input('limit')) ? intval(input('limit')) : 10;
            $channelid   = trim(input('channelid')) ? trim(input('channelid')) : '';
            $channelname = trim(input('channelname')) ? trim(input('channelname')) : '';
            $startdate   = trim(input('startdate')) ? trim(input('startdate')) : '';
            $enddate     = trim(input('enddate')) ? trim(input('enddate')) : '';
            if ($enddate) {
                $enddate = date('Ymd', (strtotime($enddate) + 3600 * 24));
            }

//            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $orderby = trim(input('orderby')) ? trim(input('orderby')) : 'lastdaykeep';
            $asc     = intval(input('asc')) ? intval(input('asc')) : 0;

            $res     = Api::getInstance()->sendRequest([
                'channelid'   => $channelid,
                'channelname' => $channelname,
                'startdate'   => $startdate,
                'enddate'     => $enddate,
                'asc'         => $asc,
                'orderby'     => $orderby,
                'page'        => $page,
                'pagesize'    => $limit
            ], 'system', 'channeldata');
            if (isset($res['data']['list']) && $res['data']['list']) {

                $countmodel = Db::connect($this->db2)->name('game_count');
                //获取pv uv信息
                $where = [

                    'day' => [['egt', date('Ymd', (strtotime($startdate)))], ['elt', $enddate]]
                ];
                $count = $countmodel->where($where)->select();

                foreach ($res['data']['list'] as &$v) {
                    $v['paytotalnew']   /= 1000;
                    $v['paytotal']      /= 1000;
                    $v['officalcharge'] /= 1000;
                    $v['agentcharge']   /= 1000;
                    $v['totalout']      /= 1000;
                    $v['totalin']       /= 1000;
                    $v['totaltax']      /= 1000;
                    $v['exchangemoney'] /= 1000;

                    $v['lastdaykeep'] = round($v['lastdaykeep'] * 100, 2);
                    $v['bindrate']    = round($v['bindrate'] * 100, 2);
                    $v['payrate']     = round($v['payrate'] * 100, 2);
                    $v['activerate']  = round($v['activerate'] * 100, 2);
                    $v['profitrate']  = round($v['profitrate'] * 100, 2);
                    $v['activekeep']  = round($v['activekeep'] * 100, 2);
                    $v['ip'] = 0;
                    $v['pv'] = 0;
                    $v['uv'] = 0;

                    foreach ($count as $c) {
                        if ($c['day'] == $v['date'] && $c['proxyid'] == $v['channelid']) {
                            $v['ip'] = $c['ip'];
                            $v['pv'] = $c['pv'];
                            $v['uv'] = $c['uv'];
                            break;
                        }
                    }

                }
                unset($v);
            }


            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 'lastdaykeep',
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);

        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function channelDownload() {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $qudao = trim(input('roleid')) ? trim(input('roleid')) : '';

            $where = $qudao ? ['qudao' => $qudao] : [];
            $count = Db::connect($this->db2)->name('game_user')->where($where)->name('game_user')->count();
            $res   = Db::connect($this->db2)->name('game_user')
                ->where($where)
                ->field('id,nickname,qudao,apkaddress,downloadaddress,jscode')
                ->page($page, $limit)
                ->order('id desc')
                ->select();

            return $this->apiReturn(0, $res, '', $count);

        }

        return $this->fetch();
    }

    //微信推广地址
    public function geturl()
    {
        if ($this->request->isAjax()) {
            $qudao   = trim(input('roleid')) ? trim(input('roleid')) : '';
            $urlArr  = [
                'https://www.winbo1.com/?proxyid=',
                'https://www.winbo2.com/?proxyid=',
                'https://www.winbo3.com/?proxyid=',
                'https://www.winbo4.com/?proxyid=',
                'https://www.winbo5.com/?proxyid=',
                'https://www.winbo6.com/?proxyid=',
                'https://www.winbo7.com/?proxyid=',
                'https://www.winbo8.com/?proxyid=',
                'https://www.winbo9.com/?proxyid=',

                'https://www.winbo1.xyz/?proxyid=',
                'https://www.winbo2.xyz/?proxyid=',
                'https://www.winbo3.xyz/?proxyid=',
                'https://www.winbo4.xyz/?proxyid=',
                'https://www.winbo5.xyz/?proxyid=',
                'https://www.winbo6.xyz/?proxyid=',
                'https://www.winbo7.xyz/?proxyid=',
                'https://www.winbo8.xyz/?proxyid=',
                'https://www.winbo9.xyz/?proxyid=',

                'https://www.wanbo1.xyz/?proxyid=',
                'https://www.wanbo2.xyz/?proxyid=',
                'https://www.wanbo3.xyz/?proxyid=',
                'https://www.wanbo4.xyz/?proxyid=',
                'https://www.wanbo5.xyz/?proxyid=',
                'https://www.wanbo6.xyz/?proxyid=',
                'https://www.wanbo7.xyz/?proxyid=',
                'https://www.wanbo8.xyz/?proxyid=',
                'https://www.wanbo9.xyz/?proxyid=',

                'https://www.wanbo0.vip/?proxyid=',
                'https://www.wanbo1.vip/?proxyid=',
                'https://www.wanbo2.vip/?proxyid=',
                'https://www.wanbo3.vip/?proxyid=',
                'https://www.wanbo4.vip/?proxyid=',
                'https://www.wanbo5.vip/?proxyid=',
                'https://www.wanbo6.vip/?proxyid=',
                'https://www.wanbo7.vip/?proxyid=',
                'https://www.wanbo9.vip/?proxyid=',

                'https://www.winbo0.vip/?proxyid=',
                'https://www.winbo1.vip/?proxyid=',
                'https://www.winbo2.vip/?proxyid=',
                'https://www.winbo3.vip/?proxyid=',
                'https://www.winbo4.vip/?proxyid=',
                'https://www.winbo5.vip/?proxyid=',
                'https://www.winbo6.vip/?proxyid=',
                'https://www.winbo7.vip/?proxyid=',
                'https://www.winbo8.vip/?proxyid=',
                'https://www.winbo9.vip/?proxyid=',

                'https://9n1.xyz/?proxyid=',
                'http://zzpixie.com/?proxyid=',
            ];
            $numbers = range(0, count($urlArr) - 1);

//shuffle 将数组顺序随即打乱 

            shuffle($numbers);

//array_slice 取该数组中的某一段 

            $num = 10;

            $result = array_slice($numbers, 0, $num);
            $tmp    = [];
            foreach ($result as $value) {
                $tmp[] = $urlArr[$value];
            }

            foreach ($tmp as &$v) {
                $v .= $qudao;
            }
            unset($v);
//var_dump($tmp);die;
            $arr = Db::connect($this->db2)->name('game_url')->where(['qudao' => $qudao, 'real' => ['in', $tmp]])->select();
            shuffle($arr);
            $xx = '';
            foreach ($arr as $u) {
                $xx .= $u['url'] . '<br>';
            }
            //$v = trim($v, '<br>');

            return $this->apiReturn(0, $xx, 'success');
        }
        $url = input('url');
        $this->assign('url', $url);
        return $this->fetch();
    }

    //获取短信推广地址
    public function geturl1()
    {

        $qudao  = trim(input('roleid')) ? trim(input('roleid')) : '';
        $smsArr = [
            0 => 'https://winbo0.vip/?proxyid=',
            1 => 'https://winbo1.vip/?proxyid=',
            2 => 'https://winbo2.vip/?proxyid=',
            3 => 'https://winbo3.vip/?proxyid=',
            4 => 'https://winbo4.vip/?proxyid=',
            5 => 'https://winbo5.vip/?proxyid=',
            6 => 'https://winbo6.vip/?proxyid=',
            7 => 'https://winbo7.vip/?proxyid=',
            8 => 'https://winbo8.vip/?proxyid=',
            9 => 'https://winbo9.vip/?proxyid=',
        ];

        $v = '';
        foreach ($smsArr as $u) {
            $v .= $u . $qudao . '<br>';
        }
        // $v = trim($v, '<br>');

        return $this->apiReturn(0, $v, 'success');
    }


    //获取其他推广地址
    public function geturl2()
    {

        $qudao    = trim(input('roleid')) ? trim(input('roleid')) : '';
        $otherArr = [
            0 => 'https://wanbo0.vip/?proxyid=',
            1 => 'https://wanbo1.vip/?proxyid=',
            2 => 'https://wanbo2.vip/?proxyid=',
            3 => 'https://wanbo3.vip/?proxyid=',
            4 => 'https://wanbo4.vip/?proxyid=',
            5 => 'https://wanbo5.vip/?proxyid=',
            6 => 'https://wanbo6.vip/?proxyid=',
            7 => 'https://wanbo7.vip/?proxyid=',
            9 => 'https://wanbo9.vip/?proxyid=',
        ];

        $v = '';
        foreach ($otherArr as $u) {
            $v .= $u . $qudao . '<br>';
        }
        // $v = trim($v, '<br>');

        return $this->apiReturn(0, $v, 'success');
    }


    public function editdownload()
    {

        if ($this->request->isAjax()) {

            $qudao       = trim(input('qudao')) ? trim(input('qudao')) : '';
            $channelname = trim(input('channelname')) ? trim(input('channelname')) : '';
            $where       = ["qudao" => $qudao];
            $data        = array("nickname" => $channelname);
            $request     = $this->request->request();
            Db::connect($this->db2)->startTrans();
            try {
                Db::connect($this->db2)->name('game_user')->where($where)->update($data);
                $res = Api::getInstance()->sendRequest(['channelid' => $qudao, 'channelname' => $channelname], 'channel', 'addchannel');
                if ($res['code'] == 0 && $res['data']) {
                    Db::connect($this->db2)->commit();
                    GameLog::logData(__METHOD__, $request, 1);
                    return $this->apiReturn(0, [], '更新成功');
                } else {
                    throw new Exception('api add wrong');
                }
            } catch (Exception $e) {
                Db::connect($this->db2)->rollback();
                GameLog::logData(__METHOD__, $request, 0, $e->getMessage());
                return $this->apiReturn(1, [], '更新失败');
            }

        }

        $id          = $_GET['id'];
        $channelname = $_GET['channelname'];
        $this->assign('qudao', $id);
        $this->assign('channelname', $channelname);
        return $this->fetch();
    }

    //重置短地址
    public function reseturl()
    {
        $qudao = trim(input('qudao')) ? trim(input('qudao')) : '';
        if (!$qudao) {
            return $this->apiReturn(1, [], '处理失败');
        }
        $where = ["qudao" => $qudao];
        Db::connect($this->db2)->startTrans();
        try {
            Db::connect($this->db2)->name('game_user')->where($where)->update(['isrun' => 0]);
            Db::connect($this->db2)->name('game_url')->where($where)->delete();
            Db::connect($this->db2)->commit();
            GameLog::logData(__METHOD__, $this->request->request(), 1);
            return $this->apiReturn(0, [], '处理成功，请稍后查看生成情况');
        } catch (Exception $e) {
            Db::connect($this->db2)->rollback();
            return $this->apiReturn(1, [], '处理失败');
        }
    }


    //生成短地址
    public function getShorturl($url)
    {
        $apiurl = 'https://v94.cn/api/SingleShortUrl.json?appid=510340223&appkey=cb071b0b0442ac949125e5ecf1aa43b6&type=add&url=' . $url . '&visit_type=browser&title=title1&keywords=keywords2&description=description3';
        $res    = file_get_contents($apiurl);
        $res    = json_decode($res, true);

        if ($res['code'] == 1 && isset($res['data']['weibo_shorturl'])) {
            //成功
            return $res['data']['weibo_shorturl'];
        } else if ($res['code'] == 1401) { //已经添加过了
            //删掉重新生成
            $apiurl = 'https://v94.cn/api/SingleShortUrl.json?appid=510340223&appkey=cb071b0b0442ac949125e5ecf1aa43b6&type=delete&url=' . $url;
            $res    = file_get_contents($apiurl);
            $res    = json_decode($res, true);
            if ($res['code'] == 1) {
                //删除成功 新增
                return $this->getShorturl($url);
            } else {
                return '';
            }
        } else {
            return '';
        }
    }



    //分发管理
    public function fenfa()
    {
        if ($this->request->isAjax()) {
            $list = Db::name('fenfa')->select();
            return $this->apiReturn(0, $list, '', count($list));
        }

        return $this->fetch();
    }

    //新增分发
    public function addfenfa()
    {
        if ($this->request->isAjax()) {
            $key      = input('key') ? trim(input('key')) : '';
            $descript = input('descript') ? trim(input('descript')) : '';
            if (!$key || !$descript) {
                return $this->apiReturn(1, [], '参数有误');
            }
            if (Db::name('fenfa')->where(['key' => $key])->count()) {
                return $this->apiReturn(2, [], '该渠道已存在，请勿重复添加');
            }
            $res = Db::name('fenfa')->insertGetId([
                'key'        => $key,
                'descript'   => $descript,
                'updatetime' => date('Y-m-d H:i:s')
            ]);
            if (!$res) {
                return $this->apiReturn(3, [], '添加失败');
            }
            return $this->apiReturn(0, [], '添加成功');
        }

        return $this->fetch();
    }

    //删除分发
    public function delfenfa()
    {
        if ($this->request->isAjax()) {
            $id = input('id') ? intval(input('id')) : 0;
            if (!$id) {
                return $this->apiReturn(1, [], '参数有误');
            }

            $res = Db::name('fenfa')->where(['id' => $id])->delete();
            if (!$res) {
                return $this->apiReturn(3, [], '删除失败');
            }
            return $this->apiReturn(0, [], '删除成功');
        }

        return $this->fetch();
    }

    //编辑分发
    public function editfenfa()
    {
        if ($this->request->isAjax()) {
            $id       = input('id') ? intval(input('id')) : 0;
            $key      = input('key') ? trim(input('key')) : '';
            $descript = input('descript') ? trim(input('descript')) : '';
            if (!$id || !$key || !$descript) {
                return $this->apiReturn(1, [], '参数有误');
            }

            $info = Db::name('fenfa')->where(['id' => $id])->find();
            if (!$info) {
                return $this->apiReturn(2, [], '参数有误');
            }
            if ($key == $info['key'] && $descript == $info['descript']) {
                return $this->apiReturn(3, [], '未做更新');
            }
            $res = Db::name('fenfa')
                ->where(['id' => $id])
                ->update([
                    'key'        => $key,
                    'descript'   => $descript,
                    'updatetime' => date('Y-m-d H:i:s')
                ]);
            if (!$res) {
                return $this->apiReturn(3, [], '更新失败');
            }
            return $this->apiReturn(0, [], '更新成功');
        }

        $id       = input('id');
        $key      = input('key');
        $descript = input('descript');
        $this->assign('id', $id);
        $this->assign('key', $key);
        $this->assign('descript', $descript);
        return $this->fetch();
    }

    //更新分发状态
    public function updatefenfa()
    {

        $id    = input('id') ? intval(input('id')) : 0;
        $value = input('value') ? intval(input('value')) : 0;
        if (!$id || !in_array($value, [0, 1])) {
            return $this->apiReturn(1, [], '参数有误');
        }

        $res = Db::name('fenfa')->where(['id' => $id])->update(['id' => $id, 'value' => $value, 'updatetime' => date('Y-m-d H:i:s')]);
        if (!$res) {
            return $this->apiReturn(3, [], '更新失败');
        }
        return $this->apiReturn(0, [], '更新成功');
    }

    //渠道列表
    public function channelList(){
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit    = $this->request->param('limit')?:15;
            $ProxyId    = $this->request->param('ProxyId');
            $RoleID  = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $where = "a.type=0";
            if (config('is_portrait')==1) {
                $RoleName = 'ProxyChannelId';
            } else {
                $RoleName = 'RoleID';
            }
            if ($ProxyId != '') {
                $where .= " and a.ProxyId='".$ProxyId."'";
            }
            if ($RoleID != '') {
                $where .= " and a.".$RoleName."=".$RoleID;
            }
            if ($ChannelID != '') {
                $where .= " and a.".$RoleName."=".$ChannelID;
            }
            $m = new \app\model\GameOCDB();
            $data = $m->getTableObject('T_ProxyChannelConfig')->alias('a')
                     ->join('[CD_UserDB].[dbo].[T_UserProxyInfo] b', 'a.'.$RoleName.'=b.RoleID', 'LEFT')
                     ->where($where)
                     ->field('a.*,b.InviteCode')
                     ->order('ProxyId desc')
                     ->paginate($limit)
                     ->toArray();
            $m = new \app\model\MasterDB();
            $InviteUrlModel = $m->getTableObject('T_GameConfig')->where('CfgType',113)->value('keyValue');
            foreach ($data['data'] as $key => &$val) {
                if (config('is_portrait') == 1) {
                    $val['InviteUrl'] = str_replace("{inviteCode}", $val[$RoleName], $InviteUrlModel);
                } else {
                    $val['InviteUrl'] = str_replace("{inviteCode}", $val['InviteCode'], $InviteUrlModel);
                }
                
                
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        $list= (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->order('ProxyId desc')->select();
        $this->assign('oplist',$list);
        if (config('is_portrait')==1) {
            return $this->fetch('channel_list_s');
        } else {
            return $this->fetch();
        }
        
    }

    public function channelEdit(){
        if (config('is_portrait')==1) {
            $RoleName = 'ProxyChannelId';
        } else {
            $RoleName = 'RoleID';
        }
        if ($this->request->method() == 'POST') {
            $ProxyId     = $this->request->param('ProxyId');
            $AccountName = $this->request->param('AccountName');
            $m = new \app\model\GameOCDB();
            $RoleID = $this->request->param('RoleID');
            
            if($RoleID){
                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where($RoleName,$RoleID)
                    ->data(['ProxyId'=>$ProxyId,'AccountName'=>$AccountName])
                    ->update();
                if (!$res) {
                     return $this->apiReturn(1,'','编辑失败');
                }
                 return $this->apiReturn(0,'','编辑成功');
            } else {
                // $has_ProxyId = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyId',$ProxyId)->find();
                // if ($has_ProxyId) {
                //     return $this->apiReturn(1,'','推广D已存在');
                // }
                $has_AccountName = $m->getTableObject('T_ProxyChannelConfig')->where('AccountName',$AccountName)->find();
                if ($has_AccountName) {
                    return $this->apiReturn(1,'','名称已存在');
                }
                //添加渠道信息
                $roleID = $this->getRoleId();
                $ProxyId = $ProxyId ?: $roleID;
                $inviteCode = createRandCode(6);
                $SecretKey = createRandCode(18);
                $data['RoleID'] = $roleID;
                $data['InviteCode'] = $inviteCode;
                $data['CorpType'] = 3;

                $ret = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo')->insert($data);
                if ($ret) {
                    // $InviteUrl = str_replace("{inviteCode}", $inviteCode, $InviteUrl);
                    // $password = md5($post['NewPassWord']?: '');
                    $conf[$RoleName] = $roleID;
                    $conf['ProxyId'] = $ProxyId;
                    $conf['AccountName'] = $AccountName;
                    $conf['PassWord'] = "";
                    $conf['DownloadUrl'] = "";
                    $conf['ShowUrl'] = "";
                    $conf['LandingPageUrl'] = "";
                    $confUpdate['ChannelDownloadUrl'] = "";
                    $conf['ChannelShowUrl'] = "";
                    $conf['OperatorId'] = 888888;
                    $conf['InviteUrl'] = $inviteCode;
                    $res = $m->getTableObject('T_ProxyChannelConfig')->insert($conf);
                    if (!$res) {
                         return $this->apiReturn(1,'','添加失败');
                    }
                     return $this->apiReturn(0,'','添加成功');
                } else {
                     return $this->apiReturn(1,'','添加失败');
                }
            }
        }
        $RoleID = $this->request->param('RoleID');
        if($RoleID){
            $m = new \app\model\GameOCDB();
            $data = $m->getTableObject('T_ProxyChannelConfig')->where($RoleName,$RoleID)->find();
        } else {
            $data = [];
        }
        $this->assign('data',$data);
        if (config('is_portrait')==1) {
            return $this->fetch('channel_edit_s');
        } else {
            return $this->fetch();
        }
    }

    public function getRoleId()
    {
        $roleId = mt_rand(500000,599999);
        $m = new \app\model\UserDB();
        $isExist = $m->getTableObject('T_UserProxyInfo')->where('RoleID='.$roleId)->find();
        if ($isExist){
            return $this->getRoleId();
        }
        return $roleId;
    }

    public function setDefaultChannel(){
        $RoleID = $this->request->param('RoleID');
        if (config('is_portrait')==1) {
            $RoleName = 'ProxyChannelId';
        } else {
            $RoleName = 'RoleID';
        }
        $type = $this->request->param('type');
        $m = new \app\model\GameOCDB();
        $res1 = $m->getTableObject('T_ProxyChannelConfig')
                        ->where($RoleName.'>0')
                        ->data(['isDefault'=>0])
                        ->update();           
        $res = $m->getTableObject('T_ProxyChannelConfig')
                     ->where($RoleName.'='.$RoleID)
                        ->data(['isDefault'=>$type])
                        ->update();
        if ($res) {
            return ['code'=>0];
        } else {
            return ['code'=>1];
        }
    }


    //渠道统计
    public function channelStatistics()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit')?:15;

            $RoleID = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $proxyId = $this->request->param('ProxyId');
            $start_date = $this->request->param('strartdate');
            $end_date = $this->request->param('enddate');
            $reg_ip = $this->request->param('reg_ip');
            $last_ip = $this->request->param('last_ip');
            $is_recharge = $this->request->param('is_recharge');
            $where = "1=1";
            if (!empty($RoleID)) {
                 $where .= "and a.RoleID=".$RoleID;
            }
            // if (!empty($ChannelID)) {
            //      $where .= "and a.ChannelID=".$ChannelID;
            // }
            // if (!empty($ProxyId)) {
            //      $where .= "and c.ProxyId='".$ProxyId."'";
            // }
            if ($start_date != '') {
               $where .= ' and b.RegisterTime>=\''.$start_date.'\'';
           }
           if ($end_date != '') {
                $where .= ' and b.RegisterTime<\''.$end_date.'\'';
           }
           if ($reg_ip != '') {
               $where .= ' and b.RegIP=\''.$reg_ip.'\'';
           }
           if ($last_ip != '') {
               $where .= ' and b.LastLoginIP=\''.$last_ip.'\'';
           }
           if ($is_recharge == 1) {
               $where .= ' and b.TotalDeposit>0';
           }
            $gameOCDB = new GameOCDB();
            $default_Proxy = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->find() ?: [];
            $default_Proxy['RoleID'] = $default_Proxy['RoleID'] ?? '';
            $default_Proxy['ProxyId'] = $default_Proxy['ProxyId'] ?? '';
            $default_Proxy['AccountName'] = $default_Proxy['AccountName'] ?? '';

            $default_ProxyId = $default_Proxy['ProxyId'] ?? $default_Proxy['ProxyId'];
            if ($proxyId>0) {
                $proxy_roleid = $gameOCDB->getProxyChannelConfig()->getValueByTable('ProxyId=\'' . $proxyId . '\'', 'RoleID');
                if ($default_ProxyId == $proxyId) {
                    $where .= " and (b.ParentID=0 or b.ParentID='$proxy_roleid')";
                } else {
                    if (!empty($proxy_roleid)) {
                        $where .= " and b.ParentID='$proxy_roleid'";
                    } else {
                        $where .= " and b.ParentID=" . $proxyId;
                    }
                }
            }
            if (!empty($ChannelID)) {
                if ($default_ProxyId == $ChannelID) {
                   $where .= " and (b.ParentID=0 or b.ParentID='$ChannelID')";
                } else {
                    $where .= " and b.ParentID='$ChannelID'";
                }
            }
            $m = new \app\model\UserDB();
            $field = "a.ChannelID,c.ProxyId,c.AccountName,b.AccountID,b.Mobile,b.TotalDeposit,b.TotalRollOut,b.Money,b.RegisterTime";
            $data = $m->getTableObject('View_UserTeam')->alias('a')
                ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID','LEFT')
                ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID','LEFT')
                ->where($where)
                ->field($field)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                $val['recharge_times'] = $m->getTableObject('T_UserTransactionChannel')->where('AccountID',$val['AccountID'])->count()?:0;
                // $val['TotalDeposit'] = FormatMoney($val['TotalDeposit']);
                // $val['TotalRollOut'] = FormatMoney($val['TotalRollOut']);
                $val['Money'] = FormatMoney($val['Money']);
                $val['TotalProfit']  = bcsub($val['TotalDeposit'],$val['TotalRollOut'],3);
                $val['ChannelID'] = $val['ChannelID'] ?: $default_Proxy['RoleID'];
                $val['ProxyId'] = $val['ProxyId'] ?: $default_Proxy['ProxyId'];
                $val['AccountName'] = $val['AccountName'] ?: $default_Proxy['AccountName'];
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                $other = [];
                $field = "ISNULL(sum(b.TotalDeposit),0)TotalDeposit,ISNULL(sum(b.TotalRollOut),0)TotalRollOut,ISNULL(sum(b.Money),0)Money";
                $other = $m->getTableObject('View_UserTeam')->alias('a')
                    ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID','LEFT')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID','LEFT')
                    ->where($where)
                    ->field($field)
                    ->select()[0];

                $other['total_num'] = $data['total'];
                $other['first_recharge_num'] = count($m->getTableObject('View_UserTeam')->alias('a')
                    ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID','LEFT')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID','LEFT')
                    ->where($where)
                    ->where('b.TotalDeposit>0')
                    ->group('a.RoleID')
                    ->field('a.RoleID')
                    ->select())?:0;
                 $other['first_recharge_amount'] = $m->getTableObject('View_UserTeam')->alias('a')
                    ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID','LEFT')
                    ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] d','d.RoleID=a.RoleID','LEFT')
                    ->where($where)
                    ->where('d.IfFirstCharge=1 and d.ChangeType=5')
                    ->sum('d.ChangeMoney')?:0;

                 //充值次数
                $other['total_recharge_num'] = count($m->getTableObject('View_UserTeam')->alias('a')
                    ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID','LEFT')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID','LEFT')
                    ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d','d.AccountID=a.RoleID','LEFT')
                    ->where($where)
                    ->field('d.*')
                    ->select())?:0;
                //充值人数
                $other['total_recharge_people'] = count($m->getTableObject('View_UserTeam')->alias('a')
                    ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.RoleID')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelID')
                    ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d','d.AccountID=a.RoleID')
                    ->where($where)
                    ->group('a.RoleID')
                    ->field('a.RoleID')
                    ->select())?:0;
                if ($other['TotalDeposit'] == 0 || $other['total_recharge_people'] == 0) {
                    $other['Money'] = 0;
                } else {
                    $other['Money'] = bcdiv($other['TotalDeposit'], $other['total_recharge_people'],3);
                }
                // $other['TotalDeposit'] = FormatMoney($other['TotalDeposit']);
                // $other['TotalRollOut'] = FormatMoney($other['TotalRollOut']);
                // $other['Money'] = FormatMoney($other['Money']);
                $other['TotalProfit'] = bcsub($other['TotalDeposit'],$other['TotalRollOut'],3);
                $other['first_recharge_amount'] = FormatMoney($other['first_recharge_amount']);
                return $this->apiReturn(0, $data['data'], 'success', $data['total'],$other);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                if (empty($data['data'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['data'];
                $result['count'] = $data['total'];
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('渠道ID') => 'string', 
                        lang('渠道名称') => 'string',
                        lang('代理ID') => 'string',
                        lang('玩家ID') => "string",
                        lang('手机号') => "string",
                        lang('充值次数') => "string",
                        lang('充值金额') => "string",
                        lang('提现金额') => "string",
                        lang('携带金币') => "string",
                        lang('盈利') => "string",
                        lang('注册时间') => "string",
                    ];
                    $filename = lang('渠道统计').'-' . date('YmdHis');
                    $rows =&$result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['ProxyId'],
                            $row['AccountName'],
                            $row['ChannelID'],
                            $row['AccountID'],
                            $row['Mobile'],
                            $row['recharge_times'],
                            $row['TotalDeposit'],
                            $row['TotalRollOut'],
                            $row['Money'],
                            $row['TotalProfit'],
                            $row['RegisterTime'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        }
        if (config('is_portrait')==1) {
            return $this->fetch('channel_statistics_s');
        } else {
            return $this->fetch();
        }
    }


    //渠道统计-竖版
    public function schannelStatistics()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit')?:15;

            $RoleID = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $proxyId = $this->request->param('ProxyId');
            $start_date = $this->request->param('strartdate');
            $end_date = $this->request->param('enddate');
            $reg_ip = $this->request->param('reg_ip');
            $last_ip = $this->request->param('last_ip');
            $is_recharge = $this->request->param('is_recharge');
            $where = "a.GmType not in(0)";
            if (!empty($RoleID)) {
                 $where .= "and a.AccountID=".$RoleID;
            }
            // if (!empty($ChannelID)) {
            //      $where .= "and a.ChannelID=".$ChannelID;
            // }
            // if (!empty($ProxyId)) {
            //      $where .= "and c.ProxyId='".$ProxyId."'";
            // }
            if ($start_date != '') {
               $where .= ' and a.RegisterTime>=\''.$start_date.'\'';
           }
           if ($end_date != '') {
                $where .= ' and a.RegisterTime<\''.$end_date.'\'';
           }
           if ($reg_ip != '') {
               $where .= ' and a.RegIP=\''.$reg_ip.'\'';
           }
           if ($last_ip != '') {
               $where .= ' and a.LastLoginIP=\''.$last_ip.'\'';
           }
           if ($is_recharge == 1) {
               $where .= ' and a.TotalDeposit>0';
           }
            $gameOCDB = new GameOCDB();
            $default_Proxy = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->find() ?: [];
            $default_Proxy['ProxyChannelId'] = $default_Proxy['ProxyChannelId'] ?? '';
            $default_Proxy['AccountName'] = $default_Proxy['AccountName'] ?? '';


            if (!empty($ChannelID)) {
                if ($default_Proxy['ProxyChannelId'] == $ChannelID) {
                   $where .= " and (a.ProxyChannelId=0 or a.ProxyChannelId='$ChannelID')";
                } else {
                    $where .= " and a.ProxyChannelId='$ChannelID'";
                }
            }
            $m = new \app\model\UserDB();
            $field = "a.ProxyChannelId,c.AccountName,a.AccountID,a.TotalDeposit,a.TotalRollOut,a.Money,a.RegisterTime,d.recharge_times";

            $recharge_sql = "(SELECT AccountID,count(*) recharge_times from T_UserTransactionChannel(NOLOCK) group by AccountID) as d";
            $data = $m->getTableObject('View_Accountinfo')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                ->join($recharge_sql,'d.AccountID=a.AccountID','LEFT')
                ->where($where)
                ->field($field)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                // $val['recharge_times'] = $m->getTableObject('T_UserTransactionChannel')->where('AccountID',$val['AccountID'])->count()?:0;
                // $val['TotalDeposit'] = FormatMoney($val['TotalDeposit']);
                // $val['TotalRollOut'] = FormatMoney($val['TotalRollOut']);
                $val['Money'] = FormatMoney($val['Money']);
                $val['TotalProfit']  = bcsub($val['TotalDeposit'],$val['TotalRollOut'],3);
                $val['ChannelID'] = $val['ProxyChannelId'] ?: $default_Proxy['ProxyChannelId'];
                $val['AccountName'] = $val['AccountName'] ?: $default_Proxy['AccountName'];
                $val['ProxyId'] = $val['ChannelID'];
                $val['recharge_times'] = $val['recharge_times'] ?: 0;
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                $other = [];
                $field = "ISNULL(sum(a.TotalDeposit),0)TotalDeposit,ISNULL(sum(a.TotalRollOut),0)TotalRollOut,ISNULL(sum(a.Money),0)Money";
                $other = $m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                    ->where($where)
                    ->field($field)
                    ->select()[0];

                $other['total_num'] = $data['total'];

                $other['first_recharge_num'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] d','d.RoleID=a.AccountID','LEFT')
                    ->where($where)
                    ->where('d.IfFirstCharge=1 and d.ChangeType=5')
                    ->field('a.AccountID')
                    ->select())?:0;
                 $other['first_recharge_amount'] = $m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                    ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] d','d.RoleID=a.AccountID','LEFT')
                    ->where($where)
                    ->where('d.IfFirstCharge=1 and d.ChangeType=5')
                    ->sum('d.ChangeMoney')?:0;

                $other['total_recharge_num'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                    ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d','d.AccountID=a.AccountID','LEFT')
                    ->where($where)
                    ->where(' d.AccountID is not null ')
                    ->field('d.AccountID')
                    ->select())?:0;
                //充值人数
                $other['total_recharge_people'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                    // ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d','d.AccountID=a.AccountID')
                    ->where($where)
                    ->where('TotalDeposit','>',0)
                    ->group('a.AccountID')
                    ->field('a.AccountID')
                    ->select())?:0;
                //提现人数
                $other['total_rollout_people'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ProxyChannelId','LEFT')
                    // ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d','d.AccountID=a.AccountID')
                    ->where($where)
                    ->where('TotalRollOut','>',0)
                    ->group('a.AccountID')
                    ->field('a.AccountID')
                    ->select())?:0;
                if ($other['TotalDeposit'] == 0 || $other['total_recharge_people'] == 0) {
                    $other['Money'] = 0;
                } else {
                    $other['Money'] = bcdiv($other['TotalDeposit'], $other['total_recharge_people'],3);
                }
                // $other['TotalDeposit'] = FormatMoney($other['TotalDeposit']);
                // $other['TotalRollOut'] = FormatMoney($other['TotalRollOut']);
                // $other['Money'] = FormatMoney($other['Money']);
                $other['TotalProfit'] = bcsub($other['TotalDeposit'],$other['TotalRollOut'],3);
                $other['first_recharge_amount'] = FormatMoney($other['first_recharge_amount']);
                return $this->apiReturn(0, $data['data'], 'success', $data['total'],$other);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                if (empty($data['data'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['data'];
                $result['count'] = $data['total'];
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('推广ID') => 'string',
                        lang('推广名称') => 'string',
                        lang('玩家ID') => "string",
                        // lang('手机号') => "string",
                        lang('充值次数') => "string",
                        lang('充值金额') => "string",
                        lang('提现金额') => "string",
                        lang('携带金币') => "string",
                        lang('盈利') => "string",
                        lang('注册时间') => "string",
                    ];
                    $filename = lang('推广统计').'-' . date('YmdHis');
                    $rows =&$result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['ChannelID'],
                            $row['AccountName'],
                            $row['AccountID'],
                            // $row['Mobile'],
                            $row['recharge_times'],
                            $row['TotalDeposit'],
                            $row['TotalRollOut'],
                            $row['Money'],
                            $row['TotalProfit'],
                            $row['RegisterTime'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        }
        if (config('is_portrait')==1) {
            return $this->fetch('channel_statistics_s');
        } else {
            return $this->fetch('channel_statistics');
        }
    }

    public function channelDaily(){
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit')?:15;

            
            $ChannelID = $this->request->param('ChannelID');
            $start_date = $this->request->param('strartdate');
            $end_date = $this->request->param('enddate');


            $where = '1=1';
            if ($start_date != '') {
               $where .= ' and a.Date>=\''.$start_date.'\'';
           }
           if ($end_date != '') {
                $where .= ' and a.Date<\''.$end_date.'\'';
           }
           
            $gameOCDB = new GameOCDB();
            $default_Proxy = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->find() ?: [];
            $default_Proxy['ProxyChannelId'] = $default_Proxy['ProxyChannelId'] ?? '';
            $default_Proxy['AccountName'] = $default_Proxy['AccountName'] ?? '';

            if (!empty($ChannelID)) {
                if ($default_Proxy['ProxyChannelId'] == $ChannelID) {
                   $where .= " and (a.ChannelId=0 or a.ChannelId='$ChannelID')";
                } else {
                    $where .= " and a.ChannelId='$ChannelID'";
                }
            }
            $order =  "Date desc";
            $data = $gameOCDB->getTableObject('T_PopularizeDailyData')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c','c.ProxyChannelId=a.ChannelId','LEFT')
                ->where($where)
                ->field('a.*,c.AccountName')
                ->order($order)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                $val['RechargeAmount'] = FormatMoney($val['RechargeAmount']);
                $val['FirstChargeAmount'] = FormatMoney($val['FirstChargeAmount']);
                $val['AverageChargeAmount'] = FormatMoney($val['AverageChargeAmount']);
                $val['DrawBackAmount'] = FormatMoney($val['DrawBackAmount']);
                $val['TotalProfit'] = FormatMoney($val['TotalProfit']);
                $val['OldRechargeAmount'] = FormatMoney($val['OldRechargeAmount']);
                $val['OldRechargeRate'] = ($val['OldRechargeRate']*100).'%';
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                $other = [];
                $field = "ISNULL(sum(a.RegisterNum),0)RegisterNum,ISNULL(sum(a.FirstChargeNum),0)FirstChargeNum,ISNULL(sum(a.FirstChargeAmount),0)FirstChargeAmount,ISNULL(sum(a.RechargeAmount),0)RechargeAmount,ISNULL(sum(a.DrawBackAmount),0)DrawBackAmount,ISNULL(sum(a.TotalProfit),0)TotalProfit,ISNULL(sum(a.AverageChargeAmount),0)AverageChargeAmount,ISNULL(sum(a.RechargeTimes),0)RechargeTimes,ISNULL(SUM(a.RechargeNum),0) AS RechargeNum";
                $other = $gameOCDB->getTableObject('T_PopularizeDailyData')->alias('a')
                           ->where($where)
                            ->field($field)
                            ->find();
                $val = &$other;
                $val['RechargeAmount'] = FormatMoney($val['RechargeAmount']);
                $val['FirstChargeAmount'] = FormatMoney($val['FirstChargeAmount']);
                if($val['RechargeTimes']!=0){
                    $val['AverageChargeAmount'] = bcdiv($val['RechargeAmount'],$val['RechargeTimes'],2);
                }
                else{
                    $val['AverageChargeAmount'] =0;
                }

                $val['DrawBackAmount'] = FormatMoney($val['DrawBackAmount']);
                $val['TotalProfit'] = FormatMoney($val['TotalProfit']);
                return $this->apiReturn(0, $data['data'], 'success', $data['total'],$other);
            }
            if (input('output') == 'exec') {
                //权限验证 
                if (empty($data['data'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['data'];
                $result['count'] = $data['total'];
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'string',
                        lang('推广ID') => 'string',
                        lang('推广名称') => 'string',
                        lang('注册人数') => "string",
                        lang('充值人数') => "string",
                        lang('充值金额') => "string",
                        lang('首充人数') => "string",
                        lang('首充金额') => "string",
                        lang('平均充值') => "string",
                        lang('提现金额') => "string",
                        lang('总盈利') => "string",
                        lang('老用户充值') => "string",
                        lang('老用户人数') => "string",
                        lang('老用户占比') => "string"
                    ];
                    $filename = lang('推广日报').'-' . date('YmdHis');
                    $rows =&$result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['Date'],
                            $row['ChannelId'],
                            $row['AccountName'],
                            $row['RegisterNum'],
                            $row['RechargeNum'],
                            $row['RechargeAmount'],
                            $row['FirstChargeNum'],
                            $row['FirstChargeAmount'],
                            $row['AverageChargeAmount'],
                            $row['DrawBackAmount'],
                            $row['TotalProfit'],
                            $row['OldRechargeAmount'],
                            $row['OldRechargeNum'],
                            $row['OldRechargeRate'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        }
        return $this->fetch();
    }
    //数据汇总日报
    public function totalDaily()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {

        } else {
            $date = $this->request->param('date') ?: date('Y-m-d');
            $ProxyId = $this->request->param('ProxyId') ?: 0;
            $RoleID = $this->request->param('RoleID') ?: 0;
            $action = $this->request->param('action');
            if ($action == 'check') {
                if (strtotime($date) > sysTime()) {
                    return ['code' => 0, 'msg' => 'time error'];
                } else {
                    return ['code' => 0];
                }
            }
            $db = new \app\model\GameOCDB();
            $ChannelID = 0;
            if($ProxyId != ''){
                $ChannelID = $db->getTableObject('T_ProxyChannelConfig')->where('ProxyId',$ProxyId)->value('RoleID')?:0;
            }
            if ($RoleID != '') {
                if($ChannelID != 0 && $ChannelID != $RoleID){
                    $ChannelID = 0;
                } else{
                    $ChannelID = $RoleID;
                }
                
            }
            $data = [];
            //在线人数
            $channelusers = $db->DBOriginQuery('SELECT RoleID FROM [CD_UserDB].[dbo].[View_UserTeam]');
            $channelusers = array_column($channelusers, 'RoleID');
            // var_dump($channelusers);die();
            $data['online'] = $this->GetOnlineUserlist2('list')['total_num'] ?: 0;
            // $data['online'] = count(array_intersect($channelusers,$data['online']));

            $data['pay'] = $db->setTable('T_GameStatisticChannelPay')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['system'] = $db->setTable('T_GameStatisticChannelTotal')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['user'] = $db->setTable('T_GameStatisticChannelUser')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['out'] = $db->setTable('T_GameStatisticChannelPayOut')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            if (!empty($data['pay'])) {
                $data['pay']['totalpay'] = FormatMoney($data['pay']['totalpay']);
                $data['pay']['manual_recharge'] = FormatMoney($data['pay']['manual_recharge']);
                $data['pay']['newuserpay'] = FormatMoney($data['pay']['newuserpay']);
                $data['pay']['paygift'] = FormatMoney($data['pay']['paygift']);
            }
            if (!empty($data['out'])) {

                $data['out']['apply_payout'] = FormatMoney($data['out']['apply_payout']);
                $data['out']['totalpayout'] = FormatMoney($data['out']['totalpayout']);
            }
            if (!empty($data['system'])) {
                $data['system']['totalyk'] = FormatMoney($data['system']['totalyk']);
                $data['system']['totaltax'] = FormatMoney($data['system']['totaltax']);
                $data['system']['totalwage'] = FormatMoney($data['system']['totalwage']);
                $data['system']['totalwin'] = FormatMoney($data['system']['totalwin']);
                $data['system']['totalwater'] = FormatMoney($data['system']['totalwater']);
                $data['system']['TotalMailCoin'] = FormatMoney($data['system']['TotalMailCoin']);
            } else {
                $data['system']['totalwin'] = 0;
                $data['system']['totaltax'] = 0;
            }

            if (isset($data['pay']['channel_payJson'])) {
                $data['recharge'] = json_decode($data['pay']['channel_payJson'], 1);
            } else {
                $data['recharge'] = [];
            }
            if (!isset($data['pay']['totalpay'])) {
                $data['pay']['totalpay'] = 0;
            }
            if (!isset($data['pay']['manual_recharge'])) {
                $data['pay']['manual_recharge'] = 0;
            }

            if (!isset($data['out']['totalpayout'])) {
                $data['out']['totalpayout'] = 0;
            }
            if (isset($data['user']['abondonuserjson'])) {
                $data['user']['abondonuser'] = json_decode($data['user']['abondonuserjson'], 1)[0];
            } else {
                $data['user']['abondonuser'] = [];
            }

            if (isset($data['user']['normaluserjson'])) {
                $data['user']['normaluser'] = json_decode($data['user']['normaluserjson'], 1)[0];
            } else {
                $data['user']['normaluser'] = [];
            }
            if (isset($data['user']['agentuserjson'])) {
                $data['user']['agentuser'] = json_decode($data['user']['agentuserjson'], 1)[0];
            } else {
                $data['user']['agentuser'] = [];
            }

            $data['activity'] = [];
            $db = new \app\model\ActivityRecord();
            // $activity_arr = [
            //     '54'=>lang('手机绑定领取'),
            //     '15,59,60'=>lang('签到领取'),
            //     '61'=>lang('VIP特权领取-升级'),
            //     '101'=>lang('vip周领取'),
            //     '102'=>lang('vip月领取'),
            //     '67'=>lang('周卡领取'),
            //     '68'=>lang('月卡领取'),
            // ];
            $data['activity'][54] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=54 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][15] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType in (15,59,60) and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][61] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=61 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][101] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=101 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][102] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=102 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][67] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=67 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][68] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=68 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            $data['activity'][54] = FormatMoney($data['activity'][54]);
            $data['activity'][15] = FormatMoney($data['activity'][15]);
            $data['activity'][61] = FormatMoney($data['activity'][61]);
            $data['activity'][101] = FormatMoney($data['activity'][101]);
            $data['activity'][102] = FormatMoney($data['activity'][102]);
            $data['activity'][67] = FormatMoney($data['activity'][67]);
            $data['activity'][68] = FormatMoney($data['activity'][68]);


            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=11 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][54] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=54 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][72] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=72 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            $data['cj'][65] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=65 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][66] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=66 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][69] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=69 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            $data['cj'][11] = FormatMoney($data['cj'][11]);
            $data['cj'][54] = FormatMoney($data['cj'][54]);
            $data['cj'][72] = FormatMoney($data['cj'][72]);

            $data['cj'][65] = FormatMoney($data['cj'][65]);
            $data['cj'][66] = FormatMoney($data['cj'][66]);
            $data['cj'][69] = FormatMoney($data['cj'][69]);

            $this->assign('ChannelID', $ProxyId);
            $this->assign('RoleID', $RoleID);
            $this->assign('data', $data);
            return $this->fetch();
        }
    }

    //数据汇总月报
    public function totalMonth()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
        } else {

            $date = $this->request->param('date') ?: date('Y-m');
            $ProxyId = $this->request->param('ProxyId') ?: 0;
            $RoleID = $this->request->param('RoleID') ?: 0;
            $action = $this->request->param('action');
            if ($action == 'check') {
                if (strtotime($date) > sysTime()) {
                    return ['code' => 0, 'msg' => 'time error'];
                } else {
                    return ['code' => 0];
                }
            }
            $db = new \app\model\GameOCDB();
            $ChannelID = 0;
            if($ProxyId != ''){
                $ChannelID = $db->getTableObject('T_ProxyChannelConfig')->where('ProxyId',$ProxyId)->value('RoleID')?:0;
            }
            if ($RoleID != '') {
                if($ChannelID != 0 && $ChannelID != $RoleID){
                    $ChannelID = 0;
                } else{
                    $ChannelID = $RoleID;
                }
                
            }
            $data = [];
            //在线人数
            $data['online'] = $this->GetOnlineUserlist2('onlinenum') ?: 0;
            $data['pay'] = $db->setTable('view_GameStatisticChannelPay')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['recharge'] = $db->setTable('View_PayChannelChannelMonth')->getListTableAll('addtime=\'' . '\'', '*') ?: [];
            $data['out'] = $db->setTable('view_GameStatisticChannelPayOut')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['system'] = $db->setTable('view_GameStatisticChannelTotal')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            $data['user'] = $db->setTable('view_GameStatisticChannelUser')->GetRow('mydate=\'' . $date . '\' and ChannelID='.$ChannelID, '*') ?: [];
            if (!empty($data['pay'])) {
                $data['pay']['totalpay'] = FormatMoney($data['pay']['totalpay']);
                $data['pay']['manual_recharge'] = FormatMoney($data['pay']['manual_recharge']);
                $data['pay']['newuserpay'] = FormatMoney($data['pay']['newuserpay']);
                $data['pay']['paygift'] = FormatMoney($data['pay']['paygift']);
            }

            if (!empty($data['out'])) {
                $data['out']['apply_payout'] = FormatMoney($data['out']['apply_payout']);
                $data['out']['totalpayout'] = FormatMoney($data['out']['totalpayout']);
            }
            if (!empty($data['system'])) {
                $data['system']['totalyk'] = FormatMoney($data['system']['totalyk']);
                $data['system']['totaltax'] = FormatMoney($data['system']['totaltax']);
                $data['system']['totalwage'] = FormatMoney($data['system']['totalwage']);
                $data['system']['totalwin'] = FormatMoney($data['system']['totalwin']);
                $data['system']['totalwater'] = FormatMoney($data['system']['totalwater']);
                $data['system']['TotalMailCoin'] = FormatMoney($data['system']['TotalMailCoin']);
            }else {
                $data['system']['totalwin'] = 0;
                $data['system']['totaltax'] = 0;
            }
            if (!isset($data['pay']['totalpay'])) {
                $data['pay']['totalpay'] = 0;
            }
            if (!isset($data['pay']['manual_recharge'])) {
                $data['pay']['manual_recharge'] = 0;
            }
            if (!isset($data['out']['totalpayout'])) {
                $data['out']['totalpayout'] = 0;
            }
            // $data['user']['abondonuser'] = json_decode($data['user']['abondonuserjson'],1);
            // $data['user']['normaluser'] = json_decode($data['user']['normaluserjson'],1);
            // $data['user']['agentuser'] = json_decode($data['user']['agentuserjson'],1);
            $data['activity'] = [];
            $db = new \app\model\ActivityRecord();
            // $activity_arr = [
            //     '54'=>lang('手机绑定领取'),
            //     '15,59,60'=>lang('签到领取'),
            //     '61'=>lang('VIP特权领取-升级'),
            //     '101'=>lang('vip周领取'),
            //     '102'=>lang('vip月领取'),
            //     '67'=>lang('周卡领取'),
            //     '68'=>lang('月卡领取'),
            // ];
            $end_date = date('Y-m-d', strtotime("$date +1 month -1 day"));
            $data['activity'][54] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][15] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType in (15,59,60) and ChannelID='.$ChannelID, 'ReceiveAmt');
            $data['activity'][61] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=61 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][101] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=101 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][102] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=102 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][67] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=67 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][68] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=68 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            $data['activity'][54] = FormatMoney($data['activity'][54]);
            $data['activity'][15] = FormatMoney($data['activity'][15]);
            $data['activity'][61] = FormatMoney($data['activity'][61]);
            $data['activity'][101] = FormatMoney($data['activity'][101]);
            $data['activity'][102] = FormatMoney($data['activity'][102]);
            $data['activity'][67] = FormatMoney($data['activity'][67]);
            $data['activity'][68] = FormatMoney($data['activity'][68]);

            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=11 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][54] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][72] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=72 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            //统计
             $data['cj'][65] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=65 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][66] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=66 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][69] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=69 and ChannelID='.$ChannelID, 'ReceiveAmt') ?: 0;

            $data['cj'][11] = FormatMoney($data['cj'][11]);
            $data['cj'][54] = FormatMoney($data['cj'][54]);
            $data['cj'][72] = FormatMoney($data['cj'][72]);

            $data['cj'][65] = FormatMoney($data['cj'][65]);
            $data['cj'][66] = FormatMoney($data['cj'][66]);
            $data['cj'][69] = FormatMoney($data['cj'][66]);

            $this->assign('ChannelID', $ProxyId);
            $this->assign('RoleID', $RoleID);
            $this->assign('data', $data);

            return $this->fetch();
        }
    }


    //首充留存
    public function remainFirstCharge(){
        $order = "mydate desc";
        $roleid = input('RoleID',0);
        $start = input('strartdate');
        $end = input('enddate');
        $typeid =input('typeid','');
        $where = " and typeid=".$typeid;
        if ($start != null && $end != null) {
            $where .= "and mydate BETWEEN '$start' And '$end'";
        }
        if($roleid>0){
            $where.=' and  ChannelID='.$roleid;
        }
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->userFirstPayByChannel($where, $order);
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->userFirstPayByChannel($where, $order);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'datetime',
                        lang('新增人数') => 'integer',
                        lang('次日留存') => 'string',
                        lang('2日留存') => "string",
                        lang('3日留存') => "string",
                        lang('4日留存') => "string",
                        lang('5日留存') => "string",
                        lang('6日留存') => "string",
                        lang('7日留存') => "string",
                        lang('15日留存') => "string",
                        lang('30日留存') => "string",
                    ];
                    $filename = lang('首充留存').'-'.date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
        $this->assign('roleid',$roleid);
        $this->assign('typeid',$typeid);
        if (config('is_portrait')==1) {
            return $this->fetch('remain_first_charge_s');
        } else {
            return $this->fetch();
        }
    }


}