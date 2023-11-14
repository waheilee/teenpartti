<?php

namespace app\business\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\UserDB;
use app\model\GameOCDB;
use app\model\MasterDB;
use think\Db;
use think\Exception;

class Channel extends Main
{


    public function __construct()
    {
        parent::__construct();

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
                $where .= " and b.AccountName like '%" . $parentName . "%'";
            }
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');
            $where .= " and a.ProxyChannelId in (" . implode(',', $all_ProxyChannelId) . ")";
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
                $val['InviteUrl'] = str_replace("{inviteCode}", $val['ProxyChannelId'], $InviteUrlModel);
                if ($val['type'] == 1) {
                    $val['type'] = lang('业务组长');
                } else {
                    $val['type'] = lang('普通业务员');
                }
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        return $this->fetch();
    }

    public function businessEdit()
    {

        if ($this->request->method() == 'POST') {

            $ProxyId = $this->request->param('ProxyId');
            $AccountName = $this->request->param('AccountName');
            $password = $this->request->param('password') ?: '';
            $LoginAccount = $this->request->param('LoginAccount') ?: '';
            $RoleID = $this->request->param('RoleID');

            $m = new \app\model\GameOCDB();
            $data = [];
            $data['AccountName'] = $AccountName;
            $data['LoginAccount'] = $LoginAccount;

            if ($password) {
                $data['PassWord'] = md5($password);
            }
            if ($RoleID) {
                $has_AccountName = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', '<>', $RoleID)->where('LoginAccount', $LoginAccount)->find();
                if ($has_AccountName) {
                    return $this->apiReturn(1, '', '登陆账号已存在');
                }
                $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
                $all_ProxyChannelId[] = session('business_ProxyChannelId');
                if (!in_array($RoleID, $all_ProxyChannelId)) {
                    return $this->apiReturn(1, '', '编辑失败');
                }
                $res = $m->getTableObject('T_ProxyChannelConfig')
                    ->where('ProxyChannelId', $RoleID)
                    ->find();
                if (empty($res)) {
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
                $parentinfo = $m->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', session('business_ProxyChannelId'))->find();
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
                    $data['OperatorId'] = (int)$parentinfo['OperatorId'];
                    $data['pid'] = session('business_ProxyChannelId');
                    $data['type'] = 2;
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
        $RoleID = $this->request->param('RoleID');
        if ($RoleID) {
            $data = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', $RoleID)->find();
        } else {
            $data = [];
            $data['pid'] = 0;
            $data['type'] = 2;
        }
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
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');

            $data = (new \app\model\GameOCDB())->getTableObject('T_ChannelDailyCollect')->alias('a')
                ->join('T_ProxyChannelConfig b', 'b.ProxyChannelId=a.ChannelId')
                ->where('b.ProxyChannelId', 'in', $all_ProxyChannelId)
                ->where($where)
                ->field('a.*,b.AccountName,b.pid')
                ->order('Date desc')
                ->paginate($limit)
                ->toArray();
            $parents = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('type', '>', 0)->field('ProxyChannelId,pid,AccountName')->select();

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
                ->where('b.ProxyChannelId', 'in', $all_ProxyChannelId)
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

    public function getXbusiness2($ProxyChannelIds)
    {
        static $result2 = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid', 'in', $ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result2;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result2 = array_unique(array_merge($result2, $xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
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


    public function proxyChannelStatic()
    {
        $proxychannelId = $this->request->param('RoleID');
        $date = $this->request->param('date');
        if (empty($date)) {
            $date = date('Y-m');
        }
        $db = new GameOCDB();

        $where = ' channelid=' . $proxychannelId;

        $firstdate = $date . '-01';
        $where .= " and Date>='$firstdate' ";
        $lasttime = strtotime("$firstdate +1 month -1 day");
        $lastdate = date('Y-m-d', $lasttime);
        $where .= " and Date<='$lastdate'";

        $total = $db->getTableObject('T_ChannelDailyCollect')->where($where)->field('sum(convert(bigint,TotalRecharge)) TotalRecharge,
        sum(convert(bigint,TotalDrawMoney)) TotalPayOut,
        sum(convert(bigint,PPBet)) as ppgamewin,
        sum(convert(bigint,PGBet)) as pggamewin,
        sum(convert(bigint,EvoLiveBet)) as evolivewin,      
        sum(convert(bigint,JiLiBet)) as jiligamewin,      
         sum(convert(bigint,habawin)) as habawin,
        sum(convert(bigint,yesbingo)) as yesbingo,
         sum(convert(bigint,Spribe)) as Spribe
        ')->find();

        $data['total_recharge'] = FormatMoney($total['TotalRecharge'] ?? 0);
        $data['totalpayout'] = FormatMoney($total['TotalPayOut'] ?? 0);
        $OperatorId = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('ProxyChannelId', $proxychannelId)->value('OperatorId');
        $config = (new MasterDB)->getTableObject('T_OperatorLink')->where('OperatorId', $OperatorId)->find();
        $data['recharge_fee'] = bcmul($data['total_recharge'], $config['RechargeFee'], 3);
        $data['payout_fee'] = bcmul($data['totalpayout'], $config['WithdrawalFee'], 3);

        $APIFee = explode(',', $config['APIFee']);
        $APIFee[0] = $APIFee[0] ?? 0; //pp
        $APIFee[1] = $APIFee[1] ?? 0; //pg
        $APIFee[2] = $APIFee[2] ?? 0; //evo

        $APIFee[3] = $APIFee[3] ?? 0; //spribegamewin
        $APIFee[4] = $APIFee[4] ?? 0; //habawin
//        $APIFee[5] = $APIFee[5] ?? 0; //hacksaw
        $APIFee[5] = $APIFee[5] ?? 0; //JILI
        $APIFee[6] = $APIFee[6] ?? 0; //yesbingo


        $TotalAPICost = 0;
        $totalpp = bcmul($APIFee[0], $total['ppgamewin'], 4);
        $totalpg = bcmul($APIFee[1], $total['pggamewin'], 4);
        $totalevo = bcmul($APIFee[2], $total['evolivewin'], 4);

        $totaSpribe = bcmul($APIFee[3], $total['Spribe'], 4);
        $totalhabawin = bcmul($APIFee[4], $total['habawin'], 4);
//        $totalhacksaw = bcmul($APIFee[5], $total['hacksaw'], 4);
        $totaljiligamewin = bcmul($APIFee[5], $total['jiligamewin'], 4);
        $totalyesbingo = bcmul($APIFee[6], $total['yesbingo'], 4);

        if ($totalpp < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpp);
        }
        if ($totalpg < 0) {//系统赢算费用
            $TotalAPICost += abs($totalpg);
        }
        if ($totalevo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalevo);
        }

        if ($totaSpribe < 0) {//系统赢算费用
            $TotalAPICost += abs($totaSpribe);
        }

        if ($totalhabawin < 0) {//系统赢算费用
            $TotalAPICost += abs($totalhabawin);
        }

//        if ($totalhacksaw < 0) {//系统赢算费用
//            $TotalAPICost += abs($totalhacksaw);
//        }

        if ($totaljiligamewin < 0) {//系统赢算费用
            $TotalAPICost += abs($totaljiligamewin);
        }

        if ($totalyesbingo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalyesbingo);
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
}