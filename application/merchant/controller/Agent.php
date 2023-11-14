<?php

namespace app\merchant\controller;

use app\model\BankDB;
use app\model\GameConfig;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\ProxyAccount;
use app\model\SysConfig;
use app\model\UserDB;
use app\model\UserProxyDailyData;
use app\model\UserProxyInfo;
use app\model\UserTeamLog;
use think\Exception;
use think\exception\PDOException;
use think\response\Json;
use think\View;
use redis\Redis;
use app\model\ActivityRecord;
use app\model\User as userModel;

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
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $filter .= ' and OperatorId='.session('merchant_OperatorId');
            }

            $order = 'ProxyId desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            $field = 'A.ParentID,A.OperatorId,A.RoleId as ProxyId,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running,A.MobileBackgroundSwitch';
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
                        $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward,2);
                        $v['ReceivedIncome'] =  $rewar_amount;
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
                    $header_types = [
                        lang('代理ID') => 'string',
                        lang('个人总收益') => 'string',
                        lang('个人充值') => "string",
                        lang('个人流水') => 'string',
                        lang('一级人数') => 'string',
                        lang('一级充值') => 'string',
                        lang('一级流水') => 'string',
                        lang('二级人数') => 'string',
                        lang('二级充值') => 'string',
                        lang('二级流水') => 'string',
                        lang('三级人数') => 'string',
                        lang('三级充值') => 'string',
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
                        $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward,2);
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
                            $row['Lv3Running']
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

    public function updateMobileBackgroundSwitch(){
        $RoleId = input('roleid');
        $MobileBackgroundSwitch = input('type')?:0;
        $res = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo')->where('RoleId='.$RoleId)->data(['MobileBackgroundSwitch'=>$MobileBackgroundSwitch])->update();
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
                return $this->apiJson($result);
            case 'exec':
                $db = new  GameOCDB();
                $result = $db->GetAgentRecord(true);
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
                if ((int)input('exec') == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'string',
                        lang('代理ID') => 'string',
                        lang('个人总收益') => 'string',
                        lang('个人充值') => "string",
                        lang('个人流水') => 'string',
                        lang('一级人数') => 'string',
                        lang('一级充值') => 'string',
                        lang('一级流水') => 'string',
                        lang('二级人数') => 'string',
                        lang('二级充值') => 'string',
                        lang('二级流水') => 'string',
                        lang('三级人数') => 'string',
                        lang('三级充值') => 'string',
                        lang('三级流水') => 'string',
                    ];
                    $filename = lang('代理明细') . '-' . date('YmdHis');
                    $rows =& $result['list'];
//                    halt($rows[0]);
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $lv1rate = bcdiv(10, 1000, 4);
                        $lv2rate = bcdiv(5, 1000, 4);
                        $lv3rate = bcdiv(2.5, 1000, 4);
                        $Lv1Reward = bcmul($row['Lv1Running'], $lv1rate, 4);
                        $Lv2Reward = bcmul($row['Lv2Running'], $lv2rate, 4);
                        $Lv3Reward = bcmul($row['Lv3Running'], $lv3rate, 4);

                        $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward,2);
                        $row['ReceivedIncome'] = $rewar_amount;

                        $item = [
                            $row['AddTime'],
                            $row['ProxyId'],
                            $row['ReceivedIncome'],
                            $row['DailyDeposit'],
                            $row['DailyRunning'],
                            $row['Lv1PersonCount'],
                            $row['Lv1Deposit'],
                            $row['Lv1Running'],

                            $row['Lv2PersonCount'],
                            $row['Lv2Deposit'],
                            $row['Lv2Running'],

                            $row['Lv3PersonCount'],
                            $row['Lv3Deposit'],
                            $row['Lv3Running']
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
            $field = 'A.ParentID,C.OperatorId,A.RoleId as ProxyId,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running';
            $proxyinfo = new UserProxyInfo();
            //$table = 'T_UserProxyInfo';
            $table = '(select ' . $field . '  FROM   CD_UserDB.dbo.T_UserProxyInfo(nolock) as A  left join [CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as B on A.RoleID=B.ProxyId left join [CD_Account].[dbo].[T_Accounts](nolock) as C on C.AccountID=A.RoleID) as t ';
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $filter .= ' and OperatorId='.session('merchant_OperatorId');
            }
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
                $where .= ' and c.LastLoginTime<=\'' .$login_date1.'\'';
            }
            if ($login_date2 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date2.'\'';
            }
            if ($register_ip != '') {
                $where .= ' and c.RegIP=\'' .$register_ip.'\'';
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= ' and c.OperatorId='.session('merchant_OperatorId');
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

            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $password = $this->request->param('password');
            if (empty($roleid) || !isset($parentid) || empty($password)) {
                return $this->apiReturn(1, '', '参数有误');
            }

            $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')->where('OperatorId',session('merchant_OperatorId'))->find();
            if (md5($password) !== $userInfo['PassWord']) {
                return $this->apiReturn(1, '', '密码错误');
            }
  

            $db = new UserDB();
            $has_parents = $db->getTableObject('T_UserProxyInfo')->where('RoleID', $roleid)->value('ParentID');
            if (empty($has_parents)) {
                $data = $this->sendGameMessage('CMD_MD_SET_PROXY_INVITE', [$roleid, $parentid], "DC", 'returnComm');
            } else {
                return $this->apiReturn(1, '', '已经有上级代理');
                // $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid,$parentid], "DC", 'returnComm');
            }
            // $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid,$parentid], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                $comment = '设置上级ID：' . $parentid;
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleid,
                    'adminid' => session('merchant_OperatorId'),
                    'type' => 3,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
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
            $roleid = $this->request->param('roleid');
            $parentid = $this->request->param('parentid');
            $password = $this->request->param('password');
            if (empty($roleid) || !isset($parentid) || empty($password)) {
                return $this->apiReturn(1, '', '参数有误');
            }
            $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')->where('OperatorId',session('merchant_OperatorId'))->find();
            if (md5($password) !== $userInfo['PassWord']) {
                return $this->apiReturn(1, '', '密码错误');
            }
            $data = $this->sendGameMessage('CMD_MD_CHANGE_PROXY', [$roleid, 0], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                $comment = '解绑';
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleid,
                    'adminid' => session('merchant_OperatorId'),
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
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= ' and c.OperatorId='.session('merchant_OperatorId');
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
            '3' => lang('代理流水返利'),
            '4' => lang('代理邀请奖励'),
            '5' => lang('代理首充奖励'),
        ];
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $roleid = $this->request->param('roleid');
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $hd_name = $this->request->param('hd_name');
            $db = new ActivityRecord();
            $where = '';
            $where2 = '1=1';
            if ($roleid != '') {
                $where .= ' and RoleID=' . $roleid;
                $where2 .= ' and RoleID=' . $roleid;
            }
            if ($start_date != '') {
                $where .= ' and AddTime>=\'' . $start_date . '\'';
                $where2 .= ' and AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and AddTime<\'' . $end_date . '\'';
                $where2 .= ' and AddTime<\'' . $end_date . '\'';
            }
            if ($hd_name != '') {
                $where .= ' and ChangeType in(' . $hd_name . ')';
                $where2 .= ' and ChangeType in(' . $hd_name . ')';
            } else {
                $where .= ' and ChangeType in(65,66,69)';
                $where2 .= ' and ChangeType in(65,66,69)';
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= ' and RoleID in(select RoleID FROM [CD_UserDB].[dbo].[T_UserProxyInfo] WITH (NOLOCK) where OperatorId='.session('merchant_OperatorId').')';
                $where2 .= ' and RoleID in(select RoleID FROM [CD_UserDB].[dbo].[T_UserProxyInfo] WITH (NOLOCK) where OperatorId='.session('merchant_OperatorId').')';
            }
            $data = $db->GetPage($where, 'AddTime desc', '*');
            foreach ($data['list'] as $key => &$val) {
                $val['hd_name'] = $activity_arr[$val['ChangeType']] ?? lang('代理流水返利');
                $val['ReceiveAmt'] = FormatMoney($val['ReceiveAmt']);
            }


            if (input('action') == 'list' && input('output') != 'exec') {
                $data['other'] = [];
                $data['other']['total_count'] = $data['count'] ?: 0;
                $data['other']['total_amount'] = FormatMoney($db->getTableSum($where2, 'ReceiveAmt'));
                return $this->apiJson($data);
            }
            if (input('output') == 'exec') {
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
                        lang('代理ID') => 'string',
                        // lang('邀请人昵称') => 'string',
                        lang('返利类型') => "string",
                        // lang('受邀人昵称') => 'string', 
                        lang('领取金额') => "string",
                        lang('时间') => "string",
                    ];
                    $filename = lang('代理领取记录') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['RoleID'], $row['hd_name'], $row['ReceiveAmt'], $row['AddTime']
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

            if($data['count']>0){
                foreach ($data['list']  as $key => &$val) {
                    $lv1rate = bcdiv(7, 1000, 3);
                    $val['DailyTax'] = bcmul($val['DailyRunning'], $lv1rate, 3);
                }
            }

            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['list'], 'success', $data['count']);
            }
            if (input('output') == 'exec') {
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
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
        
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $db = new GameOCDB();
            $where = '1=1';
            if ($start_date != '') {
                $where .= ' and AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and AddTime<\'' . $end_date . '\'';
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= ' and OperatorId='.session('merchant_OperatorId');
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
            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }
            if (input('output') == 'exec') {

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
                        lang('时间') => 'string',
                        lang('代理税收返利') => 'string',
                        lang('代理邀请奖励') => "string",
                        lang('代理周额外奖励') => "string",
                        lang('统计') => "string",
                    ];
                    $filename = lang('代理分享统计') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['AddTime'],
                            $row['RunningBonus'],
                            $row['InviteBonus'],
                            $row['FirstChargeBonus'],
                            $row['total'],
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
            return $this->fetch();
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
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= ' and c.OperatorId='.session('merchant_OperatorId');
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
                    ->field('a.RoleID,a.InviteCode,c.RegisterTime,c.Mobile,b.Lv1PersonCount,b.Lv2PersonCount,b.Lv3PersonCount,a.ReceiveProfit,(b.Lv1Running*'.config('agent_running_parent_rate')[1].'+b.Lv2Running*'.config('agent_running_parent_rate')[2].'+b.Lv3Running*'.config('agent_running_parent_rate')[3].') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit,(d.Lv1Running*'.config('agent_running_parent_rate')[1].'+d.Lv2Running*'.config('agent_running_parent_rate')[2].'+d.Lv3Running*'.config('agent_running_parent_rate')[3].') as yesRunningProfit,(d.Lv1Tax*0.3+d.Lv2Tax*0.09+d.Lv3Tax*0.027) as yesTaxProfit,b.ReceivedIncome')
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
                    ->field('a.RoleID,a.InviteCode,c.RegisterTime,c.Mobile,b.Lv1PersonCount,b.Lv2PersonCount,b.Lv3PersonCount,a.ReceiveProfit,(b.Lv1Running*'.config('agent_running_parent_rate')[1].'+b.Lv2Running*'.config('agent_running_parent_rate')[2].'+b.Lv3Running*'.config('agent_running_parent_rate')[3].') as runningProfit,(b.Lv1Tax*0.3+b.Lv2Tax*0.09+b.Lv3Tax*0.027) as taxProfit,(d.Lv1Running*'.config('agent_running_parent_rate')[1].'+d.Lv2Running*'.config('agent_running_parent_rate')[2].'+d.Lv3Running*'.config('agent_running_parent_rate')[3].') as yesRunningProfit,(d.Lv1Tax*0.3+d.Lv2Tax*0.09+d.Lv3Tax*0.027) as yesTaxProfit,b.ReceivedIncome')
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

                    $val['RegisterTime'] = substr($val['RegisterTime'],0,19);
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
            $data = $m->getTableObject('T_Operator_ProxyDailyShareCount')
                ->where('OperatorId',session('merchant_OperatorId'))
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

                $val['GameNum'] = $val['GameNum'] . '(' . $GameNum_rate*100 . '%)';
                $val['RechargeNum'] =$val['RechargeNum'] . '(' . $RechargeNum_rate*100 . '%)';
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }

            if (input('output') == 'exec') {
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

}