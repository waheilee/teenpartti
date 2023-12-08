<?php

namespace app\merchant\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\UserDB;
use app\model\GameOCDB;
use app\model\MasterDB;
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
            $auth = trim(input('auth')) ? trim(input('auth')) : '';
            $password = trim(input('password')) ? trim(input('password')) : '';
            $username = trim(input('username')) ? trim(input('username')) : '';
            $nickname = trim(input('nickname')) ? trim(input('nickname')) : '';
            $phone = trim(input('phone')) ? trim(input('phone')) : '';
            //$qudao    = trim(input('qudao')) ? trim(input('qudao')) : '';
            $request = $this->request->request();

            if (!$password || !$username || !$phone || !$nickname) {
                return $this->apiReturn(1, [], '缺少信息请补全');
            }
            if (!checkmobile($phone)) {
                return $this->apiReturn(1, [], '手机号格式有误');
            }
            $status = $status == 'on' ? 1 : 0;
            $auth = $auth == 'on' ? 1 : 0;

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
                    'username' => $username,
                    'password' => md5($salt . $password),
                    'salt' => $salt,
                    'mobile' => $phone,
                    'status' => $status,
                    'auth' => $auth,
                    'nickname' => $nickname,
                    'create_time' => date('Y-m-d H:i:s'),
                    'groupid' => 2
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
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $channelid = trim(input('channelid')) ? trim(input('channelid')) : '';
            $channelname = trim(input('channelname')) ? trim(input('channelname')) : '';
            $startdate = trim(input('startdate')) ? trim(input('startdate')) : '';
            $enddate = trim(input('enddate')) ? trim(input('enddate')) : '';
            if ($enddate) {
                $enddate = date('Ymd', (strtotime($enddate) + 3600 * 24));
            }

