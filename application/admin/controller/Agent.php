<?php

namespace app\admin\controller;

use app\admin\controller\Export\AgentWaterDailyExport;
use app\model\AccountDB;
use app\model\BankDB;
use app\model\DataChangelogsDB;
use app\model\GameConfig;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\ProxyAccount;
use app\model\SysConfig;
use app\model\UserDB;
use app\model\UserProxyDailyData;
use app\model\UserProxyInfo;
use app\model\UserTeamLog;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\response\Json;
use think\View;
use redis\Redis;
use app\model\ActivityRecord;
use app\model\User as userModel;
use app\common\GameLog;

/**
 * Class PkMatch
 * @package app\admin\controller
 */
class Agent extends Main
{
    public function AgentConfig()
    {
        return $this->fetch();
    }

    /**
     * 开关配置视图
     * @return Json|View|void
     */
    public function AgentWaterReturn()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->AgentInviteAward()->GetPage();
                return $this->apiJson($result);
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('config_edit');
            case 'edit':
                $request = request()->request();
                $ID = $request['ID'];
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->AgentInviteAward()->UPData($request, "ID=$ID");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
            case 'addView':
                $request = request()->request();
                $request['Action'] = 'add';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('config_edit');
            case 'add':
                $request = request()->request();
                unset($request['s'], $request['Action']);
                $db = new  MasterDB();
                $row = $db->AgentInviteAward()->Insert($request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');
        }
        return $this->fetch();

    }

    /**
     * 代理推荐奖励配置
     * @return mixed|Json|void
     * @throws PDOException
     */
    public function AgentInviteAward()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->AgentWaterReturn()->GetPage();
                return $this->apiJson($result);
            case 'addView':
                $request = ['ID' => null, 'LevelDesc' => null, 'MinWater' => null, 'MaxWater' => null, 'ReturnPercent' => null, 'Action' => 'add'];
                $this->assign('info', $request);
                return $this->fetch('agent_invite_award');
            case 'add':
                $request = request()->request();
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->AgentWaterReturn()->Insert($request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('agent_invite_award');
            case 'edit':
                $request = request()->request();
                $ID = $request['ID'];
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->AgentWaterReturn()->UPData($request, "ID=$ID");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
        }

    }

    /**
     * 代理推荐有效奖励配置
     * @return mixed|Json|void
     * @throws PDOException
     */
    public function AgentValidInviteAward()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->AgentValidInviteAward()->GetPage();
                return $this->apiJson($result);
            case 'addView':
                $request = ['ID' => null, 'InviteCount' => null, 'AwardMoney' => null, 'Action' => 'add'];
                $this->assign('info', $request);
                return $this->fetch('agent_valid_invite_award');
            case 'add':
                $request = request()->request();
                unset($request['s'], $request['Action']);

                $db = new  MasterDB();
                $row = $db->AgentValidInviteAward()->Insert($request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('agent_valid_invite_award');
            case 'edit':
                $request = request()->request();
                $ID = $request['ID'];
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->AgentValidInviteAward()->UPData($request, "ID=$ID");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
        }

    }


    /**
     * 代理报表
     * @return Json|void
     * @throws PDOException
     */
    public function AgentReport()
    {
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetAgentTotal();
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                $db = new GameOCDB();
                $data = $db->GetAgentTotal();
                if (empty($data)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['list'];
                $result['count'] = $data['count'];
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
                        lang('代理用户充值') => 'string',
                        lang('代理用户转出') => "string",
                        lang('盈亏') => 'string',
                    ];
                    $filename = lang('代理盈亏') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $row['UserOut'] = FormatMoney($row['UserOut']);
                        $row['yk'] = FormatMoney($row['UserOut']);

                        $item = [
                            $row['MyDate'],
                            $row['UserPay'],
                            $row['UserOut'],
                            $row['yk'] = $row['UserPay'] - $row['Agent'] - FormatMoney($row['UserOut'])
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }
        return $this->fetch();
    }


    public function datastatistic()
    {
        if ($this->request->isajax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $userteamlog = new UserTeamLog();
            $list = $userteamlog->getList([], $page, $limit, '*', 'Date desc');
            $count = $userteamlog->getCount([]);
            foreach ($list as $key => &$v) {
                $v['TeamReward'] = FormatMoney($v['TeamReward']);
                $v['TeamRewardTotal'] = FormatMoney($v['TeamRewardTotal']);

                $v['TeamTotalPay'] = bcdiv($v['TeamTotalPay'], bl, 2);
                $v['TeamTotalDraw'] = bcdiv($v['TeamTotalDraw'], bl, 2);
                $v['TeamTotalCaiJin'] = bcdiv($v['TeamTotalCaiJin'], bl, 2);
                $v['TeamTotalyk'] = bcsub($v['TeamTotalPay'], $v['TeamTotalDraw'], 2);

            }
            unset($v);
            return $this->apiReturn(0, $list, '', $count);
        }
        $data = [];
        $table = '(select u.roleid  FROM [CD_UserDB].[dbo].[T_UserProxyInfo]  u  left join  T_Role as r on u.roleid=r.roleid  where u.ParentID=0 and u.DirectCount>0 and  r.AddTime>=\'' . date('Y-m-d') . '\') as t ';
        $userdb = new UserDB();
        $today_reg = $userdb->DBQuery($table, 'count(1) as total', '');
        $today_regnum = is_null($today_reg[0]['total']) ? 0 : $today_reg[0]['total'];
        $data['today_register_count'] = $today_regnum;
        $m = new UserProxyInfo();
        $data['total_register_count'] = $m->getCount('ParentID=0 and DirectCount>0');
        $dailyM = new UserProxyDailyData();
        $data['today_profit'] = $dailyM->getSum(['Date' => date('Y-m-d')], 'RewardAmount');
        $data['today_profit'] = $data['today_profit'] > 0 ? FormatMoney($data['today_profit']) : 0;

        $data['total_profit'] = $dailyM->getSum(['RewardAmount' => ['>', 0]], 'RewardAmount');

        $data['total_profit'] = $data['total_profit'] > 0 ? FormatMoney($data['total_profit']) : 0;
        $bankdb = new BankDB();
        $total_draw = $bankdb->DBQuery('userdrawback', 'sum(iMoney) as total', ' where RecordType=1 and datediff(d,UpdateTime,\'' . date('Y-m-d H:i:s') . '\')=0 and status in(100)');

        $today_with = is_null($total_draw[0]['total']) ? 0 : $total_draw[0]['total'];
        $data['today_with'] = $today_with;
        $total_draw = $bankdb->DBQuery('userdrawback', 'sum(iMoney) as total', ' where RecordType=1 and status in(100) ');
        $total_with = is_null($total_draw[0]['total']) ? 0 : $total_draw[0]['total'];
        $data['total_with'] = $total_with;

        $data['today_with'] = $data['today_with'] > 0 ? FormatMoney($data['today_with']) : 0;
        $data['total_with'] = $data['total_with'] > 0 ? FormatMoney($data['total_with']) : 0;

        $userteamlog = new UserTeamLog();
        $sql = 'select sum(TeamTotalPay) as TeamTotalPay,sum(TeamTotalDraw) as TeamTotalDraw,sum(TeamTotalCaiJin) as TeamTotalCaiJin  from T_UserTeamLog';
        $result = $userteamlog->getTableQuery($sql);
        if (!empty($result)) {
            foreach ($result as $k => &$v) {
                $data['TeamTotalPay'] = FormatMoney($v['TeamTotalPay']);
                $data['TeamTotalDraw'] = FormatMoney($v['TeamTotalDraw']);
                $data['TeamTotalCaiJin'] = FormatMoney($v['TeamTotalCaiJin']);
                $yk = bcsub($v['TeamTotalPay'], $v['TeamTotalDraw']);
                $data['TeamTotalyk'] = FormatMoney($yk);
            }
        }

        $this->assign('data', $data);
        return $this->fetch();
    }


    //流水模式汇总
    public function AgentWaterSum()
    {
        $parentid = input('parentid', 0);
        if (input('Action') == 'list') {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('RoleID', 0);
            $todaywater = input('todaywater', 0);
            $teamnum = input('teamnum', 0);
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $filter = 'ProxyId>0 ';
            if ($roleid != null) {
                if ($parentid > 0) {
                    $filter .= ' and ParentID =' . $parentid;//' and DirectCount>0';
                }
                $filter .= ' and ProxyId = ' . $roleid;
            }

            if ($parentid > 0) {
                $filter .= ' and ParentID =' . $parentid;//' and DirectCount>0';
            }
//            else{
//                $filter .= ' and ParentID=0' ;
//            }


            $order = 'ProxyId desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }

            $userDB = new UserDB();
            $query = "IF OBJECT_ID('T_ProxyUnBind', 'U') IS NOT NULL
                        SELECT 1 AS tableExists
                      ELSE
                        SELECT 0 AS tableExists";
            $isUnBindTable = $userDB->DBOriginQuery($query);
            if ($isUnBindTable[0]['tableExists'] == 1) {
                $unBindCount = ',C.UnBindCount';
                $leftJoin = ' left join (SELECT ParentId, COUNT(ParentId) as UnBindCount FROM [CD_UserDB].[dbo].[T_ProxyUnBind]  group by ParentId) as C on A.RoleID=C.ParentId';
            } else {
                $leftJoin = '';
                $unBindCount = '';
            }

            $field = 'A.ParentID,A.RoleId as ProxyId,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1DepositPlayers,0) AS Lv1DepositPlayers,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2DepositPlayers,0) AS Lv2DepositPlayers,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3DepositPlayers,0) AS Lv3DepositPlayers,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running,A.MobileBackgroundSwitch'.$unBindCount;
            $proxyinfo = new UserProxyInfo();
            //$table = 'T_UserProxyInfo';
            $table = '(select ' . $field . '  FROM   CD_UserDB.dbo.T_UserProxyInfo(nolock) as A  left join [CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as B on A.RoleID=B.ProxyId '.$leftJoin.') as t ';
            // $table='[CD_UserDB].[dbo].[T_ProxyCollectData] (NOLOCK) ';
            $data = $proxyinfo->getProcPageList($table, '*', $filter, $order, $page, $limit);
            $list = [];
            $count = 0;
            if ($data) {
                if ($data['count'] > 0) {
                    $list = $data['list'];
                    foreach ($list as $item => &$v) {

                        //$v['TotalDeposit'] = FormatMoney($v['TotalDeposit']);
                        $v['TotalTax'] = FormatMoney($v['TotalTax']);
                        $v['TotalRunning'] = FormatMoney($v['TotalRunning']);


                        //$v['Lv1Deposit'] = FormatMoney($v['Lv1Deposit']);
                        $v['Lv1Tax'] = FormatMoney($v['Lv1Tax']);
                        $v['Lv1Running'] = FormatMoney($v['Lv1Running']);

                        // $v['Lv2Deposit'] = FormatMoney($v['Lv2Deposit']);
                        $v['Lv2Tax'] = FormatMoney($v['Lv2Tax']);
                        $v['Lv2Running'] = FormatMoney($v['Lv2Running']);

                        // $v['Lv3Deposit'] = FormatMoney($v['Lv3Deposit']);
                        $v['Lv3Tax'] = FormatMoney($v['Lv3Tax']);
                        $v['Lv3Running'] = FormatMoney($v['Lv3Running']);

                        $lv1rate = bcdiv(10, 1000, 4);
                        $lv2rate = bcdiv(5, 1000, 4);
                        $lv3rate = bcdiv(2.5, 1000, 4);
                        $Lv1Reward = bcmul($v['Lv1Running'], $lv1rate, 4);
                        $Lv2Reward = bcmul($v['Lv2Running'], $lv2rate, 4);
                        $Lv3Reward = bcmul($v['Lv3Running'], $lv3rate, 4);
                        $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
                        $v['ReceivedIncome'] = 0;
                    }
                }
                unset($v);
                $count = $data['count'];
            }
            if (input('Action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $list, '', $count);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                if (empty($list)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $list;
                $result['count'] = $count;
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
                        lang('代理ID') => 'string',
                        lang('个人总收益') => 'string',
                        lang('个人充值') => "string",
                        lang('个人流水') => 'string',
                        lang('一级人数') => 'string',
                        lang('一级充值') => 'string',
                        lang('一级充值人数') => 'string',
                        lang('一级流水') => 'string',
                        lang('二级人数') => 'string',
                        lang('二级充值') => 'string',
                        lang('二级充值人数') => 'string',
                        lang('二级流水') => 'string',
                        lang('三级人数') => 'string',
                        lang('三级充值') => 'string',
                        lang('三级充值人数') => 'string',
                        lang('三级流水') => 'string',
                    ];
                    $filename = lang('代理汇总') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $lv1rate = bcdiv(10, 1000, 4);
                        $lv2rate = bcdiv(5, 1000, 4);
                        $lv3rate = bcdiv(2.5, 1000, 4);
                        $Lv1Reward = bcmul($row['Lv1Running'], $lv1rate, 4);
                        $Lv2Reward = bcmul($row['Lv2Running'], $lv2rate, 4);
                        $Lv3Reward = bcmul($row['Lv3Running'], $lv3rate, 4);
                        $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
                        $row['ReceivedIncome'] = $rewar_amount;

                        $item = [
                            $row['ProxyId'],
                            $row['ReceivedIncome'],
                            $row['TotalDeposit'],

                            $row['Lv1PersonCount'],
                            $row['Lv1Deposit'],
                            $row['Lv1Running'],

                            $row['Lv2PersonCount'],
                            $row['Lv2Deposit'],
                            $row['Lv2Running'],

                            $row['Lv3PersonCount'],
                            $row['Lv3Deposit'],
                            $row['Lv3Running'],


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
        $m = new UserProxyInfo();
        $data['team_total'] = $m->getCount(['ParentID' => 0, 'DirectCount' => [">", 0]]);
        $dailyM = new UserProxyDailyData();
        $total_walter = $dailyM->getSum([], 'SingleTax');
        if (!empty($total_walter))
            $total_walter = FormatMoney($total_walter);
        $data['total_walter'] = $total_walter;
        $total_reward = $dailyM->getSum([], 'RewardAmount');
        $data['total_reward'] = FormatMoneyint($total_reward);

        $this->assign('data', $data);
        $this->assign('parentid', $parentid);
        return $this->fetch();
    }


    public function updateMobileBackgroundSwitch()
    {
        $RoleId = input('roleid');
        $MobileBackgroundSwitch = input('type') ?: 0;
        $res = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo')->where('RoleId=' . $RoleId)->data(['MobileBackgroundSwitch' => $MobileBackgroundSwitch])->update();
        if ($res) {
            return $this->apiReturn(0, [], '操作成功');
        } else {
            return $this->apiReturn(1, [], '操作失败');
        }
    }

    //代理裂变
    public function AgentWaterDaily()
    {
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $startdate = input('startdate', '');
        $enddate = input('enddate', '');
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetAgentRecord(true);
                $sumdata = $db->GetAgentRecordSum(true);
                $result['other'] = $sumdata;
                $result['other']['startdate'] = $result['startdate'];
                $result['other']['enddate'] = $result['enddate'];
                return $this->apiJson($result);
            case 'exec':

                $AgentWaterDailyExport = new AgentWaterDailyExport();
                $AgentWaterDailyExport->export();
//                $db = new  GameOCDB();
//                $result = $db->GetAgentRecord(true);
//                $outAll = input('outall', false);
//                if ((int)input('exec', 0) == 0) {
//                    if ($result['count'] == 0) {
//                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
//                    }
//                    if ($result['count'] >= 5000 && $outAll == false) {
//                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
//                    }
//                    unset($result['list']);
//                    return $this->apiJson($result);
//                }
//                //导出表格
//                if ((int)input('exec') == 1 && $outAll = true) {
//                    //权限验证
//                    $auth_ids = $this->getAuthIds();
//                    if (!in_array(10008, $auth_ids)) {
//                        return $this->apiReturn(1, '', '没有权限');
//                    }
//                    $header_types = [
//                        lang('日期') => 'string',
//                        lang('代理ID') => 'string',
//                        lang('1+2级打码') => 'string',
//                        lang('个人总收益') => 'string',
//                        lang('个人充值') => "string",
//                        lang('个人流水') => 'string',
//                        lang('一级人数') => 'string',
//                        lang('一级充值') => 'string',
//                        lang('一级流水') => 'string',
//                        lang('二级人数') => 'string',
//                        lang('二级充值') => 'string',
//                        lang('二级流水') => 'string',
//                        lang('三级人数') => 'string',
//                        lang('三级充值') => 'string',
//                        lang('三级流水') => 'string',
//                        lang('一级首充金额') => 'string',
//                        lang('二级首充金额') => 'string',
//                        lang('三级首充金额') => 'string',
//                        lang('一级提现金额') => 'string',
//                    ];
//                    $filename = lang('代理明细') . '-' . date('YmdHis');
//                    $rows =& $result['list'];
////                    halt($rows[0]);
//                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
//                    foreach ($rows as $index => &$row) {
//                        $lv1rate = bcdiv(10, 1000, 4);
//                        $lv2rate = bcdiv(5, 1000, 4);
//                        $lv3rate = bcdiv(2.5, 1000, 4);
//                        $Lv1Reward = bcmul($row['Lv1Running'], $lv1rate, 4);
//                        $Lv2Reward = bcmul($row['Lv2Running'], $lv2rate, 4);
//                        $Lv3Reward = bcmul($row['Lv3Running'], $lv3rate, 4);
//                        $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
//                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
//                        $row['ReceivedIncome'] = $rewar_amount;
//
//                        $item = [
//                            $row['AddTime'],
//                            $row['ProxyId'],
//                            $row['dm'],
//                            $row['ReceivedIncome'],
//                            $row['DailyDeposit'],
//                            $row['DailyRunning'],
//                            $row['Lv1PersonCount'],
//                            $row['Lv1Deposit'],
//                            $row['Lv1Running'],
//
//                            $row['Lv2PersonCount'],
//                            $row['Lv2Deposit'],
//                            $row['Lv2Running'],
//
//                            $row['Lv3PersonCount'],
//                            $row['Lv3Deposit'],
//                            $row['Lv3Running'],
//
//                            $row['Lv1FirstDepositMoney'],
//                            $row['Lv2FirstDepositMoney'],
//                            $row['Lv3FirstDepositMoney'],
//                            $row['Lv1WithdrawalMoney'],
//
//                        ];
//                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
//                        unset($rows[$index]);
//                    }
//                    unset($row, $item);
//                    $writer->writeToStdOut();
//                    exit();
//                }
//
//                break;
        }

        $this->assign('parentid', $parentid);
        $this->assign('roleid', $roleid);
        $this->assign('startdate', $startdate);
        $this->assign('enddate', $enddate);
        return $this->fetch();
    }


    //代理汇总
    public function teamlist()
    {
        $parentid = input('parentid', 0);
        if (input('Action') == 'list') {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('RoleID', 0);
            $todaywater = input('todaywater', 0);
            $teamnum = input('teamnum', 0);
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $filter = 'ProxyId>0 ';
            if ($roleid != null) {
                if ($parentid > 0) {
                    $filter .= ' and ParentID =' . $parentid;//' and DirectCount>0';
                }
                $filter .= ' and ProxyId = ' . $roleid;
            }

            if ($parentid > 0) {
                $filter .= ' and ParentID =' . $parentid;//' and DirectCount>0';
            }
//            else{
//                $filter .= ' and ParentID=0' ;
//            }


            $order = 'ProxyId desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            $field = 'A.ParentID,A.RoleId as ProxyId,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running';
            $proxyinfo = new UserProxyInfo();
            //$table = 'T_UserProxyInfo';
            $table = '(select ' . $field . '  FROM   CD_UserDB.dbo.T_UserProxyInfo(nolock) as A  left join [CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as B on A.RoleID=B.ProxyId) as t ';
            // $table='[CD_UserDB].[dbo].[T_ProxyCollectData] (NOLOCK) ';
            $data = $proxyinfo->getProcPageList($table, '*', $filter, $order, $page, $limit);
            $list = [];
            $count = 0;
            if ($data) {
                if ($data['count'] > 0) {
                    $list = $data['list'];
                    foreach ($list as $item => &$v) {

                        $level1profit = bcmul($v['Lv1Tax'], 0.3, 3);
                        $level2profit = bcmul($v['Lv2Tax'], 0.09, 3);
                        $level3profit = bcmul($v['Lv3Tax'], 0.027, 3);
                        $v['ReceivedIncome'] = $level1profit + $level2profit + $level3profit;
                        $v['ReceivedIncome'] = FormatMoney($v['ReceivedIncome']);

                        //$v['TotalDeposit'] = FormatMoney($v['TotalDeposit']);
                        $v['TotalTax'] = FormatMoney($v['TotalTax']);
                        $v['TotalRunning'] = FormatMoney($v['TotalRunning']);


                        // $v['Lv1Deposit'] = FormatMoney($v['Lv1Deposit']);
                        $v['Lv1Tax'] = FormatMoney($v['Lv1Tax']);
                        $v['Lv1Running'] = FormatMoney($v['Lv1Running']);

                        // $v['Lv2Deposit'] = FormatMoney($v['Lv2Deposit']);
                        $v['Lv2Tax'] = FormatMoney($v['Lv2Tax']);
                        $v['Lv2Running'] = FormatMoney($v['Lv2Running']);

                        // $v['Lv3Deposit'] = FormatMoney($v['Lv3Deposit']);
                        $v['Lv3Tax'] = FormatMoney($v['Lv3Tax']);
                        $v['Lv3Running'] = FormatMoney($v['Lv3Running']);
                    }
                }
                unset($v);
                $count = $data['count'];
            }
            if (input('Action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $list, '', $count);
            }
            if (input('output') == 'exec') {
                if (empty($list)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $list;
                $result['count'] = $count;
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
                    //权限验证 
                    $auth_ids = $this->getAuthIds();
                    if (!in_array(10008, $auth_ids)) {
                        return $this->apiReturn(1, '', '没有权限');
                    }
                    $header_types = [
                        lang('代理ID') => 'string',
                        lang('个人总收益') => 'string',
                        lang('个人充值') => "string",
                        lang('个人税收') => 'string',
                        lang('一级人数') => 'string',
                        lang('一级充值') => 'string',
                        lang('一级税收') => 'string',
                        lang('二级人数') => 'string',
                        lang('二级充值') => 'string',
                        lang('二级税收') => 'string',
                        lang('三级人数') => 'string',
                        lang('三级充值') => 'string',
                        lang('三级税收') => 'string',
                    ];
                    $filename = lang('代理汇总') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['ProxyId'],
                            $row['ReceivedIncome'],
                            $row['TotalDeposit'],
                            $row['TotalTax'],
                            $row['Lv1PersonCount'],
                            $row['Lv1Deposit'],
                            $row['Lv1Tax'],

                            $row['Lv2PersonCount'],
                            $row['Lv2Deposit'],
                            $row['Lv2Tax'],

                            $row['Lv3PersonCount'],
                            $row['Lv3Deposit'],
                            $row['Lv3Tax'],
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
        $m = new UserProxyInfo();
        $data['team_total'] = $m->getCount(['ParentID' => 0, 'DirectCount' => [">", 0]]);
        $dailyM = new UserProxyDailyData();
        $total_walter = $dailyM->getSum([], 'SingleTax');
        if (!empty($total_walter))
            $total_walter = FormatMoney($total_walter);
        $data['total_walter'] = $total_walter;
        $total_reward = $dailyM->getSum([], 'RewardAmount');
        $data['total_reward'] = FormatMoneyint($total_reward);
        $this->assign('data', $data);
        $this->assign('parentid', $parentid);
        return $this->fetch();
    }

    //代理裂变
    public function teamreport()
    {
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $startdate = input('startdate', '');
        $enddate = input('enddate', '');
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetAgentRecord();
                $sumdata = $db->GetAgentRecordSum();
                $result['other'] = $sumdata;
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                $db = new  GameOCDB();
                $result = $db->GetAgentRecord();
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
                if ((int)input('exec', 0 == 1 && $outAll = true)) {
                    $header_types = [
                        lang('日期') => 'string',
                        lang('代理ID') => 'string',
                        lang('个人总收益') => 'string',
                        lang('个人充值') => "string",
                        lang('个人税收') => 'string',
                        lang('一级人数') => 'string',
                        lang('首充人数') => 'string',
                        lang('首充金额') => 'string',
                        lang('一级充值') => 'string',
                        lang('一级税收') => 'string',
                        lang('二级人数') => 'string',
                        lang('二级充值') => 'string',
                        lang('二级税收') => 'string',
                        lang('三级人数') => 'string',
                        lang('三级税收') => 'string',
                        lang('三级流水') => 'string',
                    ];
                    $filename = lang('代理明细') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['AddTime'],
                            $row['ProxyId'],
                            $row['RewardAmount'],
                            $row['DailyDeposit'],
                            $row['DailyTax'],
                            $row['Lv1PersonCount'],
                            $row['FirstDepositPerson'],
                            $row['FirstDepositMoney'],

                            $row['Lv1Deposit'],
                            $row['Lv1Tax'],

                            $row['Lv2PersonCount'],
                            $row['Lv2Deposit'],
                            $row['Lv2Tax'],

                            $row['Lv3PersonCount'],
                            $row['Lv3Deposit'],
                            $row['Lv3Tax']
                        ];

                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
                break;
        }

        $this->assign('parentid', $parentid);
        $this->assign('roleid', $roleid);
        $this->assign('startdate', $startdate);
        $this->assign('enddate', $enddate);
        if (empty($date)) {
            $date = date("Y-m-d", time());
        }
        return $this->fetch();
    }


    public function viewchilds()
    {
        $user_id = input('id');
        $depth = input('depth', '');
        if ($depth == '' || $depth < 3) {
            $depth = 3;
        }
        $this->assign('depth', $depth);
        $m = new Model();
        $fields = 'u.*,d.SingleWater TodayWater,RewardAmount TodayReward';
        $order = 'RoleID asc';
        $filter = 'u.RoleID = ' . $user_id;
        $data = $m->alias('u')->join('[dbo].[T_UserProxyDailyData] d', 'd.RoleID=u.RoleID and d.Date=\'' . date('Y-m-d') . '\'', 'left')->field($fields)->where($filter)->order($order)->find();

        if ($data) {
            $data['TodayWater'] = $data['TodayWater'] <= 0 ? 0 : $data['TodayWater'];
            $data['TodayReward'] = $data['TodayReward'] <= 0 ? 0 : $data['TodayReward'];
            $data['TodayWater'] = bcdiv($data['TodayWater'], 1000, 3);
            $data['TotalWater'] = bcdiv($data['TotalWater'], 1000, 3);
            $data['TotalProfit'] = bcdiv($data['TotalProfit'], 1000, 3);
            $data['children'] = $m->getMapData($user_id);
            if ($data['children']) {
                //空间换时间大法:客户要求可以选择显示10层下级用户
                foreach ($data['children'] as $k => $v) {
                    $data['children'][$k]['children'] = $m->getMapData($v['RoleID']);
                    if ($depth > 3 && !empty($data['children'][$k]['children'])) {
                        foreach ($data['children'][$k]['children'] as $k1 => $v1) {
                            $data['children'][$k]['children'][$k1]['children'] = $m->getMapData($v1['RoleID']);
                        }
                    }
                }
            }
        }
        $filter = 'u.RoleID = ' . $data['ParentID'];
        $parent_data = $m->alias('u')->join('[dbo].[T_UserProxyDailyData] d', 'd.RoleID=u.RoleID and d.Date=\'' . date('Y-m-d') . '\'', 'left')->field($fields)->where($filter)->order($order)->find();
        if ($parent_data) {
            $parent_data['TodayWater'] = $parent_data['TodayWater'] <= 0 ? 0 : $parent_data['TodayWater'];
            $parent_data['TodayReward'] = $parent_data['TodayReward'] <= 0 ? 0 : $parent_data['TodayReward'];
            $parent_data['TodayWater'] = bcdiv($parent_data['TodayWater'], 1000, 3);
            $parent_data['TotalWater'] = bcdiv($parent_data['TotalWater'], 1000, 3);
            $parent_data['TotalProfit'] = bcdiv($parent_data['TotalProfit'], 1000, 3);
            $parent_data['children'][0] = $data;
        } else {
            $parent_data = $data;
        }
        $this->assign('id', '');
        $this->assign('user_id', $user_id);
        $this->assign('data', json_encode($parent_data, 256));
        return $this->fetch();
    }

    //代理

    public function channelAgentList()
    {

        if ($this->request->isajax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('RoleID', 0);
            $where = [];
            if ($roleid > 0)
                $where['RoleID'] = $roleid;

            $userproxy = new ProxyAccount();
            $list = $userproxy->getList($where, $page, $limit, '*', '');
            foreach ($list as $k => &$v) {
                // $shareurl = config('site.agetcfg')['shareurl'];
                $sysconfig = new GameConfig();
                $shareurl = $sysconfig->getValue(['CfgType' => 113], 'keyValue');
                $loginurl = config('site.agetcfg')['loginurl'];
                $v['shareurl'] = str_replace('{inviteCode}', $v['InviteCode'], $shareurl);
                $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
                $time = sysTime();
                $token = md5($v['RoleID'] . $time . '210fd4cb4308ad8d2');
                $v['loginurl'] = $loginurl . '?roleid=' . $v['RoleID'] . '&time=' . sysTime() . '&token=' . $token;
            }
            unset($v);
            $count = $userproxy->getCount($where);
            return $this->apiReturn(0, $list, '', $count);
        }

        return $this->fetch();
    }


    public function addchanneluser()
    {
        if ($this->request->isAjax()) {
            $accountname = input('account', '');
            $nickname = input('nickname', '');
            $password = input('password', '');
            $descript = input('descript', '');

            if (empty($accountname) || empty($password)) {
                return $this->apiReturn(100, '', '账号密码不能为空');
            }
            $userid = $this->getAgentId();
            $userdb = new UserDB();
            $password = md5($password);
            $save_data = [
                'LoginID' => $userid,
                'AccountName' => $accountname,
                'PassWord' => $password,
                'NickName' => $nickname,
                'Descript' => $descript
            ];
            $result = $userdb->addChannelAgent($save_data);
            if (!empty($result[0][0])) {
                $result = $result[0][0];
                if ($result['retcode'] == 100) {
                    return $this->apiReturn(0, '', '代理添加成功');
                } else if ($result['retcode'] == 20)
                    return $this->apiReturn(100, '', '账号已经存在');
            }
            return $this->apiReturn(100, '', '代理添加失败');
        }
        return $this->fetch();
    }


    public function editchanneluser()
    {

        $roleid = input('roleid');
        $userporxy = new ProxyAccount();
        if ($this->request->isAjax()) {
            $accountname = input('account', '');
            $nickname = input('nickname', '');
            $password = input('password', '');
            $descript = input('descript', '');

            if (!$roleid) {
                return $this->apiReturn(100, '', '玩家不存在');
            }

            $savedata = [
                'NickName' => $nickname,
                'Descript' => $descript
            ];
            if (!empty($password)) {
                $savedata['PassWord'] = md5($password);
            }
            $ret = $userporxy->updateByWhere(['RoleID' => $roleid], $savedata);
            if ($ret)
                return $this->apiReturn(0, '', '修改成功');
            else
                return $this->apiReturn(100, '', '修改失败');
        }
        $info = $userporxy->getDataRow(['RoleID' => $roleid], '*');
        $this->assign('info', $info);
        return $this->fetch('channelpsw');
    }


    private function getAgentId()
    {
        $id = Redis::getInstance()->get('channel_UserId');
        $ret_id = '';
        $prename = '6';
        if (!$id) {
            $ret_id = $prename . sprintf('%05s', 1);
            Redis::getInstance()->set('channel_UserId', 1);
        } else {
            $m = $id + 1;
            $ret_id = $prename . sprintf('%05s', $m);
            Redis::getInstance()->set('channel_UserId', $m);
        }
        return $ret_id;
    }


    public function setChannelStatus()
    {
        if ($this->request->isAjax()) {
            $roleid = input('roleid', 0);
            $status = input('status', -1);
            if ($roleid == 0) {
                return $this->apiReturn(100, '', '参数错误');
            }
            if ($status == -1) {
                return $this->apiReturn(100, '', '参数错误');
            }

            $proxyinfo = new ProxyAccount();
            $ret = $proxyinfo->updateByWhere(['RoleID' => $roleid], ['Status' => $status]);
            return $this->apiReturn(0, '', '状态更新成功');

        }
    }


    public function config()
    {
        $cfgtype = input('cfgtype', 0);
        $keyword = input('keyword', '');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $where = 'cfgtype=' . $cfgtype;
            if (!empty($keyword)) {
                $where .= ' and CfgName like \'%' . $keyword . '%\'';
            }
            $sysconfig = new SysConfig();
            $list = $sysconfig->getList($where, $page, $limit, '*', 'Id');
            $count = $sysconfig->getCount($where);
            return $this->apiReturn(0, $list, '', $count);
        }
        return $this->fetch();
    }


    public function addagentcfg()
    {
        $id = input('id', 0);
        $sysconfig = new SysConfig();
        if ($this->request->isAjax()) {
            $cfgname = input('CfgName', '');
            $cfgkey = input('Cfgkey', '');
            $cfgvalue = input('CfgValue', '');
            $data = [
                'CfgName' => $cfgname,
                'Cfgkey' => $cfgkey,
                'CfgValue' => $cfgvalue
            ];

            if ($id > 0) {
                $sysconfig->updateById($id, $data);
            } else {
                $sysconfig->add($data);
            }
            return $this->apiReturn(0, 'success', '更新成功');

        }
        $info = $sysconfig->getRowById($id);
        if (empty($info)) {
            $info = ['Id' => 0, 'CfgName' => '', 'Cfgkey' => '', 'CfgValue' => ''];
        }
        $this->assign('info', $info);
        return $this->fetch();
    }

    public function delconfig()
    {
        $id = input('id', 0);
        $sysconfig = new SysConfig();

        if ($id > 0) {
            $sysconfig->delRow(['id' => $id]);
        }

        return $this->apiReturn(0, '', '删除成功');

    }


    //代理关系
    public function relation()
    {
        $action = $this->request->param('action');
        $m = new UserDB();
        if ($action == 'list') {
            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $ispay = $this->request->param('ispay');
            $reg_date1 = $this->request->param('register_date1');
            $register_ip = $this->request->param('register_ip');
            $reg_date2 = $this->request->param('register_date2');
            $login_date1 = $this->request->param('login_date1');
            $login_date2 = $this->request->param('login_date2');
            $register_ip = $this->request->param('register_ip');
            $limit = $this->request->param('limit') ?: 15;

            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $order = 'RoleID desc';
            if (!empty($orderby)) {
                if ($orderby == 'Totalyk') {
                    $orderby = "(TotalDeposit-TotalRollOut)";
                }
                $order = " $orderby $orderytpe";
            }

            $where = '1=1';
            if ($roleid != '') {
                $where .= ' and a.RoleID=' . $roleid;
            }
            if ($parentid != '') {
                $where .= ' and a.ParentID=' . $parentid;
            }
            if ($ispay == 1) {
                $where .= ' and d.TotalDeposit>0';
            }
            if ($ispay == 2) {
                $where .= ' and d.TotalDeposit=0';
            }
            if ($reg_date1 != '') {
                $where .= ' and c.RegisterTime>=\'' .$reg_date1.'\'';
            }
            if ($reg_date2 != '') {
                $where .= ' and c.RegisterTime<=\'' .$reg_date2.'\'';
            }
            if ($login_date1 != '') {
                $where .= ' and c.LastLoginTime>=\'' .$login_date1.'\'';
            }
            if ($login_date2 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date2.'\'';
            }
            if ($register_ip != '') {
                $where .= ' and c.RegIP=\'' .$register_ip.'\'';
            }
            $where .= ' and c.GmType<>0';
            $data = $m->getTableObject('T_UserProxyInfo')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
                ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs](NOLOCK) e', 'e.RoleID=a.RoleID and ChangeType=5 and IfFirstCharge=1', 'left')
                ->where($where)
                ->field('a.RoleID,a.ParentID,c.RegisterTime,c.RegIP,c.LastLoginTime,d.TotalDeposit,d.TotalRollOut,(b.Lv1Tax + b.Lv2Tax + b.Lv3Tax) TotalTax,(b.Lv1Running*'.config('agent_running_parent_rate')[1].'+b.Lv2Running*'.config('agent_running_parent_rate')[2].'+b.Lv3Running*'.config('agent_running_parent_rate')[3].') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit,e.AddTime as firtczTime,ISNULL(e.TransMoney,0) AS TransMoney')
                ->order($order)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                // if (app_name == 'runing') {
                //     $val['TotalProfit'] = $val['runningProfit'] ?: 0;
                // }
                // if (app_name == 'tax') {
                //     $val['TotalProfit'] = $val['taxProfit'] ?: 0;
                // }
                if ($val['ParentID'] == 0) {
                    $val['ParentID'] = '';
                    $val['parent_name'] = '';
                }
                $val['RegisterTime'] = date('Y-m-d H:i:s', strtotime($val['RegisterTime']));
                $val['firtczTime'] = $val['firtczTime'] ? date('Y-m-d H:i:s', strtotime($val['firtczTime'])) : '';
                $val['LastLoginTime'] = date('Y-m-d H:i:s', strtotime($val['LastLoginTime']));
                $val['TotalDeposit'] = bcdiv($val['TotalDeposit'] ?: 0, 1, 3) / 1;
                $val['TotalRollOut'] = bcdiv($val['TotalRollOut'] ?: 0, 1, 3) / 1;
                $val['Totalyk'] = bcsub($val['TotalDeposit'], $val['TotalRollOut'], 3) / 1;
                $val['TotalTax'] = bcdiv($val['TotalTax'] ?: 0, bl, 3) / 1;
                // $val['TotalProfit'] = bcdiv($val['TotalProfit'] ?: 0, bl, 3) / 1;
            }
            $other = $m->getTableObject('T_UserProxyInfo')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
                ->where($where)
                ->field('sum(d.TotalDeposit) TotalDeposit,sum(d.TotalRollOut) TotalRollOut,sum(b.Lv1Tax + b.Lv2Tax + b.Lv3Tax)TotalTax')
                ->find();
            $other['TotalDeposit'] = bcdiv($other['TotalDeposit'] ?: 0, 1, 3) / 1;
            $other['TotalRollOut'] = bcdiv($other['TotalRollOut'] ?: 0, 1, 3) / 1;
            $other['Totalyk'] = bcsub($other['TotalDeposit'], $other['TotalRollOut'], 3) / 1;
            $other['TotalTax'] = bcdiv($other['TotalTax'] ?: 0, bl, 3) / 1;
            return $this->apiReturn(0, $data['data'], 'success', $data['total'],$other);
        }
        if ($action == 'bind') {
            $auth_ids = $this->getAuthIds();
            if (!in_array(10005, $auth_ids)) {
                return $this->apiReturn(1, [], '没有权限');
            }
            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $password = $this->request->param('password');
            if (empty($roleid) || !isset($parentid) || empty($password)) {
                return $this->apiReturn(1, '', '参数有误');
            }
            $user_controller = new \app\admin\controller\User();
            $pwd = $user_controller->rsacheck($password);
            if (!$pwd) {
                return json(['code' => 1, 'msg' => '密码错误']);
            }
            $userModel = new UserModel();
            $userInfo = $userModel->getRow(['id' => session('userid')]);

            if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
                return json(['code' => 1, 'msg' => '密码有误，请重新输入']);
            }
            // $has_next = $m->getTableObject('T_UserProxyInfo')->where('ParentID',$roleid)->find();
            // if ($has_next) {
            //     return $this->apiReturn(1,'','受邀人已有下级，不可换绑');
            // }
            // $res = $m->getTableObject('T_UserProxyInfo')
            //                 ->where('RoleID',$roleid)
            //                 ->data(['ParentID'=>$parentid])
            //                 ->update();
            $db = new UserDB();
            $has_parents = $db->getTableObject('T_UserProxyInfo')->where('RoleID', $roleid)->value('ParentID');
            if (empty($has_parents)) {
                $data = $this->sendGameMessage('CMD_MD_SET_PROXY_INVITE', [$roleid, $parentid], "DC", 'returnComm');
            } else {
                return $this->apiReturn(1, '', '已经有上级代理');
                // $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid,$parentid], "DC", 'returnComm');
            }
            GameLog::logData(__METHOD__, [$roleid, $parentid,$data['iResult']], 1, '修改上级id');
            // $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid,$parentid], "DC", 'returnComm');
            if ($data['iResult']>10000000 ) {
                $comment = '设置上级ID：' . $parentid;
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleid,
                    'adminid' => session('userid'),
                    'type' => 3,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
//                $db->updateProxyOldData($roleid,$parentid);
                return $this->apiReturn(0, '', '操作成功');
            } else {
                $remark = '操作失败';
                switch ($data['iResult']) {
                    case '1':
                        $remark = '没有上级代理';
                        break;
                    case '2':
                        $remark = '已经有上级代理';
                        break;
                    case '3':
                        $remark = '上级代理不存在';
                        break;
                    case '4':
                        $remark = '代理链中有自己的ID';
                        break;
                    case '5':
                        $remark = '有下级不能绑定';
                        break;
                    case '6':
                        $remark = '同ip不能绑定';
                        break;
                }
                return $this->apiReturn(1, '', $remark);
            }
        }
        if ($action == 'unbind') {
            $auth_ids = $this->getAuthIds();
            if (!in_array(10005, $auth_ids)) {
                return $this->apiReturn(1, [], '没有权限');
            }
            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $password = $this->request->param('password');
            if (empty($roleid) || !isset($parentid) || empty($password)) {
                return $this->apiReturn(1, '', '参数有误');
            }
            $user_controller = new \app\admin\controller\User();
            $pwd = $user_controller->rsacheck($password);
            if (!$pwd) {
                return json(['code' => 1, 'msg' => '密码错误']);
            }
            $userModel = new userModel();
            $userInfo = $userModel->getRow(['id' => session('userid')]);
            if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
                return json(['code' => 1, 'msg' => '密码有误，请重新输入']);
            }
            $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid, 0], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                $comment = '解绑';
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleid,
                    'adminid' => session('userid'),
                    'type' => 3,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '没有上级代理');
            }
        }
        if ($action == 'output') {

            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $ispay = $this->request->param('ispay');
            $reg_date1 = $this->request->param('register_date1');
            $reg_date2 = $this->request->param('register_date2');
            $login_date1 = $this->request->param('login_date1');
            $login_date2 = $this->request->param('login_date2');

            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $order = 'RoleID desc';
            if (!empty($orderby)) {
                if ($orderby == 'Totalyk') {
                    $orderby = "(TotalDeposit-TotalRollOut)";
                }
                $order = " $orderby $orderytpe";
            }

            $where = '1=1';
            if ($roleid != '') {
                $where .= ' and a.RoleID=' . $roleid;
            }
            if ($parentid != '') {
                $where .= ' and a.ParentID=' . $parentid;
            }
            if ($ispay == 1) {
                $where .= ' and d.TotalDeposit>0';
            }
            if ($ispay == 2) {
                $where .= ' and d.TotalDeposit=0';
            }
            if ($reg_date1 != '') {
                $where .= ' and c.RegisterTime>=\'' .$reg_date1.'\'';
            }
            if ($reg_date2 != '') {
                $where .= ' and c.RegisterTime<=\'' .$reg_date1.'\'';
            }
            if ($login_date1 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date1.'\'';
            }
            if ($login_date2 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date2.'\'';
            }
            $field = "a.RoleID,a.ParentID,a.TotalWater TotalTax,a.TotalProfit,d.TotalDeposit,d.TotalRollOut";
            $data = $m->getTableObject('T_UserProxyInfo')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
                ->where($where)
                ->field('a.RoleID,a.ParentID,c.RegisterTime,c.LastLoginTime,d.TotalDeposit,d.TotalRollOut,(b.Lv1Tax + b.Lv2Tax + b.Lv3Tax) TotalTax,(b.Lv1Running*'.config('agent_running_parent_rate')[1].'+b.Lv2Running*'.config('agent_running_parent_rate')[2].'+b.Lv3Running*'.config('agent_running_parent_rate')[3].') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit')
                ->order($order)
                ->select();
            $result = [];
            $result['list'] = $data;
            $result['count'] = count($data);
            $outAll = input('outall', false);
            if ((int)input('exec', 0) == 0) {
                //权限验证
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
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
                    lang('邀请人ID') => 'string',
                    // lang('邀请人昵称') => 'string',
                    lang('受邀人ID') => "string",
                    // lang('受邀人昵称') => 'string',
                    lang('总充值') => "string",
                    lang('总提现') => "string",
                    lang('总盈亏') => "string",
                    lang('总税收') => "string",
                    lang('注册时间') => "string",
                    lang('最后登陆时间') => "string",
                ];
                $filename = lang('代理关系') . '-' . date('YmdHis');
                $rows =& $result['list'];
                $writer = $this->GetExcel($filename, $header_types, $rows, true);
                foreach ($rows as $index => &$val) {
                    if ($val['ParentID'] == 0) {
                        $val['ParentID'] = '';
                        $val['parent_name'] = '';
                    }
                    $val['RegisterTime'] = date('Y-m-d H:i:s', strtotime($val['RegisterTime']));
                    $val['LastLoginTime'] = date('Y-m-d H:i:s', strtotime($val['LastLoginTime']));
                    $val['TotalDeposit'] = bcdiv($val['TotalDeposit'] ?: 0, 1, 3) / 1;
                    $val['TotalRollOut'] = bcdiv($val['TotalRollOut'] ?: 0, 1, 3) / 1;
                    $val['Totalyk'] = bcsub($val['TotalDeposit'], $val['TotalRollOut'], 3) / 1;
                    $val['TotalTax'] = bcdiv($val['TotalTax'] ?: 0, bl, 3) / 1;

                    $item = [
                        // $row['ParentID'],$row['ParentName'],$row['ParentID'],$row['RoleID'],$row['RoleName'],$row['TotalDeposit'],$row['TotalRollOut'],$row['TotalProfit'],$row['TotalTax'],$row['TotalProfit']
                        $val['ParentID'], $val['RoleID'], $val['TotalDeposit'], $val['TotalRollOut'], $val['Totalyk'],$val['TotalTax'], $val['RegisterTime'], $val['LastLoginTime']
                    ];
                    $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                    unset($val[$index]);
                }
                unset($val, $item);
                $writer->writeToStdOut();
                exit();
            }
        }
        return $this->fetch();
    }

    //领取
    public function receive()
    {
        $activity_arr = [
            '5' => lang('邀请奖励'),
            '4' => lang('邀请成就'),
            '3' => lang('投注返利'),
            '2' => lang('佣金转账'),
            '1' => lang('佣金提现'),
            '6' => lang('佣金提现失败返还'),
            '7' => lang('GM发奖励'),
            '10001' => lang('投注返利'),
        ];
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $page = $this->request->param('page') ?: 1;
            $roleid = $this->request->param('roleid');
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $hd_name = $this->request->param('hd_name');

            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $order = 'AddTime desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }


            $db = new DataChangelogsDB();
            $where = '1=1';
            if ($roleid != '') {
                $where .= ' and RoleID=' . $roleid;
            }
            if ($start_date != '') {
                $where .= ' and AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and AddTime<\'' . $end_date . '\'';
            }
            if ($hd_name != '') {
                $where .= ' and BonusType in(' . $hd_name . ')';
            }

            $data = $db->getTableList('T_ProxyBonusLog', $where, $page, $limit, '*', $order);
            foreach ($data['list'] as $key => &$val) {
                $val['hd_name'] = $activity_arr[$val['BonusType']] ?? $val['BonusType'];
                $val['BonusAmount'] = FormatMoney($val['BonusAmount']);
                $val['LastProxyBonus'] = FormatMoney($val['LastProxyBonus']);
            }

            if (input('action') == 'list' && input('output') != 'exec') {
//                $fields='count(distinct(RoleID)) as total_count,sum(BonusAmount) as BonusAmount,sum(LastProxyBonus) as LastProxyBonus';
                $fields = 'CAST(count(distinct(RoleID)) AS BIGINT) as total_count, CAST(sum(BonusAmount) AS BIGINT) as BonusAmount, CAST(sum(LastProxyBonus) AS BIGINT) as LastProxyBonus';
                $sumdata = $db->getTableRow('T_ProxyBonusLog', $where, $fields);
                $data['other'] = [];
                $data['other']['total_count'] = $sumdata['total_count'] ?? 0;
                $data['other']['BonusAmount'] = FormatMoney($sumdata['BonusAmount'] ?? 0);
                $data['other']['LastProxyBonus'] = FormatMoney($sumdata['LastProxyBonus'] ?? 0);
                return $this->apiJson($data);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                if (empty($data['list'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['list'];
                $result['count'] = $data['count'];
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
                        lang('时间') => "string",
                        lang('代理ID') => 'string',
                        lang('佣金') => "string",
                        lang('佣金类型') => "string",
                        lang('佣金余额') => "string",
                    ];
                    $filename = lang('代理领取记录') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['AddTime'], $row['RoleId'], $row['BonusAmount'], $row['hd_name'], $row['LastProxyBonus']
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        } else {
            $this->assign('activity_arr', $activity_arr);
            return $this->fetch();
        }
    }

    public function levelRebatConfig()
    {
        $action = $this->request->param('action');
        $m = new GameOCDB;
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $data = $m->getTableObject('T_AgentTaxConfig')
                ->field('*')
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        if ($action == 'edit') {
            $Id = $this->request->param('Id');
            // $ConfigName = $this->request->param('ConfigName');
            // $UserLevel = $this->request->param('UserLevel');
            // $RebateType = $this->request->param('RebateType');
            $Rate = $this->request->param('Rate');
            $res = $m->getTableObject('T_AgentTaxConfig')->where('Id', $Id)
                ->data([
                    // 'ConfigName'=>$ConfigName,
                    // 'UserLevel'=>$UserLevel,
                    // 'RebateType'=>$RebateType,
                    'Rate' => $Rate,
                    'update_date' => date('Y-m-d H:i:s'),
                    'opt_user' => session('username')
                ])
                ->update();
            if ($res) {
                return $this->apiReturn(1, '', '操作成功');
            } else {
                return $this->apiReturn(2, '', '操作失败');
            }
        }
        if ($action == 'view') {
            $id = $this->request->param('Id');
            $data = $m->getTableObject('T_AgentTaxConfig')
                ->where('Id', $id)
                ->find();
            $this->assign('data', $data);
            return $this->fetch();
        }

    }

    //代理额外奖励
    public function extraConfig()
    {
        $action = $this->request->param('action');
        $m = new MasterDB;
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $data = $m->getTableObject('T_ProxyWeeklyBonusCfg')
                ->field('*')
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
    }

    //代理推广明细查询
    public function promotionDetails()
    {
        $action = $this->request->param('action');
        $m = new UserDB();
        if ($action == 'list') {
//            $roleid = $this->request->param('roleid') ?: 0;
//            $parentid = $this->request->param('parentid') ?: 0;
//            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
//            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
//            $limit = $this->request->param('limit') ?: 15;
//            $parentid = $this->request->param('parentid');

            // if ($roleid == '' && $parentid == '' ) {
            //    return $this->apiReturn(0, [], 'success', 0);
            // }

//            $where = '1=1';
//            if ($roleid != '') {
//                $where .= ' and b.RoleID=' . $roleid;
//            }
//            if ($parentid != '') {
//                $where .= ' and b.ParentID=' . $parentid;
//            }
//            if ($start_date != '') {
//                $where .= 'and a.Date>=\'' . $start_date . '\'';
//            }
//            if ($end_date != '') {
//                $where .= 'and a.Date<=\'' . $end_date . '\'';
//            }

            $db = new GameOCDB();
            $data = $db->GetAgentRecord(true);
//            $field = "a.Date,a.RoleID,a.SingleTax";
//            $data = $m->getTableObject('T_UserProxyDailyData')->alias('a')
//                ->join('[CD_UserDB].[dbo].[T_UserProxyInfo] b', 'a.RoleID=b.RoleID', 'left')
//                ->where($where)
//                ->field($field)
//                ->order("Date desc")
//                ->paginate($limit)
//                ->toArray();

            if ($data['count'] > 0) {
                foreach ($data['list'] as $key => &$val) {
                    $lv1rate = bcdiv(7, 1000, 3);
                    $val['DailyTax'] = bcmul($val['DailyRunning'], $lv1rate, 3);
                }
            }

            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['list'], 'success', $data['count']);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                if (empty($data['list'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['list'];
                $result['count'] = $data['count'];
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
                        lang('时间') => 'string',
                        lang('直属玩家') => 'string',
                        lang('总贡献奖励') => "string",

                    ];
                    $filename = lang('代理推广明细') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['AddTime'],
                            $row['ProxyId'],
                            $row['DailyTax'],
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

    //代理奖励统计
    public function agentRewardStatistical()
    {
        $activity_arr = [
            '65' => lang('代理税收返利'),
            '66' => lang('代理邀请奖励'),
            '69' => lang('代理周额外奖励'),
        ];
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $db = new GameOCDB();
            $where = '1=1';
            if (config('is_portrait') == 1) {
                if ($start_date != '') {
                    $where .= ' and AddTime>=\'' . $start_date . '\'';
                }
                if ($end_date != '') {
                    $where .= ' and AddTime<\'' . $end_date . '\'';
                }
                if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                    $where .= ' and OperatorId=' . session('merchant_OperatorId');
                }

                $subQuery = "(SELECT AddTime,sum(RunningBonus) RunningBonus,sum(InviteBonus) InviteBonus,sum(FirstChargeBonus) FirstChargeBonus FROM [OM_GameOC].[dbo].[T_ProxyDailyBonus](NOLOCK) WHERE " . $where . " GROUP BY [AddTime])";
                $data = $db->getTableObject($subQuery)->alias('a')
                    ->order('AddTime desc')
                    // ->fetchSql(true)
                    // ->select();
                    ->paginate($limit)
                    ->toArray();

                foreach ($data['data'] as $key => &$val) {
                    $val['RunningBonus'] = FormatMoney($val['RunningBonus'] ?: 0);
                    $val['InviteBonus'] = FormatMoney($val['InviteBonus'] ?: 0);
                    $val['FirstChargeBonus'] = FormatMoney($val['FirstChargeBonus'] ?: 0);
                    $val['total'] = round(($val['RunningBonus'] + $val['InviteBonus'] + $val['FirstChargeBonus']), 2);
                }
            } else {

                if ($start_date != '') {
                    $where .= ' and adddate>=\'' . $start_date . '\'';
                }
                if ($end_date != '') {
                    $where .= ' and adddate<\'' . $end_date . '\'';
                }
                $where .= ' and ChangeType in(65,66,69)';
                $subQuery = "(SELECT [adddate] FROM [OM_GameOC].[dbo].[View_ActivityReceiveSum] WHERE " . $where . " GROUP BY [adddate])";
                $data = $db->getTableObject($subQuery)->alias('a')
                    ->join('[OM_GameOC].[dbo].[View_ActivityReceiveSum] b', 'b.adddate=a.adddate and b.ChangeType=65', 'left')
                    ->join('[OM_GameOC].[dbo].[View_ActivityReceiveSum] c', 'c.adddate=a.adddate and c.ChangeType=66', 'left')
                    ->join('[OM_GameOC].[dbo].[View_ActivityReceiveSum] d', 'd.adddate=a.adddate and d.ChangeType=69', 'left')
                    ->field('a.adddate,b.ReceiveAmt amt65,c.ReceiveAmt amt66,d.ReceiveAmt amt69')
                    ->order('adddate desc')
                    // ->fetchSql(true)
                    // ->select();
                    ->paginate($limit)
                    ->toArray();
                foreach ($data['data'] as $key => &$val) {
                    $val['amt65'] = FormatMoney($val['amt65'] ?: 0);
                    $val['amt66'] = FormatMoney($val['amt66'] ?: 0);
                    $val['amt69'] = FormatMoney($val['amt69'] ?: 0);
                    $val['total'] = round(($val['amt65'] + $val['amt66'] + $val['amt69']), 2);
                }
            }


            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }
            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
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
                    if (config('is_portrait') == 1) {
                        $header_types = [
                            lang('时间') => 'string',
                            lang('代理税收返利') => 'string',
                            lang('代理邀请奖励') => "string",
                            lang('代理周额外奖励') => "string",
                            lang('统计') => "string",
                        ];
                    } else {
                        $header_types = [
                            lang('时间') => 'string',
                            lang('代理税收返利') => 'string',
                            lang('代理邀请奖励') => "string",
                            lang('代理周额外奖励') => "string",
                            lang('统计') => "string",
                        ];
                    }

                    $filename = lang('代理分享统计') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        if (config('is_portrait') == 1) {
                            $item = [
                                $row['AddTime'],
                                $row['RunningBonus'],
                                $row['InviteBonus'],
                                $row['FirstChargeBonus'],
                                $row['total'],
                            ];
                        } else {
                            $item = [
                                $row['adddate'],
                                $row['amt65'],
                                $row['amt66'],
                                $row['amt69'],
                                $row['total'],
                            ];
                        }

                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        } else {
            if (config('is_portrait') == 1) {
                return $this->fetch('agent_reward_statistical_s');
            } else {
                return $this->fetch();
            }

        }
    }


    //代理查询
    public function agentQuery()
    {
        $action = $this->request->param('action');
        if ($action == 'list' || $action == 'output') {
            $roleid = $this->request->param('roleid');
            $invite_code = $this->request->param('invite_code');
            $limit = $this->request->param('limit') ?: 15;
            $where = '1=1';
            if ($invite_code != '') {
                $where .= ' and a.InviteCode=\'' . $invite_code . '\'';
            }
            if ($roleid != '') {
                $where .= ' and a.RoleID=' . $roleid;
            }
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $order = 'RoleID asc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            // $where .= ' and a.update_time>=\''.date('Y-m-d').'\'';
            $field = "";
            $ymd = date('Ymd', strtotime('-1 days'));
            $m = new UserDB();
            if ($action == 'list') {
                $data = $m->getTableObject('[CD_UserDB].[dbo].[T_UserProxyInfo](NOLOCK)')->alias('a')
                    ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                    ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                    ->join('[OM_GameOC].[dbo].[T_ProxyDailyCollectData_' . $ymd . '](NOLOCK) d', 'd.ProxyId=a.RoleID', 'left')
                    ->where($where)
                    ->field('a.RoleID,a.InviteCode,c.RegisterTime,c.Mobile,b.Lv1PersonCount,b.Lv2PersonCount,b.Lv3PersonCount,a.ReceiveProfit,(b.Lv1Running*' . config('agent_running_parent_rate')[1] . '+b.Lv2Running*' . config('agent_running_parent_rate')[2] . '+b.Lv3Running*' . config('agent_running_parent_rate')[3] . ') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit,(d.Lv1Running*' . config('agent_running_parent_rate')[1] . '+d.Lv2Running*' . config('agent_running_parent_rate')[2] . '+d.Lv3Running*' . config('agent_running_parent_rate')[3] . ') as yesRunningProfit,(d.Lv1Tax*0.3+d.Lv2Tax*0.09+d.Lv3Tax*0.027) as yesTaxProfit,b.ReceivedIncome')
                    ->order($order)
                    ->paginate($limit)
                    ->toArray();
                if (empty($data)) {
                    return $this->apiReturn(0, [], 'success', 0);
                }
            }
            if ($action == 'output') {
                $data = [];
                $data['data'] = $m->getTableObject('[CD_UserDB].[dbo].[T_UserProxyInfo](NOLOCK)')->alias('a')
                    ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                    ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                    ->join('[OM_GameOC].[dbo].[T_ProxyDailyCollectData_' . $ymd . '](NOLOCK) d', 'd.ProxyId=a.RoleID', 'left')
                    ->where($where)
                    ->field('a.RoleID,a.InviteCode,c.RegisterTime,c.Mobile,b.Lv1PersonCount,b.Lv2PersonCount,b.Lv3PersonCount,a.ReceiveProfit,(b.Lv1Running*' . config('agent_running_parent_rate')[1] . '+b.Lv2Running*' . config('agent_running_parent_rate')[2] . '+b.Lv3Running*' . config('agent_running_parent_rate')[3] . ') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit,(d.Lv1Running*' . config('agent_running_parent_rate')[1] . '+d.Lv2Running*' . config('agent_running_parent_rate')[2] . '+d.Lv3Running*' . config('agent_running_parent_rate')[3] . ') as yesRunningProfit,(d.Lv1Tax*0.3+d.Lv2Tax*0.09+d.Lv3Tax*0.027) as yesTaxProfit,b.ReceivedIncome')
                    ->order($order)
                    ->select();
            }
            if (!empty($data['data'])) {
                foreach ($data['data'] as $key => &$val) {
                    if (app_name == 'runing') {
                        $val['TotalIncome'] = $val['runningProfit'] ?: 0;
                        $val['yesIncome'] = $val['yesRunningProfit'] ?: 0;
                    }
                    if (app_name == 'tax') {
                        $val['TotalIncome'] = $val['taxProfit'] ?: 0;
                        $val['yesIncome'] = $val['yesTaxProfit'] ?: 0;
                    }
                    $val['Lv1PersonCount'] = $val['Lv1PersonCount'] ?: 0;
                    $val['Lv2PersonCount'] = $val['Lv2PersonCount'] ?: 0;
                    $val['Lv3PersonCount'] = $val['Lv3PersonCount'] ?: 0;

                    $val['ReceivedIncome'] = FormatMoney($val['ReceivedIncome']); //已领取收益
                    $val['yesIncome'] = FormatMoney($val['yesIncome']);
                    $val['TotalIncome'] = FormatMoney($val['TotalIncome']);
                    $val['unIncome'] = bcsub($val['TotalIncome'], $val['ReceivedIncome'], 2);

                    $val['RegisterTime'] = substr($val['RegisterTime'], 0, 19);
                    $val['Mobile'] = '******';
                }
            }

            if ($action == 'list') {
                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }
            if ($action == 'output') {
                if (empty($data['data'])) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                }
                $result = [];
                $result['list'] = $data['data'];
                $result['count'] = 1;
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    //权限验证 
                    $auth_ids = $this->getAuthIds();
                    if (!in_array(10008, $auth_ids)) {
                        return $this->apiReturn(1, '', '没有权限');
                    }
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
                        lang('注册时间') => 'string',
                        lang('代理ID') => 'string',
                        lang('邀请码') => 'string',
                        lang('电话号码') => "string",
                        lang('一级代理') => 'string',
                        lang('二级代理') => "string",
                        lang('三级代理') => "string",
                        lang('昨日佣金') => "string",
                        lang('已领取佣金') => "string",
                        lang('未领取佣金') => "string",
                        lang('历史佣金') => "string",
                    ];
                    $filename = lang('代理查询') . '-' . date('YmdHis');
                    $rows =& $result['list'];

                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['RegisterTime'],
                            $row['RoleID'],
                            $row['InviteCode'],
                            $row['Mobile'],
                            $row['Lv1PersonCount'],
                            $row['Lv2PersonCount'],
                            $row['Lv3PersonCount'],
                            $row['yesIncome'],
                            $row['ReceivedIncome'],
                            $row['unIncome'],
                            $row['taxProfit']
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

    public function agentShare()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            // $roleid   = $this->request->param('roleid');
            $limit = $this->request->param('limit') ?: 15;
            $m = new GameOCDB();
            $data = $m->getTableObject('T_ProxyDailyShareCount')
                ->order('AddDate desc')
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                if ($val['RegisterNum'] <= 0) {
                    $GameNum_rate = 0;
                    $RechargeNum_rate = 0;
                } else {
                    $GameNum_rate = round($val['GameNum'] / $val['RegisterNum'], 2);
                    $RechargeNum_rate = round($val['RechargeNum'] / $val['RegisterNum'], 2);
                }

                $val['GameNum'] = $val['GameNum'] . '(' . $GameNum_rate * 100 . '%)';
                $val['RechargeNum'] = $val['RechargeNum'] . '(' . $RechargeNum_rate * 100 . '%)';
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }

            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
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
                        lang('日期') => 'string',
                        lang('注册人数') => 'string',
                        lang('游戏人数(占比百分比)') => "string",
                        lang('充值人数(占比百分比)') => "string",
                    ];
                    $filename = lang('代理分享统计') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['AddDate'],
                            $row['RegisterNum'],
                            $row['GameNum'],
                            $row['RechargeNum'],
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

    public function bind_log()
    {
        return $this->fetch();
    }


    public function agentDayStatistical()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $uid = $this->request->param('roleid');
            $tab = $this->request->param('tab') ?: 'total';
            if (empty($uid)) {
                return $this->apiReturn(0, [], 'success', 1);
            }
            switch ($tab) {
                case 'total':
                    $data = $this->getTotalData($uid);
                    break;
                case 'today':
                    $now_date = date('Y-m-d H:i:s');
                    $data = $this->getIndexData($uid, date('Y-m-d') . ' 00:00:00', $now_date);
                    break;
                case 'yestoday':
                    $data = $this->getIndexData($uid, date('Y-m-d', strtotime('-1 days')) . ' 00:00:00', date('Y-m-d', strtotime('-1 days')) . ' 23:59:59');
                    break;
                case 'month':
                    $now_date = date('Y-m-d H:i:s');
                    $data = $this->getIndexData($uid, date('Y-m') . '-01 00:00:00', $now_date);
                    break;
                case 'lastmonth':
                    $start_date = date('Y-m-01 00:00:00', strtotime('-1 month'));
                    $data = $this->getIndexData($uid, $start_date, date('Y-m') . '-01 00:00:00');
                    break;
                case 'week':
                    $w = date('w');
                    if ($w == 0) {
                        $w = 7;
                    }
                    $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                    $now_date = date('Y-m-d H:i:s');
                    $data = $this->getIndexData($uid, date('Y-m-d', $w) . ' 00:00:00', $now_date);
                    break;
                case 'lastweek':
                    $w = date('w');
                    if ($w == 0) {
                        $w = 7;
                    }
                    $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                    $start_date = date('Y-m-d  00:00:00', $w - 7 * 86400);
                    $data = $this->getIndexData($uid, $start_date, date('Y-m-d', $w) . ' 00:00:00');
                    break;
                default:
                    $data = $this->getTotalData($uid);
                    break;
            }
            $result[] = $data;
            return $this->apiReturn(0, $result, 'success', 1);
        }

        return $this->fetch();
    }

    private function getIndexData($uid, $date, $end_date)
    {
        $table = 'dbo.T_ProxyDailyCollectData';

        $where = "";
        $where .= " and ProxyId=" . $uid;

        $begin = substr($date, 0, 10);
        $end = substr($end_date, 0, 10);
        $field = 'ISNULL(sum(Lv1PersonCount),0) as Lv1PersonCount,ISNULL(sum(Lv1Deposit),0) as Lv1Deposit,ISNULL(sum(Lv1Running),0) as Lv1Running,ISNULL(sum(Lv1RechargeCount),0) as Lv1RechargeCount,ISNULL(sum(Lv2PersonCount),0) as Lv2PersonCount,ISNULL(sum(Lv2Deposit),0) as Lv2Deposit,ISNULL(sum(Lv2Running),0) as Lv2Running,ISNULL(sum(Lv2RechargeCount),0) as Lv2RechargeCount,ISNULL(sum(Lv3PersonCount),0) as Lv3PersonCount,ISNULL(sum(Lv3Deposit),0) as Lv3Deposit,ISNULL(sum(Lv3Running),0) as Lv3Running,ISNULL(sum(Lv3RechargeCount),0) as Lv3RechargeCount';
        if (config('is_show_under4') == 1) {
            $field .= ',ISNULL(sum(UnderLv4PersonCount),0) AS UnderLv4PersonCount,ISNULL(sum(UnderLv4Deposit),0) AS UnderLv4Deposit,ISNULL(sum(UnderLv4Running),0) AS UnderLv4Running,ISNULL(sum(Lv4RechargeCount),0) as Lv4RechargeCount';
        }

        $tablefield = 'sum(Lv1PersonCount) as Lv1PersonCount,sum(Lv1Deposit) as Lv1Deposit,sum(Lv1Running) as Lv1Running,sum(Lv1FirstDepositPlayers) as Lv1RechargeCount,sum(Lv2PersonCount) as Lv2PersonCount,sum(Lv2Deposit) as Lv2Deposit,sum(Lv2Running) as Lv2Running,sum(Lv2FirstDepositPlayers) as Lv2RechargeCount,sum(Lv3PersonCount) as Lv3PersonCount,sum(Lv3Deposit) as Lv3Deposit,sum(Lv3Running) as Lv3Running,sum(Lv3FirstDepositPlayers) as Lv3RechargeCount';

        if (config('is_show_under4') == 1) {
            $tablefield .= ',sum(UnderLv4PersonCount) as UnderLv4PersonCount,sum(UnderLv4Deposit) as UnderLv4Deposit,sum(UnderLv4Running) as UnderLv4Running,sum(UnderLv4DepositPlayers) as Lv4RechargeCount';
        }

        $sqlExec = "exec Proc_GetGameLogTotal '$table','$field','$tablefield','','$where','$begin','$end'";

        $res = (new \app\model\GameOCDB())->getTableQuery($sqlExec);

        $result = $res[0][0];

        $result['Lv1Running'] = FormatMoney($result['Lv1Running'] ?? 0) / 1;

        $result['Lv2Running'] = FormatMoney($result['Lv2Running'] ?? 0) / 1;

        $result['Lv3Running'] = FormatMoney($result['Lv3Running'] ?? 0) / 1;

        if (config('is_show_under4') == 1) {
            $result['UnderLv4Running'] = FormatMoney($result['UnderLv4Running'] ?? 0) / 1;
        }
        return $result;
    }


    private function getTotalData($uid)
    {
        $field = 'ISNULL(ReceivedIncome,0) As ReceivedIncome,ISNULL(TotalDeposit,0) AS TotalDeposit,ISNULL(TotalTax,0) AS TotalTax,ISNULL(TotalRunning,0) AS TotalRunning,ISNULL(Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(Lv1Deposit,0) AS Lv1Deposit,ISNULL(Lv1Tax,0) AS Lv1Tax,ISNULL(Lv1Running,0) AS Lv1Running,ISNULL(Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(Lv2Deposit,0) AS Lv2Deposit,ISNULL(Lv2Tax,0) AS Lv2Tax,ISNULL(Lv2Running,0) AS Lv2Running,ISNULL(Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(Lv3Deposit,0) AS Lv3Deposit,ISNULL(Lv3Tax,0) AS Lv3Tax,ISNULL(Lv3Running,0) AS Lv3Running,Lv1DepositPlayers as Lv1RechargeCount,Lv2DepositPlayers as Lv2RechargeCount,Lv3DepositPlayers as Lv3RechargeCount';
        if (config('is_show_under4') == 1) {
            $field .= ',ISNULL(UnderLv4PersonCount,0) AS UnderLv4PersonCount,ISNULL(UnderLv4Deposit,0) AS UnderLv4Deposit,ISNULL(UnderLv4Running,0) AS UnderLv4Running,UnderLv4DepositPlayers as Lv4RechargeCount';
        }

        $proxy = (new \app\model\UserDB())->getTableObject('T_ProxyCollectData(nolock)')->where('ProxyId', $uid)->field($field)->find();

        $proxy['TotalTax'] = FormatMoney($proxy['TotalTax']);
        // $proxy['TotalDeposit'] = FormatMoney($proxy['TotalDeposit'])/1;
        $proxy['TotalRunning'] = FormatMoney($proxy['TotalRunning']) / 1;

        $proxy['Lv1Tax'] = FormatMoney($proxy['Lv1Tax']);
        $proxy['Lv1Running'] = FormatMoney($proxy['Lv1Running']) / 1;

        $proxy['Lv2Tax'] = FormatMoney($proxy['Lv2Tax']);
        $proxy['Lv2Running'] = FormatMoney($proxy['Lv2Running']) / 1;

        $proxy['Lv3Tax'] = FormatMoney($proxy['Lv3Tax']);
        $proxy['Lv3Running'] = FormatMoney($proxy['Lv3Running']) / 1;

        if (config('is_show_under4') == 1) {
            $proxy['UnderLv4Running'] = FormatMoney($proxy['UnderLv4Running']) / 1;
        }
        $Lv1Reward = bcmul($proxy['Lv1Running'], config('agent_running_parent_rate')[1], 4);
        $Lv2Reward = bcmul($proxy['Lv2Running'], config('agent_running_parent_rate')[2], 4);
        $Lv3Reward = bcmul($proxy['Lv3Running'], config('agent_running_parent_rate')[3], 4);
        $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
        $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 4);
        $proxy['ReceivedIncome'] = round($rewar_amount, 2) / 1;
        $proxy['TotalDeposit'] = round($proxy['TotalDeposit'], 2) / 1;
        $proxy['TotalRunning'] = round($proxy['TotalRunning'], 2) / 1;
        return $proxy;
    }


    ///代理线批量封号
    public function lockParent()
    {
        $parentId = input('parentid');
        $password = input('password');
        $user_controller = new \app\admin\controller\User();
        $pwd = $user_controller->rsacheck($password);
        if (!$pwd) {
            return $this->apiReturn(1, '', '密码错误');
        }
        $userModel = new userModel();
        $userInfo = $userModel->getRow(['id' => session('userid')]);
        if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
            return $this->apiReturn(1, '', '密码有误，请重新输入');
        }
        $userdb = new UserDB();
        $accountdb = new AccountDB();
        $result = $userdb->getTableObject('T_UserProxyInfo')->field('RoleId')->where('ParentIds like \'%' . $parentId . '%\'')->select();
        if (empty($result)) {
            return $this->apiReturn(1, '', '下级没有任务玩家，无法操作');
        }
        $array_roleid = [];
        foreach ($result as $k => $v) {
            array_push($array_roleid, $v['RoleId']);
        }
        $str_roleid = implode(',', $array_roleid);
        $where = " AccountID in($str_roleid)";
        $accountdb->updateTable('T_Accounts', ['Locked' => 1, 'LockedDays' => 300, 'LockStatTime' => date('Y-m-d H:i:s')], $where);
        return $this->apiReturn(0, [], 'success', 1);
    }


    //设置代理佣金额外提现额度
    public function setExtraEd()
    {
        $roleid = $this->request->param('roleid');
        $amount = $this->request->param('amount');
        $amount = floatval($amount);
        $amount = $amount * bl;
        $data = $this->sendGameMessage('CMD_MD_SET_PROXY_WITHDRAW_EXTRA_AMOUNT', [$roleid, $amount], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    /**
     * 代理汇总列表批量备注
     * @return mixed
     */
    public function addRemark()
    {
        $roleid = input('roleid', 0, 'intval');
        $type = input('type', 1, 'intval');
        $comment = input('comment');
        if (empty($comment)) {
            return $this->apiReturn(1, '', '备注不能为空');
        }
        $db = new GameOCDB();

        $filter = 'ProxyId>0 ';
        $filter .= ' and ParentID =' . $roleid;
        $order = 'ProxyId desc';
        $field = 'A.ParentID,A.RoleId as ProxyId';
        $proxyinfo = new UserProxyInfo();

        $table = '(select ' . $field . '  FROM   CD_UserDB.dbo.T_UserProxyInfo(nolock) as A  left join [CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as B on A.RoleID=B.ProxyId) as t ';

        $data = $proxyinfo->getProcPageList($table, '*', $filter, $order, 1, 999);
        try {
            Db::startTrans();
            if ($data && $data['count'] > 0) {
                foreach ($data['list'] as $item => $v) {
                    $db->setTable('T_PlayerComment')->Insert([
                        'roleid' => $v['ProxyId'],
                        'adminid' => session('userid'),
                        'type' => 1,
                        'opt_time' => date('Y-m-d H:i:s'),
                        'comment' => $comment
                    ]);
                }
            }
            $res = $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 1,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);
            Db::commit();
            if ($res) {
                GameLog::logData(__METHOD__, [$roleid, $type, $comment], 1, '操作成功');
                return $this->apiReturn(0, '', '操作成功');
            } else {
                GameLog::logData(__METHOD__, [$roleid, $type, $comment], 1, '操作失败');
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (Exception $ex) {
            Db::rollback();
            GameLog::logData(__METHOD__, [$roleid, $type, $comment], 1, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }

    }

    public function temReward()
    {
        $roleid = $this->request->param('roleid');
        $amount = $this->request->param('reward');
        $amount = floatval($amount);
        $amount = $amount * bl;
        try {

            $res = $this->sendGameMessage('CMD_MD_GM_ADD_PROXY_COMMISSION', [$roleid, $amount, 10001]);
            $res = unpack('LiResult/', $res);
        } catch (Exception $exception) {
            return $this->error('连接服务器失败,请稍后重试!');
        }
        if ($res['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }






    }


}