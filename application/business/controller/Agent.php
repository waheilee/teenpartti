<?php

namespace app\business\controller;

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
            $ProxyChannelId = input('ProxyChannelId', 0);
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
            if ($ProxyChannelId > 0) {
                $filter .= ' and ProxyChannelId =' . $ProxyChannelId;//' and DirectCount>0';
            }
//            else{
//                $filter .= ' and ParentID=0' ;
//            }
            // if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            //     $filter .= ' and OperatorId='.session('merchant_OperatorId');
            // }
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');

            $order = 'ProxyId desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            $field = 'A.ParentID,A.OperatorId,A.RoleId as ProxyId,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running,A.MobileBackgroundSwitch,C.ProxyChannelId';
            $proxyinfo = new UserProxyInfo();
            //$table = 'T_UserProxyInfo';
            
            $table = '(select ' . $field . '  FROM   CD_UserDB.dbo.T_UserProxyInfo(nolock) as A  left join [CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as B on A.RoleID=B.ProxyId left join [CD_Account].[dbo].[T_Accounts](nolock) as C on C.AccountID=A.RoleID  where C.ProxyChannelId in('.implode(',', $all_ProxyChannelId).')) as t ';
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

                        $return_rate = config('agent_running_parent_rate');

                        $lv1rate = $return_rate[1];//bcdiv(10, 1000, 4);
                        $lv2rate = $return_rate[2];//bcdiv(5, 1000, 4);
                        $lv3rate = $return_rate[3];//bcdiv(2.5, 1000, 4);
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
                        lang('所属业务员ID') => 'string',
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
                        $return_rate = config('agent_running_parent_rate');
                        $lv1rate = $return_rate[1];//bcdiv(10, 1000, 4);
                        $lv2rate = $return_rate[2];//bcdiv(5, 1000, 4);
                        $lv3rate = $return_rate[3];//bcdiv(2.5, 1000, 4);
                        $Lv1Reward = bcmul($row['Lv1Running'], $lv1rate, 4);
                        $Lv2Reward = bcmul($row['Lv2Running'], $lv2rate, 4);
                        $Lv3Reward = bcmul($row['Lv3Running'], $lv3rate, 4);
                        $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
                        $rewar_amount = bcadd($rewar_amount, $Lv3Reward,2);
                        $row['ReceivedIncome'] = $rewar_amount;

                        $item = [
                            $row['ProxyId'],
                            $row['ProxyChannelId'],
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
                $result = $db->GetBusinessAgentRecord(true);
                $sumdata = $db->GetBusinessAgentRecordSum(true);
                $result['other'] = $sumdata;
                return $this->apiJson($result);
            case 'exec':
                $db = new  GameOCDB();
                $result = $db->GetBusinessAgentRecord(true);
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

                        $return_rate = config('agent_running_parent_rate');
                        $lv1rate = $return_rate[1];//bcdiv(10, 1000, 4);
                        $lv2rate = $return_rate[2];//bcdiv(5, 1000, 4);
                        $lv3rate = $return_rate[3];//bcdiv(2.5, 1000, 4);

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
            $where .= ' and c.GmType<>0';
            // if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            //     $where .= ' and c.OperatorId='.session('merchant_OperatorId');
            // }
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');

            $data = $m->getTableObject('T_UserProxyInfo')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
                ->join('[CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs](NOLOCK) e', 'e.RoleID=a.RoleID and ChangeType=5 and IfFirstCharge=1', 'left')
                ->where('c.ProxyChannelId','in',$all_ProxyChannelId)
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
                ->where('c.ProxyChannelId','in',$all_ProxyChannelId)
                ->where($where)
                ->field('sum(d.TotalDeposit) TotalDeposit,sum(d.TotalRollOut) TotalRollOut,sum(b.Lv1Tax + b.Lv2Tax + b.Lv3Tax)TotalTax')
                ->find();
            $other['TotalDeposit'] = bcdiv($other['TotalDeposit'] ?: 0, 1, 3) / 1;
            $other['TotalRollOut'] = bcdiv($other['TotalRollOut'] ?: 0, 1, 3) / 1;
            $other['Totalyk'] = bcsub($other['TotalDeposit'], $other['TotalRollOut'], 3) / 1;
            $other['TotalTax'] = bcdiv($other['TotalTax'] ?: 0, bl, 3) / 1;
            return $this->apiReturn(0, $data['data'], 'success', $data['total'],$other);
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
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');

            $field = "a.RoleID,a.ParentID,a.TotalWater TotalTax,a.TotalProfit,d.TotalDeposit,d.TotalRollOut";
            $data = $m->getTableObject('T_UserProxyInfo')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) b', 'b.ProxyId=a.RoleID', 'left')
                ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
                ->where('c.ProxyChannelId','in',$all_ProxyChannelId)
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

    //代理推广明细查询
    public function promotionDetails()
    {
        $action = $this->request->param('action');
        $m = new UserDB();
        if ($action == 'list') {

            $db = new GameOCDB();
            $data = $db->GetBusinessAgentRecord(true);
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

    public function agentShare()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            // $roleid   = $this->request->param('roleid');
            $limit = $this->request->param('limit') ?: 15;
            $m = new GameOCDB();
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');
            $data = $m->getTableObject('T_Biz_ProxyDailyShareCount')
                ->where('ProxyChannelId','in',$all_ProxyChannelId)
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

            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');
            
            $subQuery = "(SELECT AddTime,sum(RunningBonus) RunningBonus,sum(InviteBonus) InviteBonus,sum(FirstChargeBonus) FirstChargeBonus FROM [OM_GameOC].[dbo].[T_ProxyDailyBonus](NOLOCK) AS B  left join [CD_Account].[dbo].[T_Accounts](nolock) as C on C.AccountID=B.RoleID  where C.ProxyChannelId in(".implode(',',$all_ProxyChannelId).") and " . $where . " GROUP BY [AddTime])";
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

    public function agentCoinApply()
    {

        $userproxyinfo = new UserProxyInfo();

        $action = $this->request->param('action');
        if ($action == 'list') {
            $page = intval(input('page')) ? intval(input('page')) : 1;  //页码
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('roleid', '');
            $start = input('start','');
            $end = input('end','');
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');

            $where = '1=1';
            
            if (!empty($roleid)) {
                $where .= " and roleid=" . $roleid;
            }
            if(!empty($start)){
                $where .= " and addtime >='$start'";
            }

            if(!empty($end)){
                $where .= " and addtime <='$end'";
            }
            $order = 'RoleID desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            $db = new GameOCDB();
            
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');

            $subQuery = "(SELECT RoleID,AddTime,sum(RunningBonus) RunningBonus,sum(InviteBonus) InviteBonus,sum(FirstChargeBonus) FirstChargeBonus FROM [OM_GameOC].[dbo].[T_ProxyDailyBonus](NOLOCK) AS B  left join [CD_Account].[dbo].[T_Accounts](nolock) as C on C.AccountID=B.RoleID  where C.ProxyChannelId in(".implode(',',$all_ProxyChannelId).") and ". $where . " GROUP BY [RoleID],AddTime)";
            $data = $db->getTableObject($subQuery)->alias('a')
                ->order($order)
                ->paginate($limit)
                ->toArray();
            $list = $data['data'];
            $count = $data['total'];
            foreach ($data['data'] as $key => &$val) {
                $val['RunningBonus'] = FormatMoney($val['RunningBonus'] ?: 0);
                $val['InviteBonus'] = FormatMoney($val['InviteBonus'] ?: 0);
                $val['FirstChargeBonus'] = FormatMoney($val['FirstChargeBonus'] ?: 0);
                $val['TotalProfit'] = round(($val['RunningBonus'] + $val['InviteBonus'] + $val['FirstChargeBonus']), 2);
            }
            if ($action == 'list' && input('output') != 'exec') {
                $other = [
                    
                ];

                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }

            if (input('output') == 'exec') {
                if (empty($list)) {
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
                        lang('玩家ID') => 'string',
                        lang('代理流水返利') => "string",
                        lang('代理邀请奖励') => "string",
                        lang('代理首充奖励') => "string",
                        lang('总收益') => 'string',
                    ];
                    $filename = lang('代理奖励列表') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['RoleID'],
                            $row['d1'],
                            $row['d2'],
                            $row['d3'],
                            $row['TotalProfit'],
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

    public function getXbusiness($ProxyChannelIds){
        static $result = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid','in',$ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result = array_unique(array_merge($result,$xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
    }

    //博主
    public function blogger(){
        if (input('action') == 'list') {
            $data = (new \app\model\UserDB())->getBloggerData();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }

    public function addBlogger(){
        if ($this->request->method() == 'POST') {
            $roleId = input('roleid', '');
            $WeChat = input('WeChat', '');
            $AccountDB = new \app\model\AccountDB();
            $roleidinfo = $AccountDB->getTableObject('T_Accounts(NOLOCK)')->where('AccountID',$roleId)->find();

            if (empty($roleidinfo)) {
                return $this->apiReturn(1, '', '玩家ID不存在，请重新输入');
            }
            if ($roleidinfo['ProxyChannelId'] != session('business_ProxyChannelId')) {
                return $this->apiReturn(1, '', '无法添加该玩家');
            }
            if ($roleidinfo['IsBlogger'] == 1) {
                return $this->apiReturn(1, '', '请勿重复提交博主ID');
            }
            $data = ['IsBlogger'=>1,'BloggerDate'=>date('Y-m-d H:i:s'),'WeChat'=>$WeChat];
            $res = $AccountDB->getTableObject('T_Accounts')->where('AccountID',$roleId)->data($data)->update();
            if ($res) {
                return $this->apiReturn(0, '', 'success');
            } else {
                return $this->apiReturn(1, '', 'fail');
            }
        } else {
            return $this->fetch();
        }
    }

    public function editWechat(){
        $id = request()->param('id');
        $field = request()->param('field');
        $value = request()->param('value');
        $AccountDB = new \app\model\AccountDB();
        $res = $AccountDB->getTableObject('T_Accounts')->where('AccountID',$id)->data(['WeChat'=>$value])->update();
        if($res){
            return ['code'=>0,'msg'=>'success'];
        } else {
            return ['code'=>1,'msg'=>'更新失败'];
        }
    }

    //掉绑比例
    public function unbindRecord(){
        if (input('action') == 'list') {
            $data = (new \app\model\UserDB())->unbindRecord();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }
}