//            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $orderby = trim(input('orderby')) ? trim(input('orderby')) : 'lastdaykeep';
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = Api::getInstance()->sendRequest([
                'channelid' => $channelid,
                'channelname' => $channelname,
                'startdate' => $startdate,
                'enddate' => $enddate,
                'asc' => $asc,
                'orderby' => $orderby,
                'page' => $page,
                'pagesize' => $limit
            ], 'system', 'channeldata');
            if (isset($res['data']['list']) && $res['data']['list']) {

                $countmodel = Db::connect($this->db2)->name('game_count');
                //获取pv uv信息
                $where = [

                    'day' => [['egt', date('Ymd', (strtotime($startdate)))], ['elt', $enddate]]
                ];
                $count = $countmodel->where($where)->select();

                foreach ($res['data']['list'] as &$v) {
                    $v['paytotalnew'] /= 1000;
                    $v['paytotal'] /= 1000;
                    $v['officalcharge'] /= 1000;
                    $v['agentcharge'] /= 1000;
                    $v['totalout'] /= 1000;
                    $v['totalin'] /= 1000;
                    $v['totaltax'] /= 1000;
                    $v['exchangemoney'] /= 1000;

                    $v['lastdaykeep'] = round($v['lastdaykeep'] * 100, 2);
                    $v['bindrate'] = round($v['bindrate'] * 100, 2);
                    $v['payrate'] = round($v['payrate'] * 100, 2);
                    $v['activerate'] = round($v['activerate'] * 100, 2);
                    $v['profitrate'] = round($v['profitrate'] * 100, 2);
                    $v['activekeep'] = round($v['activekeep'] * 100, 2);
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

    public function channelDownload()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $qudao = trim(input('roleid')) ? trim(input('roleid')) : '';

            $where = $qudao ? ['qudao' => $qudao] : [];
            $count = Db::connect($this->db2)->name('game_user')->where($where)->name('game_user')->count();
            $res = Db::connect($this->db2)->name('game_user')
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
            $qudao = trim(input('roleid')) ? trim(input('roleid')) : '';
            $urlArr = [
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
            $tmp = [];
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

        $qudao = trim(input('roleid')) ? trim(input('roleid')) : '';
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

        $qudao = trim(input('roleid')) ? trim(input('roleid')) : '';
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

            $qudao = trim(input('qudao')) ? trim(input('qudao')) : '';
            $channelname = trim(input('channelname')) ? trim(input('channelname')) : '';
            $where = ["qudao" => $qudao];
            $data = array("nickname" => $channelname);
            $request = $this->request->request();
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

        $id = $_GET['id'];
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
        $res = file_get_contents($apiurl);
        $res = json_decode($res, true);

        if ($res['code'] == 1 && isset($res['data']['weibo_shorturl'])) {
            //成功
            return $res['data']['weibo_shorturl'];
        } else if ($res['code'] == 1401) { //已经添加过了
            //删掉重新生成
            $apiurl = 'https://v94.cn/api/SingleShortUrl.json?appid=510340223&appkey=cb071b0b0442ac949125e5ecf1aa43b6&type=delete&url=' . $url;
            $res = file_get_contents($apiurl);
            $res = json_decode($res, true);
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
            $key = input('key') ? trim(input('key')) : '';
            $descript = input('descript') ? trim(input('descript')) : '';
            if (!$key || !$descript) {
                return $this->apiReturn(1, [], '参数有误');
            }
            if (Db::name('fenfa')->where(['key' => $key])->count()) {
                return $this->apiReturn(2, [], '该渠道已存在，请勿重复添加');
            }
            $res = Db::name('fenfa')->insertGetId([
                'key' => $key,
                'descript' => $descript,
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
            $id = input('id') ? intval(input('id')) : 0;
            $key = input('key') ? trim(input('key')) : '';
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
                    'key' => $key,
                    'descript' => $descript,
                    'updatetime' => date('Y-m-d H:i:s')
                ]);
            if (!$res) {
                return $this->apiReturn(3, [], '更新失败');
            }
            return $this->apiReturn(0, [], '更新成功');
        }

        $id = input('id');
        $key = input('key');
        $descript = input('descript');
        $this->assign('id', $id);
        $this->assign('key', $key);
        $this->assign('descript', $descript);
        return $this->fetch();
    }

    //更新分发状态
    public function updatefenfa()
    {

        $id = input('id') ? intval(input('id')) : 0;
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
    public function channelList()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $ProxyId = $this->request->param('ProxyId');
            $RoleID = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $where = "a.type=0";

            if ($ProxyId != '') {
                $where .= " and a.ProxyId='" . $ProxyId . "'";
            }
            if ($RoleID != '') {
                $where .= " and a.ProxyChannelId=" . $RoleID;
            }
            if ($ChannelID != '') {
                $where .= " and a.ProxyChannelId=" . $ChannelID;
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= " and a.OperatorId=" . session('merchant_OperatorId');
            }
            $m = new \app\model\GameOCDB();
            $data = $m->getTableObject('T_ProxyChannelConfig')->alias('a')
                // ->join('[CD_UserDB].[dbo].[T_UserProxyInfo] b', 'a.ProxyChannelId=b.RoleID', 'LEFT')
                ->where($where)
                ->field('a.*')
                ->order('ProxyChannelId asc')
                ->paginate($limit)
                ->toArray();
            $m = new \app\model\MasterDB();
            $InviteUrlModel = $m->getTableObject('T_GameConfig')->where('CfgType', 113)->value('keyValue');
            foreach ($data['data'] as $key => &$val) {
                // $val['InviteUrl'] = str_replace("{inviteCode}", $val['InviteCode'], $InviteUrlModel);
                $val['InviteUrl'] = str_replace("{inviteCode}", $val['ProxyChannelId'], $InviteUrlModel);
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        $list = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('type', 0)->where('OperatorId', session('merchant_OperatorId'))->order('ProxyChannelId asc')->select();
        $this->assign('oplist', $list);
        return $this->fetch();
    }

    public function businessList()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $ProxyId = $this->request->param('ProxyId');
            $RoleID = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $bustype = $this->request->param('bustype');
            $parentName = $this->request->param('parentName');
            $where = "a.type>0";

            if ($ProxyId != '') {
                $where .= " and a.ProxyId='" . $ProxyId . "'";
            }
            if ($RoleID != '') {
                $where .= " and a.ProxyChannelId=" . $RoleID;
            }
            if ($ChannelID != '') {
                $where .= " and a.ProxyChannelId=" . $ChannelID;
            }
            if ($bustype != '') {
                $where .= " and a.type=" . $bustype;
            }
            if ($parentName != '') {
                $where .= " and a.pid=" . $parentName;
            }
            // if ($parentName != '') {
            //     $where .= " and b.AccountName like '%".$parentName."%'";
            // }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= " and a.OperatorId=" . session('merchant_OperatorId');
            }
            $m = new \app\model\GameOCDB();
            $data = $m->getTableObject('T_ProxyChannelConfig')->alias('a')
                ->join('T_ProxyChannelConfig b', 'b.ProxyChannelId=a.pid', 'LEFT')
                ->where($where)
                ->field('a.*,b.AccountName parentName')
                ->order('Addtime asc')
                ->fetchSql(0)
                ->paginate($limit)
                ->toArray();
            $m = new \app\model\MasterDB();
            $InviteUrlModel = $m->getTableObject('T_GameConfig')->where('CfgType', 113)->value('keyValue');
            foreach ($data['data'] as $key => &$val) {
                // $val['InviteUrl'] = str_replace("{inviteCode}", $val['InviteCode'], $InviteUrlModel);
                $val['InviteUrl'] = str_replace("{inviteCode}", $val['ProxyChannelId'], $InviteUrlModel);
                if ($val['type'] == 1) {
                    $val['type'] = lang('业务组长');
                } else {
                    $val['type'] = lang('普通业务员');
                }
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        $list = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('type', '>', 0)->where('OperatorId', session('merchant_OperatorId'))->order('ProxyChannelId asc')->select();
        $this->assign('oplist', $list);
        return $this->fetch();
    }

    public function businessEdit()
    {

        if ($this->request->method() == 'POST') {

            $ProxyId = $this->request->param('ProxyId');
            $AccountName = $this->request->param('AccountName');
            $pid = $this->request->param('pid') ?: 0;
            $bustype = $this->request->param('bustype');
            $password = $this->request->param('password') ?: '';
            $LoginAccount = $this->request->param('LoginAccount') ?: '';
            $RoleID = $this->request->param('RoleID');

            $m = new \app\model\GameOCDB();
            $data = [];
            $data['AccountName'] = $AccountName;
            $data['LoginAccount'] = $LoginAccount;
            $data['type'] = $bustype;
            if ($data['type'] == 1) {
                $data['pid'] = 0;
            } else {
                $data['pid'] = $pid;
                if (empty($data['pid'])) {
                    return $this->apiReturn(1, '', '请先选择上级业务员');
                }
            }

            if ($password) {
                $data['PassWord'] = md5($password);
            }
            if ($RoleID) {
                $has_AccountName = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', '<>', $RoleID)->where('LoginAccount', $LoginAccount)->find();
                if ($has_AccountName) {
                    return $this->apiReturn(1, '', '登陆账号已存在');
                }

                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where('ProxyChannelId', $RoleID)
                    ->find();
                if (empty($res)) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                // $xbusiness = $this->getXbusiness($RoleID);
                // $xbusiness[] = $RoleID;
                // if (in_array($data['pid'], $xbusiness)) {
                //     return $this->apiReturn(1,'','编辑失败');
                // }
                if ($res['OperatorId'] != session('merchant_OperatorId')) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where('ProxyChannelId', $RoleID)
                    ->data($data)
                    ->update();
                if (!$res) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                return $this->apiReturn(0, '', '编辑成功');
            } else {
                $has_AccountName = $m->getTableObject('T_ProxyChannelConfig')->where('LoginAccount', $LoginAccount)->find();
                if ($has_AccountName) {
                    return $this->apiReturn(1, '', '登陆账号已存在');
                }

                //添加渠道信息
                $roleID = $this->getRoleId();
                $ProxyId = $ProxyId ?: $roleID;
                $inviteCode = createRandCode(6);
                $SecretKey = createRandCode(18);
                $info['RoleID'] = $roleID;
                $info['InviteCode'] = $inviteCode;
                $info['CorpType'] = 3;


                $ret = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo')->insert($info);

                if ($ret) {

                    $data['ProxyChannelId'] = $roleID;
                    $data['ProxyId'] = $ProxyId;

                    $data['DownloadUrl'] = "";
                    $data['ShowUrl'] = "";
                    $data['LandingPageUrl'] = "";
                    $data['ChannelShowUrl'] = "";
                    $data['InviteUrl'] = $inviteCode;
                    $data['OperatorId'] = (int)session('merchant_OperatorId');
                    $data['Addtime'] = time();
                    $res = $m->getTableObject('T_ProxyChannelConfig')->insert($data);
                    if (!$res) {
                        return $this->apiReturn(1, '', '添加业务员失败');
                    }
                    return $this->apiReturn(0, '', '添加业务员成功');
                } else {
                    return $this->apiReturn(1, '', '添加业务员失败');
                }
            }
        }
        $RoleID = $this->request->param('RoleID') ?: 0;
        if ($RoleID) {
            $data = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', $RoleID)->find();
            //不能挂在自身和下级
            // $xbusiness = $this->getXbusiness($RoleID);
            // $xbusiness[] = $RoleID;
            // $business = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId',session('merchant_OperatorId'))->where('ProxyChannelId','not in',$xbusiness)->where('type','>',0)->order('Addtime asc')->field('ProxyChannelId,AccountName,type,Addtime')->select();
        } else {
            $data = [];
            $data['pid'] = 0;
            $data['type'] = 1;
            // $business = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId',session('merchant_OperatorId'))->where('type','>',0)->order('Addtime asc')->field('ProxyChannelId,AccountName,type,Addtime')->select();
        }
        //组长列表
        $business = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId', session('merchant_OperatorId'))->where('type', 1)->where('ProxyChannelId', '<>', $RoleID)->order('Addtime asc')->field('ProxyChannelId,AccountName,type,Addtime')->select();
        foreach ($business as $key => &$val) {
            if ($val['type'] == 1) {
                $val['type'] = lang('业务组长');
            } else {
                $val['type'] = lang('普通业务员');
            }
        }
        $this->assign('business', $business);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function businessData()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $ProxyChannelId = $this->request->param('roleid');
            $tab = $this->request->param('tab');
            $start = $this->request->param('start');
            $end = $this->request->param('end');
            $bustype = $this->request->param('bustype');
            $parentName = $this->request->param('parentName');
            $businessid = (int)$this->request->param('businessid');
            $limit = $this->request->param('limit') ?: 10;
            $where = '1=1';
            if ($ProxyChannelId != '') {
                $where .= " and a.ChannelId='" . $ProxyChannelId . "'";
            }
            if ($bustype != '') {
                $where .= " and b.type=" . $bustype;
            } else {
                $where .= " and b.type>0";
            }
            if ($parentName != '') {
                $where .= " and b.pid=" . $parentName;
            }
            if ($businessid != '') {
                $where .= " and (b.pid='" . $businessid . "' or b.ProxyChannelId='" . $businessid . "')";
            }
            switch ($tab) {
                case '':
                    if ($start != '') {
                        $where .= " and Date>='" . $start . "'";
                    }
                    if ($end != '') {
                        $where .= " and Date<='" . $end . "'";
                    }
                    break;
                case 'today':
                    $where .= " and Date>='" . date('Y-m-d') . "'";
                    break;
                case 'yestoday':
                    $where .= " and Date>='" . date('Y-m-d', strtotime('-1 days')) . "' and Date<'" . date('Y-m-d') . "'";
                    break;
                case 'total':

                    break;
                case 'month':
                    $where .= " and Date>='" . date('Y-m') . "-01'";
                    $where .= " and Date<='" . date('Y-m-d') . "'";
                    break;
                case 'lastmonth':
                    $start = date('Y-m-01', strtotime('-1 month'));
                    $end = date('Y-m') . '-01';
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<'" . $end . "'";
                    break;
                case 'week':
                    $w = date('w');
                    if ($w == 0) {
                        $w = 7;
                    }
                    $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                    $start = date('Y-m-d', $w);
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<='" . date('Y-m-d') . "'";
                    break;
                case 'lastweek':
                    $w = date('w');
                    if ($w == 0) {
                        $w = 7;
                    }
                    $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                    $start = date('Y-m-d', $w - 7 * 86400);
                    $end = date('Y-m-d', $w);
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<'" . $end . "'";
                    break;
                default:

                    break;
            }

            $data = (new \app\model\GameOCDB())->getTableObject('T_ChannelDailyCollect')->alias('a')
                ->join('T_ProxyChannelConfig b', 'b.ProxyChannelId=a.ChannelId')
                ->where('b.OperatorId', session('merchant_OperatorId'))
                ->where($where)
                ->field('a.*,b.AccountName,b.pid')
                ->order('Date desc')
                ->paginate($limit)
                ->toArray();
            $parents = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId', session('merchant_OperatorId'))->where('type', '>', 0)->field('ProxyChannelId,pid,AccountName')->select();

            $parents = array_column($parents, null, 'ProxyChannelId');

            foreach ($data['data'] as $key => &$val) {

                $val['TotalRecharge'] = FormatMoney($val['TotalRecharge']);
                $val['TotalDrawMoney'] = FormatMoney($val['TotalDrawMoney']);
                $val['RoundBets'] = FormatMoney($val['RoundBets']);
                $val['PrizeBonus'] = FormatMoney($val['PrizeBonus']);
                $val['TotalYk'] = FormatMoney($val['TotalYk']);
                $val['ProxyChildBonus'] = FormatMoney($val['ProxyChildBonus']);
                $val['SendCoin'] = FormatMoney($val['SendCoin']);
                $val['HistoryCoin'] = FormatMoney($val['HistoryCoin']);

                $val['Profit'] = bcsub($val['TotalRecharge'], $val['TotalDrawMoney'], 2);

                if ($val['pid'] > 0) {
                    $val['parentName'] = $parents[$val['pid']]['AccountName'];
                }
            }
            $field = "ISNULL(sum(PersonCount),0) as PersonCount,ISNULL(sum(ActiveUserCount),0) as ActiveUserCount,ISNULL(sum(RechargeActiveCount),0) as RechargeActiveCount,ISNULL(sum(FirstRechargeCount),0) as FirstRechargeCount,ISNULL(sum(TotalRecharge),0) as TotalRecharge,ISNULL(sum(RechargeCount),0) as RechargeCount,ISNULL(sum(TotalDrawMoney),0) as TotalDrawMoney,ISNULL(sum(TotalDrawCount),0) as TotalDrawCount,ISNULL(sum(RoundBets),0) as RoundBets,ISNULL(sum(RoundBetsCount),0) as RoundBetsCount,ISNULL(sum(RoundBetTimes),0) as RoundBetTimes,ISNULL(sum(PrizeBonus),0) as PrizeBonus,ISNULL(sum(TotalYk),0) as TotalYk,ISNULL(sum(ProxyTotal),0) as ProxyTotal,ISNULL(sum(ProxyChildBonus),0) as ProxyChildBonus,ISNULL(sum(SendCoin),0) as SendCoin,ISNULL(sum(HistoryCoin),0) as HistoryCoin";
            $other = (new \app\model\GameOCDB())->getTableObject('T_ChannelDailyCollect')->alias('a')
                ->join('T_ProxyChannelConfig b', 'b.ProxyChannelId=a.ChannelId')
                ->where('b.OperatorId', session('merchant_OperatorId'))
                ->where($where)
                ->field($field)
                ->find();
            if (isset($other['TotalRecharge'])) {
                $other['TotalRecharge'] = FormatMoney($other['TotalRecharge']);
                $other['TotalDrawMoney'] = FormatMoney($other['TotalDrawMoney']);
                $other['RoundBets'] = FormatMoney($other['RoundBets']);
                $other['PrizeBonus'] = FormatMoney($other['PrizeBonus']);
                $other['TotalYk'] = FormatMoney($other['TotalYk']);
                $other['ProxyChildBonus'] = FormatMoney($other['ProxyChildBonus']);
                $other['SendCoin'] = FormatMoney($other['SendCoin']);
                $other['HistoryCoin'] = FormatMoney($other['HistoryCoin']);

                $other['Profit'] = bcsub($other['TotalRecharge'], $other['TotalDrawMoney'], 2);
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
        }
        $business = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId', session('merchant_OperatorId'))->where('type', 1)->order('Addtime asc')->field('ProxyChannelId,AccountName,type,Addtime')->select();
        $this->assign('business', $business);
        return $this->fetch();
    }

    public function businessData2()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $ProxyChannelId = $this->request->param('roleid');
            $tab = $this->request->param('tab');
            $start = $this->request->param('start');
            $end = $this->request->param('end');
            $limit = $this->request->param('limit') ?: 10;
            $where = '1=1';
            if ($ProxyChannelId != '') {
                $where .= " and ChannelId='" . $ProxyChannelId . "'";
            }
            switch ($tab) {
                case '':
                    if ($start != '') {
                        $where .= " and Date>='" . $start . "'";
                    }
                    if ($end != '') {
                        $where .= " and Date<='" . $end . "'";
                    }
                    break;
                case 'today':
                    $where .= " and Date>='" . date('Y-m-d') . "'";
                    break;
                case 'yestoday':
                    $where .= " and Date>='" . date('Y-m-d', strtotime('-1 days')) . "' and Date<'" . date('Y-m-d') . "'";
                    break;
                case 'total':

                    break;
                case 'month':
                    $where .= " and Date>='" . date('Y-m') . "-01'";
                    $where .= " and Date<='" . date('Y-m-d') . "'";
                    break;
                case 'lastmonth':
                    $start = date('Y-m-01', strtotime('-1 month'));
                    $end = date('Y-m') . '-01';
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<'" . $end . "'";
                    break;
                case 'week':
                    $w = mktime(0, 0, 0, date('m'), date('d') - date('w') + 1, date('y'));
                    $start = date('Y-m-d', $w);
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<='" . date('Y-m-d') . "'";
                    break;
                case 'lastweek':
                    $w = mktime(0, 0, 0, date('m'), date('d') - date('w') + 1, date('y'));
                    $start = date('Y-m-d', $w - 7 * 86400);
                    $end = date('Y-m-d', $w);
                    $where .= " and Date>='" . $start . "'";
                    $where .= " and Date<'" . $end . "'";
                    break;
                default:

                    break;
            }
            $ProxyChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('OperatorId', session('merchant_OperatorId'))->where('type', '>', 0)->field('ProxyChannelId,pid')->select();
            $parentids = array_column($ProxyChannelIds, 'pid', 'ProxyChannelId');

            //获取关系
            $relation = [];
            foreach ($ProxyChannelIds as $key => &$val) {
                $ProxyChannelId = $val['ProxyChannelId'];
                if (!isset($relation[$ProxyChannelId])) {
                    $relation[$ProxyChannelId] = [];
                    $relation[$ProxyChannelId][] = $ProxyChannelId;
                }
                $parentid = $val['pid'];
                while ($parentid > 0) {
                    if (!isset($relation[$parentid])) {
                        $relation[$parentid] = [];
                    }
                    $relation[$parentid][] = $ProxyChannelId;
                    $parentid = $parentids[$parentid];
                }
            }

            if (empty($ProxyChannelIds)) {
                return $this->apiReturn(0, [], 'success', 0, []);
            }
            $ProxyChannelIds = array_column($ProxyChannelIds, 'ProxyChannelId');
            $ProxyChannelIds = implode(',', $ProxyChannelIds);
            $field = "ISNULL(sum(PersonCount),0) as PersonCount,ISNULL(sum(ActiveUserCount),0) as ActiveUserCount,ISNULL(sum(RechargeActiveCount),0) as RechargeActiveCount,ISNULL(sum(FirstRechargeCount),0) as FirstRechargeCount,ISNULL(sum(TotalRecharge),0) as TotalRecharge,ISNULL(sum(RechargeCount),0) as RechargeCount,ISNULL(sum(TotalDrawMoney),0) as TotalDrawMoney,ISNULL(sum(TotalDrawCount),0) as TotalDrawCount,ISNULL(sum(RoundBets),0) as RoundBets,ISNULL(sum(RoundBetsCount),0) as RoundBetsCount,ISNULL(sum(RoundBetTimes),0) as RoundBetTimes,ISNULL(sum(PrizeBonus),0) as PrizeBonus,ISNULL(sum(TotalYk),0) as TotalYk,ISNULL(sum(ProxyTotal),0) as ProxyTotal,ISNULL(sum(ProxyChildBonus),0) as ProxyChildBonus,ISNULL(sum(SendCoin),0) as SendCoin,ISNULL(sum(HistoryCoin),0) as HistoryCoin";

            $sql = "select ChannelId," . $field . " from [OM_GameOC].[dbo].[T_ChannelDailyCollect](nolock) where ChannelId in (" . $ProxyChannelIds . ") and " . $where . " group by ChannelId";
            $data = (new \app\model\GameOCDB())->DBOriginQuery($sql);
            if (empty($data)) {
                $data = [];
                // return $this->apiReturn(0, [], 'success', 0,[]);
            }
            $total_data = array_column($data, null, 'ChannelId');
            $other = [];
            $other['ActiveUserCount'] = array_sum(array_column($data, 'ActiveUserCount')) ?: 0;
            $other['TotalRecharge'] = FormatMoney(array_sum(array_column($data, 'TotalRecharge'))) ?: 0;
            $other['RechargeCount'] = array_sum(array_column($data, 'RechargeCount')) ?: 0;
            $other['TotalDrawMoney'] = FormatMoney(array_sum(array_column($data, 'TotalDrawMoney'))) ?: 0;
            $other['TotalDrawCount'] = array_sum(array_column($data, 'TotalDrawCount')) ?: 0;
            $other['RoundBets'] = FormatMoney(array_sum(array_column($data, 'RoundBets'))) ?: 0;
            $other['RoundBetsCount'] = array_sum(array_column($data, 'RoundBetsCount')) ?: 0;

            $data = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')
                ->where('OperatorId', session('merchant_OperatorId'))
                ->where('type', '>', 0)
                ->field('ProxyChannelId,Addtime')
                ->order('Addtime asc')
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                //自身+伞下业务员
                $ProxyChannelId = $val['ProxyChannelId'];
                $total = array_intersect_key($total_data, array_flip($relation[$ProxyChannelId]));
                $val['PersonCount'] = array_sum(array_column($total, 'PersonCount')) ?: 0;
                $val['ActiveUserCount'] = array_sum(array_column($total, 'ActiveUserCount')) ?: 0;
                $val['RechargeActiveCount'] = FormatMoney(array_sum(array_column($total, 'RechargeActiveCount'))) ?: 0;
                $val['FirstRechargeCount'] = array_sum(array_column($total, 'FirstRechargeCount')) ?: 0;
                $val['TotalRecharge'] = FormatMoney(array_sum(array_column($total, 'TotalRecharge'))) ?: 0;
                $val['RechargeCount'] = array_sum(array_column($total, 'RechargeCount')) ?: 0;
                $val['TotalDrawMoney'] = FormatMoney(array_sum(array_column($total, 'TotalDrawMoney'))) ?: 0;
                $val['TotalDrawCount'] = array_sum(array_column($total, 'TotalDrawCount')) ?: 0;
                $val['RoundBets'] = FormatMoney(array_sum(array_column($total, 'RoundBets'))) ?: 0;
                $val['RoundBetsCount'] = array_sum(array_column($total, 'RoundBetsCount')) ?: 0;
                $val['RoundBetTimes'] = array_sum(array_column($total, 'RoundBetTimes')) ?: 0;
                $val['PrizeBonus'] = FormatMoney(array_sum(array_column($total, 'PrizeBonus'))) ?: 0;
                $val['TotalYk'] = FormatMoney(array_sum(array_column($total, 'TotalYk'))) ?: 0;
                $val['ProxyTotal'] = array_sum(array_column($total, 'ProxyTotal'));
                $val['ProxyChildBonus'] = FormatMoney(array_sum(array_column($total, 'ProxyChildBonus'))) ?: 0;
                $val['SendCoin'] = FormatMoney(array_sum(array_column($total, 'SendCoin'))) ?: 0;
                $val['HistoryCoin'] = FormatMoney(array_sum(array_column($total, 'HistoryCoin'))) ?: 0;
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
        }
        return $this->fetch();
    }

    public function getXbusiness($ProxyChannelIds)
    {
        static $result = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid', 'in', $ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result = array_unique(array_merge($result, $xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
    }


    public function channelEdit()
    {

        if ($this->request->method() == 'POST') {

            $ProxyId = $this->request->param('ProxyId');
            $AccountName = $this->request->param('AccountName');
            // $password = $this->request->param('password') ?: '';

            $m = new \app\model\GameOCDB();
            $RoleID = $this->request->param('RoleID');
            if ($RoleID) {
                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where('ProxyChannelId', $RoleID)
                    ->find();
                if (empty($res)) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                if ($res['OperatorId'] != session('merchant_OperatorId')) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where('ProxyChannelId', $RoleID)
                    ->data(['ProxyId' => $ProxyId, 'AccountName' => $AccountName])
                    ->update();
                if (!$res) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                return $this->apiReturn(0, '', '编辑成功');
            } else {
                // $has_ProxyId = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyId',$ProxyId)->find();
                // if ($has_ProxyId) {
                //     return $this->apiReturn(1,'','推广ID已存在');
                // }
                $has_AccountName = $m->getTableObject('T_ProxyChannelConfig')->where('AccountName', $AccountName)->find();
                if ($has_AccountName) {
                    return $this->apiReturn(1, '', '推广名称已存在');
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
                    $conf['ProxyChannelId'] = $roleID;
                    $conf['ProxyId'] = $ProxyId;
                    $conf['AccountName'] = $AccountName;
                    $conf['PassWord'] = "";
                    $conf['DownloadUrl'] = "";
                    $conf['ShowUrl'] = "";
                    $conf['LandingPageUrl'] = "";
                    $confUpdate['ChannelDownloadUrl'] = "";
                    $conf['ChannelShowUrl'] = "";
                    $conf['InviteUrl'] = $inviteCode;
                    $conf['OperatorId'] = (int)session('merchant_OperatorId');
                    $res = $m->getTableObject('T_ProxyChannelConfig')->insert($conf);
                    if (!$res) {
                        return $this->apiReturn(1, '', '添加推广失败');
                    }
                    return $this->apiReturn(0, '', '添加推广成功');
                } else {
                    return $this->apiReturn(1, '', '添加推广失败');
                }
            }
        }
        $RoleID = $this->request->param('RoleID');
        if ($RoleID) {
            $m = new \app\model\GameOCDB();
            $data = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', $RoleID)->find();
        } else {
            $data = [];
        }
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function getRoleId()
    {
        $roleId = mt_rand(500000, 599999);
        $m = new \app\model\UserDB();
        $isExist = $m->getTableObject('T_UserProxyInfo')->where('RoleID=' . $roleId)->find();
        if ($isExist) {
            return $this->getRoleId();
        }
        return $roleId;
    }

    public function setDefaultChannel()
    {
        $RoleID = $this->request->param('RoleID');
        $type = $this->request->param('type');
        $m = new \app\model\GameOCDB();
        $res1 = $m->getTableObject('T_ProxyChannelConfig')
            ->where('RoleID>0')
            ->data(['isDefault' => 0])
            ->update();
        $res = $m->getTableObject('T_ProxyChannelConfig')
            ->where('RoleID=' . $RoleID)
            ->data(['isDefault' => $type])
            ->update();
        if ($res) {
            return ['code' => 0];
        } else {
            return ['code' => 1];
        }
    }

    //渠道统计-竖版
    public function channelStatistics()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;

            $RoleID = $this->request->param('RoleID');
            $ChannelID = $this->request->param('ChannelID');
            $proxyId = $this->request->param('ProxyId');
            $start_date = $this->request->param('strartdate');
            $end_date = $this->request->param('enddate');
            $reg_ip = $this->request->param('reg_ip');
            $last_ip = $this->request->param('last_ip');
            $is_recharge = $this->request->param('is_recharge');
            $where = "1=1";
            // $where = "c.type=0";
            if (!empty($RoleID)) {
                $where .= "and a.AccountID=" . $RoleID;
            }
            // if (!empty($ChannelID)) {
            //      $where .= "and a.ChannelID=".$ChannelID;
            // }
            // if (!empty($ProxyId)) {
            //      $where .= "and c.ProxyId='".$ProxyId."'";
            // }
            if ($start_date != '') {
                $where .= ' and a.RegisterTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.RegisterTime<\'' . $end_date . '\'';
            }
            if ($reg_ip != '') {
                $where .= ' and a.RegIP=\'' . $reg_ip . '\'';
            }
            if ($last_ip != '') {
                $where .= ' and a.LastLoginIP=\'' . $last_ip . '\'';
            }
            if ($is_recharge == 1) {
                $where .= ' and a.TotalDeposit>0';
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= " and a.OperatorId=" . session('merchant_OperatorId');
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
            $field = "a.ProxyChannelId,c.AccountName,a.AccountID,a.Mobile,a.TotalDeposit,a.TotalRollOut,a.Money,a.RegisterTime";
            $data = $m->getTableObject('View_Accountinfo')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c', 'c.ProxyChannelId=a.ProxyChannelId', 'LEFT')
                ->where($where)
                ->field($field)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                $val['recharge_times'] = $m->getTableObject('T_UserTransactionChannel')->where('AccountID', $val['AccountID'])->count() ?: 0;
                // $val['TotalDeposit'] = FormatMoney($val['TotalDeposit']);
                // $val['TotalRollOut'] = FormatMoney($val['TotalRollOut']);
                $val['Money'] = FormatMoney($val['Money']);
                $val['TotalProfit'] = bcsub($val['TotalDeposit'], $val['TotalRollOut'], 3);
                $val['ChannelID'] = $val['ProxyChannelId'] ?: $default_Proxy['ProxyChannelId'];
                $val['AccountName'] = $val['AccountName'] ?: $default_Proxy['AccountName'];
                $val['ProxyId'] = $val['ChannelID'];
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                $other = [];
                $field = "ISNULL(sum(a.TotalDeposit),0)TotalDeposit,ISNULL(sum(a.TotalRollOut),0)TotalRollOut,ISNULL(sum(a.Money),0)Money";
                $other = $m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c', 'c.ProxyChannelId=a.ProxyChannelId', 'LEFT')
                    ->where($where)
                    ->field($field)
                    ->select()[0];

                $other['total_num'] = $data['total'];
                $other['first_recharge_num'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] d', 'd.RoleID=a.AccountID', 'LEFT')
                    ->where($where)
                    ->where('d.IfFirstCharge=1 and d.ChangeType=5')
                    ->field('a.AccountID')
                    ->select()) ?: 0;
                $other['first_recharge_amount'] = $m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c', 'c.ProxyChannelId=a.ProxyChannelId', 'LEFT')
                    ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] d', 'd.RoleID=a.AccountID', 'LEFT')
                    ->where($where)
                    ->where('d.IfFirstCharge=1 and d.ChangeType=5')
                    ->sum('d.ChangeMoney') ?: 0;

                $other['total_recharge_num'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c', 'c.ProxyChannelId=a.ProxyChannelId', 'LEFT')
                    ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d', 'd.AccountID=a.AccountID', 'LEFT')
                    ->where($where)
                    ->where(' d.AccountID is not null ')
                    ->field('d.AccountID')
                    ->select()) ?: 0;
                //充值人数
                $other['total_recharge_people'] = count($m->getTableObject('View_Accountinfo')->alias('a')
                    ->join('[OM_GameOC].[dbo].[T_ProxyChannelConfig] c', 'c.ProxyChannelId=a.ProxyChannelId', 'LEFT')
                    ->join('[CD_UserDB].[dbo].[T_UserTransactionChannel] d', 'd.AccountID=a.AccountID')
                    ->where($where)
                    ->group('a.AccountID')
                    ->field('a.AccountID')
                    ->select()) ?: 0;
                if ($other['TotalDeposit'] == 0 || $other['total_recharge_people'] == 0) {
                    $other['Money'] = 0;
                } else {
                    $other['Money'] = bcdiv($other['TotalDeposit'], $other['total_recharge_people'], 3);
                }
                // $other['TotalDeposit'] = FormatMoney($other['TotalDeposit']);
                // $other['TotalRollOut'] = FormatMoney($other['TotalRollOut']);
                // $other['Money'] = FormatMoney($other['Money']);
                $other['TotalProfit'] = bcsub($other['TotalDeposit'], $other['TotalRollOut'], 3);
                $other['first_recharge_amount'] = FormatMoney($other['first_recharge_amount']);
                return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
            }
            if (input('output') == 'exec') {
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                // }
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
                    $filename = lang('渠道统计') . '-' . date('YmdHis');
                    $rows =& $result['list'];
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
            if ($ProxyId != '') {
                $ChannelID = $db->getTableObject('T_ProxyChannelConfig')->where('ProxyId', $ProxyId)->value('RoleID') ?: 0;
            }
            if ($RoleID != '') {
                if ($ChannelID != 0 && $ChannelID != $RoleID) {
                    $ChannelID = 0;
                } else {
                    $ChannelID = $RoleID;
                }

            }
            $data = [];
            //在线人数
            $channelusers = $db->DBOriginQuery('SELECT RoleID FROM [CD_UserDB].[dbo].[View_UserTeam]');
            $channelusers = array_column($channelusers, 'RoleID');
            // var_dump($channelusers);die();
            $data['online'] = $this->GetOnlineUserlist2('list')['total'] ?: [];
            $data['online'] = count(array_intersect($channelusers, $data['online']));

            $data['pay'] = $db->setTable('T_GameStatisticChannelPay')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['system'] = $db->setTable('T_GameStatisticChannelTotal')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['user'] = $db->setTable('T_GameStatisticChannelUser')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['out'] = $db->setTable('T_GameStatisticChannelPayOut')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
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
            $data['activity'][54] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=54 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][15] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType in (15,59,60) and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][61] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=61 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][101] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=101 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][102] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=102 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][67] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=67 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][68] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=68 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

            $data['activity'][54] = FormatMoney($data['activity'][54]);
            $data['activity'][15] = FormatMoney($data['activity'][15]);
            $data['activity'][61] = FormatMoney($data['activity'][61]);
            $data['activity'][101] = FormatMoney($data['activity'][101]);
            $data['activity'][102] = FormatMoney($data['activity'][102]);
            $data['activity'][67] = FormatMoney($data['activity'][67]);
            $data['activity'][68] = FormatMoney($data['activity'][68]);


            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=11 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][54] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=54 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][72] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=72 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

            $data['cj'][65] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=65 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][66] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=66 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][69] = $db->getActivityChannelReceiveSum()->getValueByTable('adddate=\'' . $date . '\' and ChangeType=69 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

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
            if ($ProxyId != '') {
                $ChannelID = $db->getTableObject('T_ProxyChannelConfig')->where('ProxyId', $ProxyId)->value('RoleID') ?: 0;
            }
            if ($RoleID != '') {
                if ($ChannelID != 0 && $ChannelID != $RoleID) {
                    $ChannelID = 0;
                } else {
                    $ChannelID = $RoleID;
                }

            }
            $data = [];
            //在线人数
            $data['online'] = $this->GetOnlineUserlist2('onlinenum') ?: 0;
            $data['pay'] = $db->setTable('view_GameStatisticChannelPay')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['recharge'] = $db->setTable('View_PayChannelChannelMonth')->getListTableAll('addtime=\'' . '\'', '*') ?: [];
            $data['out'] = $db->setTable('view_GameStatisticChannelPayOut')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['system'] = $db->setTable('view_GameStatisticChannelTotal')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
            $data['user'] = $db->setTable('view_GameStatisticChannelUser')->GetRow('mydate=\'' . $date . '\' and ChannelID=' . $ChannelID, '*') ?: [];
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
            $data['activity'][54] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][15] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType in (15,59,60) and ChannelID=' . $ChannelID, 'ReceiveAmt');
            $data['activity'][61] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=61 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][101] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=101 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][102] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=102 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][67] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=67 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['activity'][68] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=68 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

            $data['activity'][54] = FormatMoney($data['activity'][54]);
            $data['activity'][15] = FormatMoney($data['activity'][15]);
            $data['activity'][61] = FormatMoney($data['activity'][61]);
            $data['activity'][101] = FormatMoney($data['activity'][101]);
            $data['activity'][102] = FormatMoney($data['activity'][102]);
            $data['activity'][67] = FormatMoney($data['activity'][67]);
            $data['activity'][68] = FormatMoney($data['activity'][68]);

            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=11 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][54] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][72] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=72 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

            //统计
            $data['cj'][65] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=65 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][66] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=66 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;
            $data['cj'][69] = $db->getActivityChannelReceiveSum()->getTableSum('adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=69 and ChannelID=' . $ChannelID, 'ReceiveAmt') ?: 0;

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
    public function remainFirstCharge()
    {
        $order = "mydate desc";
        $roleid = input('RoleID', 0);
        $start = input('strartdate');
        $end = input('enddate');
        $typeid =input('typeid',111);
        $where = " and typeid=".$typeid;
        if ($start != null && $end != null) {
            $where = "And mydate BETWEEN '$start' And '$end'";
        }
        if ($roleid > 0) {
            $where .= ' and  ChannelID=' . $roleid;
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
                    $filename = lang('首充留存') . '-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
        $this->assign('roleid', $roleid);
        return $this->fetch();
    }


    public function proxyChannelStatic()
    {
        $proxychannelId = $this->request->param('RoleID');
        $date = $this->request->param('date');
        if (empty($date)) {
            $date = date('Y-m');
        }
        $db = new GameOCDB();
        $where = ' OperatorId=' . session('merchant_OperatorId');
        if ($proxychannelId) {
            $where .= ' and channelid=' . $proxychannelId;
        }

        $firstdate = $date . '-01';
        $where .= " and Date>='$firstdate' ";
        $lasttime = strtotime("$firstdate +1 month -1 day");
        $lastdate = date('Y-m-d', $lasttime);
        $where .= " and Date<='$lastdate'";

        $total = $db->getTableObject('T_ChannelDailyCollect')->where($where)->field('sum(convert(bigint,TotalRecharge)) TotalRecharge,sum(convert(bigint,TotalDrawMoney)) TotalPayOut,sum(convert(bigint,PPBet)) as ppgamewin,sum(convert(bigint,PGBet)) as pggamewin,sum(convert(bigint,EvoLiveBet)) as evolivewin,sum(convert(bigint,Spribe)) as spribegamewin,sum(convert(bigint,habawin)) as habawin,sum(convert(bigint,hacksaw)) as hacksaw,sum(convert(bigint,JiLiBet)) as jiliwin,sum(convert(bigint,yesbingo)) as yesbingo,sum(convert(bigint,fcgame)) as fcgame,sum(convert(bigint,tadagame)) as tadagame')->find();

        $data['total_recharge'] = FormatMoney($total['TotalRecharge'] ?? 0);
        $data['totalpayout'] = FormatMoney($total['TotalPayOut'] ?? 0);

        $config = (new MasterDB)->getTableObject('T_OperatorLink')->where('OperatorId', session('merchant_OperatorId'))->find();
        $data['recharge_fee'] = bcmul($data['total_recharge'], $config['RechargeFee'], 3);
        $data['payout_fee'] = bcmul($data['totalpayout'], $config['WithdrawalFee'], 3);

        $APIFee = explode(',', $config['APIFee']);
        $APIFee[0] = $APIFee[0] ?? 0; //pp
        $APIFee[1] = $APIFee[1] ?? 0; //pg
        $APIFee[2] = $APIFee[2] ?? 0; //evo
        $APIFee[3] = $APIFee[3] ?? 0; //spribe游戏
        $APIFee[4] = $APIFee[4] ?? 0; //haba
        $APIFee[5] = $APIFee[5] ?? 0; //hacksaw
        $APIFee[6] = $APIFee[6] ?? 0; //jiliwin
        $APIFee[7] = $APIFee[7] ?? 0; //yesbingo
        $APIFee[8] = $APIFee[8] ?? 0; //jiliwin
        $APIFee[9] = $APIFee[9] ?? 0; //yesbingo

        $TotalAPICost = 0;
        $totalpp = bcmul($APIFee[0], $total['ppgamewin'], 4);
        $totalpg = bcmul($APIFee[1], $total['pggamewin'], 4);
        $totalevo = bcmul($APIFee[2], $total['evolivewin'], 4);
        $totalspribe = bcmul($APIFee[3], $total['spribegamewin'], 4);
        $totalhaba = bcmul($APIFee[4], $total['habawin'], 4);
        $totalhacksaw = bcmul($APIFee[5], $total['hacksaw'], 4);
        $totaljiliwin = bcmul($APIFee[6], $total['jiliwin'], 4);
        $totalyesbingo = bcmul($APIFee[7], $total['yesbingo'], 4);
        $tadagame = bcmul($APIFee[8], $total['tadagame'], 4);
        $fcgame = bcmul($APIFee[9], $total['fcgame'], 4);

        if ($totalpp < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpp);
        }
        if ($totalpg < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpg);
        }
        if ($totalevo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalevo);
        }

        if ($totalhaba < 0) {//系统赢算费用
            $TotalAPICost += abs($totalhaba);
        }

        if ($totalhacksaw < 0) {//系统赢算费用
            $TotalAPICost += abs($totalhacksaw);
        }

        if ($totalspribe < 0) {//系统赢算费用
            $TotalAPICost += abs($totalspribe);
        }
        if ($totaljiliwin < 0) {//系统赢算费用
            $TotalAPICost += abs($totaljiliwin);
        }

        if ($totalyesbingo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalyesbingo);
        }
        if ($tadagame < 0) {//系统赢算费用
            $TotalAPICost += abs($tadagame);
        }
        if ($fcgame < 0) {//系统赢算费用
            $TotalAPICost += abs($fcgame);
        }

        $data['TotalAPICost'] = FormatMoney($TotalAPICost);
        $data['totalprofit'] = round(($data['total_recharge']) - ($data['totalpayout'] + $data['recharge_fee'] + $data['payout_fee'] + $data['TotalAPICost']), 3);

        if ($this->request->isAjax()) {
            if (empty($total)) {
                return $this->failJSON(lang('该月份没有任何数据'));

            } else {
                $data['onlinedata'] = config('record_start_time');
                return $this->successJSON($data);
            }
        }

        $this->assign('onlinedata', config('record_start_time'));
        $this->assign('data', $data);
        $this->assign('proxychannelId', $proxychannelId);
        $this->assign('thismonth', date('Y-m'));
        return $this->fetch();

    }

    public function toIndex()
    {

        $ProxyChannelId = request()->param('ProxyChannelId');
        $userInfo = (new \app\model\GameOCDB)->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', $ProxyChannelId)->find();
        if ($userInfo) {
            session('business_LoginAccount', $userInfo['LoginAccount']);
            session('business_ProxyChannelId', $userInfo['ProxyChannelId']);
            session('business_OperatorId', $userInfo['OperatorId']);
            session('business_Proxytype', $userInfo['type']);
            return json(['code' => 0]);
        } else {
            return json(['code' => 1]);
        }
    }

    public function toIndex2()
    {
        $url = url('business/index/index');
        $this->redirect($url);
    }

    ///盈利报表
    public function profitStatement()
    {
        $this->assign('thismonth', date('Y-m'));
        return $this->fetch();
    }

    //运营商详情
    public function operatorSummaryData()
    {
        $proxychannelId = $this->request->param('proxychannelId');
        $date = $this->request->param('date');
        if (empty($date)) {
            $date = date('Y-m');
        }
        $db = new GameOCDB();
        $proxy_channel_info = $db->getTableObject('T_ProxyChannelConfig')->where('OperatorId', session('merchant_OperatorId'))->column('ProxyChannelId,AccountName', 'ProxyChannelId');
        $channelIds = array_column($proxy_channel_info, 'ProxyChannelId');
        if (empty($channelIds)) {
            return $this->apiReturn(0, [], 'success');
        }
        $where = ' ChannelId in(' . implode(',', $channelIds) . ')';
        if ($proxychannelId != '') {
            $where .= ' and ChannelId=' . $proxychannelId;
        }


        $firstdate = $date . '-01';
        $where .= " and Date>='$firstdate' ";
        $lasttime = strtotime("$firstdate +1 month -1 day");
        $lastdate = date('Y-m-d', $lasttime);
        $where .= " and Date<='$lastdate'";

        $total_data = $db->getTableObject('T_ChannelDailyCollect')->where($where)->field('ChannelId as ProxyChannelId,sum(convert(bigint,TotalRecharge)) total_recharge,sum(convert(bigint,TotalDrawMoney)) totalpayout,sum(convert(bigint,PPBet)) as ppgamewin,sum(convert(bigint,PGBet)) as pggamewin,sum(convert(bigint,EvoLiveBet)) as evolivewin,sum(convert(bigint,Spribe)) as spribegamewin,sum(convert(bigint,habawin)) as habawin')->group('ChannelId')->select();

        $config = (new MasterDB)->getTableObject('T_OperatorLink')->where('OperatorId', session('merchant_OperatorId'))->find();
        foreach ($total_data as $key => &$data) {
            $data['ProxyChannelName'] = $proxy_channel_info[$data['ProxyChannelId']]['AccountName'];
            $data['totalpayorder'] = FormatMoney($data['total_recharge'] ?? 0);
            $data['totalpayout'] = FormatMoney($data['totalpayout'] ?? 0);

            $data['rechargefee'] = bcmul($data['totalpayorder'], $config['RechargeFee'], 3);
            $data['withdrawfee'] = bcmul($data['totalpayout'], $config['WithdrawalFee'], 3);

            $APIFee = explode(',', $config['APIFee']);
            $APIFee[0] = $APIFee[0] ?? 0; //pp
            $APIFee[1] = $APIFee[1] ?? 0; //pg
            $APIFee[2] = $APIFee[2] ?? 0; //evo
            $APIFee[3] = $APIFee[3] ?? 0; //spribe游戏
            $APIFee[4] = $APIFee[4] ?? 0; //haba
//            $APIFee[5] = $APIFee[5] ?? 0; //hacksaw

            $TotalAPICost = 0;
            $totalpp = bcmul($APIFee[0], $data['ppgamewin'], 4);
            $totalpg = bcmul($APIFee[1], $data['pggamewin'], 4);
            $totalevo = bcmul($APIFee[2], $data['evolivewin'], 4);
            $totalspribe = bcmul($APIFee[3], $data['spribegamewin'], 4);
            $totalhaba = bcmul($APIFee[4], $data['habawin'], 4);
//            $totalhacksaw = bcmul($APIFee[5], $data['hacksaw'], 4);

            if ($totalpp < 0) {//系统赢算费用
                $TotalAPICost += abs($totalpp);
            }
            if ($totalpg < 0) {//系统赢算费用
                $TotalAPICost += abs($totalpg);
            }
            if ($totalevo < 0) {//系统赢算费用
                $TotalAPICost += abs($totalevo);
            }

            if ($totalhaba < 0) {//系统赢算费用
                $TotalAPICost += abs($totalhaba);
            }

//            if ($totalhacksaw < 0) {//系统赢算费用
//                $TotalAPICost += abs($totalhacksaw);
//            }

            if ($totalspribe < 0) {//系统赢算费用
                $TotalAPICost += abs($totalspribe);
            }

            $data['apicost'] = FormatMoney($TotalAPICost);
            $data['totalprofit'] = round(($data['totalpayorder']) - ($data['totalpayout'] + $data['rechargefee'] + $data['withdrawfee'] + $data['apicost']), 3);
        }

        return $this->apiReturn(0, $total_data, 'success');

    }


    /**
     * 业务员对账
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function channelReconciliation()
    {
        if ($this->request->isAjax()){
            $OperatorSessionId = session('merchant_OperatorId');
            $time = $this->request->param('time');
            $channelId = $this->request->param('channel_id');
            $GameOCDB = new GameOCDB();
            //业务员
            $channels = $GameOCDB->getTableObject('T_ProxyChannelConfig')
                ->where('OperatorId', $OperatorSessionId)
                ->where(function ($q) use($channelId){
                    if($channelId){
                        $q->where('ProxyChannelId',$channelId);
                    }
                })
                ->column('ProxyChannelId,AccountName');
//dump($channels);die();
            $channelIds = [];
            foreach ($channels as $id){
                $channelIds[] = $id['ProxyChannelId'];
            }
            $data = [];
            if (!empty($channelIds)) {
                $channelDaliyCollect = $GameOCDB->getTableObject('T_ChannelDailyCollect')
                    ->field('ChannelId,sum(convert(bigint,TotalRecharge)) totalrecharge,
                sum(convert(bigint,TotalDrawMoney)) totalpayout,
                sum(convert(bigint,PPBet)) as ppgamewin,
                sum(convert(bigint,PGBet)) as pggamewin,
                sum(convert(bigint,EvoLiveBet)) as evolivewin,
                sum(convert(bigint,Spribe)) as spribegamewin,
                sum(convert(bigint,habawin)) as habawin,
                sum(convert(bigint,yesbingo)) as yesbingo,
                sum(convert(bigint,Spribe)) as Spribe,
                sum(convert(bigint,tadagame)) as tadagame,
                sum(convert(bigint,fcgame)) as fcgame,
                sum(convert(bigint,pplive)) as pplive')
                    ->where(function ($q) use ($time){
                        if ($time){
                            $beginTime = date('Y-m-01 00:00:00',strtotime($time));
                            $endTime = date('Y-m-31 23:59:59',strtotime($time));
                            $q->where('Date','between time',[$beginTime,$endTime]);
                        }
                    })
                    ->whereIn('ChannelId', $channelIds)
                    ->group('ChannelId')
                    ->select();
                $config = (new MasterDB)->getTableObject('T_OperatorLink')
                    ->where('OperatorId',$OperatorSessionId)
                    ->find();
                foreach ($channelDaliyCollect as $channel) {
                    $item = [];
                    foreach ($channels as $id){
                        if ($id['ProxyChannelId'] == $channel['ChannelId']){
                            $item['AccountName'] = $id['AccountName'];
                        }
                    }
                    $item['ProxyChannelId'] = $channel['ChannelId'];
                    $item['totalInCash'] = FormatMoney($channel['totalrecharge']);
                    $item['totalOutCah'] = FormatMoney($channel['totalpayout']);
                    $item['rechargeCommission'] = bcmul($item['totalInCash'], $config['RechargeFee'], 3);
                    $item['withdrawalCommission'] = bcmul($item['totalOutCah'], $config['WithdrawalFee'], 3);
                    $item['apiCommission'] = $this->getTotalAPICost($config,$channel);
                    $item['totalProfit'] = bcsub(($item['totalInCash']) , $item['totalOutCah'], 3);

                    $data[] = $item;
                }
            }
            return $this->apiReturn(0, $data, 'success');
        }
        $this->assign('thismonth', date('Y-m'));
        return $this->fetch();

    }

    public function getTotalAPICost($config,$data)
    {
        $APIFee = explode(',', $config['APIFee']);
        $APIFee[0] = $APIFee[0] ?? 0; //pp
        $APIFee[1] = $APIFee[1] ?? 0; //pg
        $APIFee[2] = $APIFee[2] ?? 0; //evo
        $APIFee[3] = $APIFee[3] ?? 0; //JDB
        $APIFee[4] = $APIFee[4] ?? 0; //habawin
        $APIFee[5] = $APIFee[5] ?? 0; //hacksaw
        $APIFee[6] = $APIFee[6] ?? 0; //JILI
        $APIFee[7] = $APIFee[7] ?? 0; //YES!BINGO
        $APIFee[8] = $APIFee[8] ?? 0; //tadagame
        $APIFee[9] = $APIFee[9] ?? 0; //fcgame
        $APIFee[10] = $APIFee[10] ?? 0; //pplive

        $totalpp = bcmul($APIFee[0], $data['ppgamewin'], 4);
        $totalpg = bcmul($APIFee[1], $data['pggamewin'], 4);
        $totalevo = bcmul($APIFee[2], $data['evolivewin'], 4);
        $totalspribe = bcmul($APIFee[3], $data['spribewin'], 4);
        $habawin = bcmul($APIFee[4], $data['habawin'], 4);
        $hackSaw = bcmul($APIFee[5], $data['hacksaw'], 4);
        $jiliwin = bcmul($APIFee[6], $data['jiliwin'], 4);
        $yesbingo = bcmul($APIFee[7], $data['yesbingo'], 4);
        $tadagame = bcmul($APIFee[8], $data['tadagame'], 4);
        $fcgame = bcmul($APIFee[9], $data['fcgame'], 4);
        $pplive = bcmul($APIFee[10], $data['pplive'], 4);

        $TotalAPICost = 0;
        if ($totalpp < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpp);
        }
        if ($totalpg < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpg);
        }
        if ($totalevo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalevo);
        }
        if ($totalspribe < 0) {//系统赢算费用
            $TotalAPICost += abs($totalspribe);
        }

        if ($habawin < 0) {//系统赢算费用
            $TotalAPICost += abs($habawin);
        }

        if ($hackSaw < 0) {//系统赢算费用
            $TotalAPICost += abs($hackSaw);
        }
        if ($jiliwin < 0) {//系统赢算费用
            $TotalAPICost += abs($jiliwin);
        }

        if ($yesbingo < 0) {//系统赢算费用
            $TotalAPICost += abs($yesbingo);
        }

        if ($tadagame < 0) {//系统赢算费用
            $TotalAPICost += abs($tadagame);
        }

        if ($fcgame < 0) {//系统赢算费用
            $TotalAPICost += abs($fcgame);
        }

        if ($pplive < 0) {//系统赢算费用
            $TotalAPICost += abs($pplive);
        }

        $data['TotalAPICost'] = round($TotalAPICost, 3);
        $data['totalprofit'] = round(($data['total_recharge']) - ($data['totalpayout'] + $data['recharge_fee'] + $data['payout_fee'] + $data['TotalAPICost']), 3);
        return FormatMoney($TotalAPICost);
    }
}