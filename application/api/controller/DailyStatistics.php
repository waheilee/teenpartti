<?php

namespace app\api\controller;


use app\model\UserDB;
use think\Controller;

/**
 * 每日统计定时请求接口
 */


class DailyStatistics extends Controller {
    
    /**
     * 每日定时统计 更新两天前到当天的日况统计
     * (只允许在每天的 00:00:00 - 02:00:00 更新前一天的金币库存 )
     * 
     */
    public function DailyCountCrontab()
    {
        // 当天日期
        $default_timezone=config('default_timezone');
        date_default_timezone_set($default_timezone);
        $c_date = date('Y-m-d', strtotime('-2 day', time()));
        $dsLib = new \app\admin\lib\DailyStatisticsLib();
        try {
            $data = $dsLib->updateDailyStatisticsUntilOneDay($c_date);
            if (!$data['status']) {
                throw new \Exception($data['msg']);
            }
            $limit_start_time = strtotime(date('Y-m-d 00:00:00', time()));
            $limit_end_time = strtotime(date('Y-m-d 04:00:00', time()));
            $now_time = strtotime(date('Y-m-d H:i:s', time()));
            echo date('Y-m-d H:i:s',$now_time);
            /***  只允许在每天的 00:00:00 - 02:00:00 更新前一天的金币库存   ***/
            if (($now_time >= $limit_start_time) && ($now_time < $limit_end_time)) {
                $yesterday = date('Y-m-d', strtotime('-1 day', time()));
                // 统计一次用户剩余金币库存
                $update_res = $dsLib->countDailyCoinsStock($yesterday);
                save_log('dailyjob','统计库存状态:'.json_encode($update_res));
                if (!$update_res['status']) {
                    throw new \Exception($update_res['msg']);
                }
            }
            
        } catch (\Exception $ex) {
            $data = array('status'=> FALSE, 'msg'=> $ex->getMessage());
        }
        return json_encode($data, 256);
    }
    
    /**
     * 从start_date那天起到当前日期重新统计日况概要 默认start_date为前一天日期
     */
    public function UpdateDailyCountCrontab()
    {
        
//        return; // 关闭功能
        
        $dsLib = new \app\admin\lib\DailyStatisticsLib();
        try {
            $start_date = trim($this->request->get('start_date'));
            $end_date = trim($this->request->get('end_date'));
            if (empty($start_date)) {
                $start_date = date('Ymd', strtotime('-1 day', time()));
            } else {
                $start_date = date('Ymd', strtotime($start_date));
            }
            if (empty($end_date)) {
                $end_date = date('Ymd', time());
            } else {
                $end_date = date('Ymd', strtotime($end_date));
            }
            $data = $dsLib->recordDailyStatistics($start_date, $end_date);
            
        } catch (\Exception $ex) {
            $data = array('status'=> FALSE, 'msg'=> $ex->getMessage());
        }
        return json_encode($data, 256);
    }
    
    /**
     * 更新前一天充值用户数影响的对应记录的日留存
     */
    public function DailyRechargeRetainedRecordCrontab() 
    {
        $lib = new \app\admin\lib\RetainedLib();
        try {
            $start_date = trim($this->request->get('start_date'));
            $end_date = trim($this->request->get('end_date'));
            if (empty($start_date)) {
                $start_date = date('Ymd', strtotime('-1 day', time()));
            } else {
                $start_date = date('Ymd', strtotime($start_date));
            }
            if (empty($end_date)) {
                $end_date = date('Ymd', time());
            } else {
                $end_date = date('Ymd', strtotime($end_date));
            }
            $data = $lib->recordRechargeRetainedInDayRange($start_date, $end_date);
        } catch (\Exception $ex) {
            $data = array('status'=> FALSE, 'msg'=> $ex->getMessage());
        }
        return json_encode($data, 256);
    }
    
    /**
     * 更新前一天免费游戏购买日统计数据
     */
    public function UpdateBuyFreeGameDailyCountCrontab() 
    {
        $lib = new \app\admin\lib\BuyFreeGameDailyCountLib();
        try {
            $start_date = trim($this->request->get('start_date'));
            $end_date = trim($this->request->get('end_date'));
            if (empty($start_date)) {
                $start_date = date('Ymd', strtotime('-1 day', time()));
            } else {
                $start_date = date('Ymd', strtotime($start_date));
            }
            if (empty($end_date)) {
                $end_date = date('Ymd', time());
            } else {
                $end_date = date('Ymd', strtotime($end_date));
            }
            $data = $lib->updateBuyFreeGameDailyCountInDayRange($start_date, $end_date);
        } catch (\Exception $ex) {
            $data = array('status'=> FALSE, 'msg'=> $ex->getMessage());
        }
        return json_encode($data, 256);
    }
    

    /**
     * 更新前一天代理查询表数据
     */
    public function updateAgentQuery(){

        set_time_limit(0);

        $GameOCDB = new \app\model\GameOCDB;
        $UserDB = new \app\model\UserDB;
        $date = date('Y-m-d H:i:s');
        $GameOCDB->getTableObject('T_ProxyQuery')->where('1=1')->delete();
        $agent_list = $UserDB->getTableObject('T_UserProxyInfo')->alias('a')
                     ->join('[CD_Account].[dbo].[T_Accounts] b','b.AccountID=a.RoleID','left')
                     ->where('a.RoleID','>',0)
                     ->field('a.RoleID,b.Mobile,b.RegisterTime,a.TotalProfit,a.AbleProfit')
                     ->select();

        foreach ($agent_list as $key => $role) {
            $roleid = $role['RoleID'];
            // $team = $this->getTeam($roleid,3);
            $team = $this->getTeamSql($roleid);
        
            $direct_agent_num = count($UserDB->DBOriginQuery($team[1]));
            if ($direct_agent_num>0) {
                $direct_rebate_num = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID in('.$team[1].')')->where('SingleTax','>',0)->field('RoleID')->group('RoleID')->select();

                $role['direct_rebate_num'] = count($direct_rebate_num);
            } else {
                $role['direct_rebate_num'] = 0;
            }
            $role['direct_agent_num']  = $direct_agent_num;
            $other_agent = array_merge($UserDB->DBOriginQuery($team[2]),$UserDB->DBOriginQuery($team[3]));
            $other_agent = array_column($other_agent,'RoleID');
            $other_agent_num = count($other_agent);
            if ($other_agent_num>0) {
                //分块处理
                $j = ceil($other_agent_num/500);
                $other_rebate_num = 0;
                for ($i=0; $i < $j; $i++) { 
                    $other_agent_clunk = array_slice($other_agent,$i*500,500);
                    $other_rebate_num_clunk = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID', 'in',$other_agent_clunk)->where('SingleTax','>',0)->field('RoleID')->group('RoleID')->select();
                    $other_rebate_num += count($other_rebate_num_clunk);
                }
                
                $role['other_rebate_num'] = $other_rebate_num;
            } else {
                $role['other_rebate_num'] = 0;
            }
            $role['other_agent_num']  = $other_agent_num;
            //昨日
            $yes_date = date('Y-m-d',strtotime('-1 days'));
            $role['yes_total_profit'] = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID',$roleid)->whereTime('Date',$yes_date)->value('RewardAmount')?:0;
            $role['yes_direct_profit'] = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID in('.$team[1].')')->whereTime('Date',$yes_date)->value('SingleTax')?:0;
            $yes_two_profit = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID in('.$team[2].')')->whereTime('Date',$yes_date)->value('SingleTax')?:0;
            $yes_thress_profit = $UserDB->getTableObject('T_UserProxyDailyData')->where('RoleID in('.$team[3].')')->whereTime('Date',$yes_date)->value('SingleTax')?:0;
            $role['yes_total_profit'] = bcdiv($role['yes_total_profit'],bl,2)/1;
            $role['yes_direct_profit'] = bcdiv(bcmul($role['yes_direct_profit'],0.3),bl,2)/1;
            $role['yes_other_profit'] = bcdiv(bcadd(bcmul($yes_two_profit,0.3*0.3),bcmul($yes_thress_profit,0.3*0.3*0.2)),bl,2)/1;
            $role['TotalProfit'] = bcdiv($role['TotalProfit'],bl,2)/1;
            $role['AbleProfit'] = bcdiv($role['AbleProfit'],bl,2)/1;
            
            $role['update_time'] = $date;
            unset($role['ROW_NUMBER']);
            $GameOCDB->getTableObject('T_ProxyQuery')->insert($role);
            echo $roleid;
        }
    }

    public function getTeam($agent_uids='',$depth=3,$i=1){
        static $team= [];
        if ($i > $depth) {
            return $team;
        }
        $m = new \app\model\UserDB;
        $proxy_model = $m->getTableObject('T_UserProxyInfo');

        $agent_uids  = $proxy_model->where('ParentID','in',$agent_uids)->column('RoleID')?:[];
        if (empty($agent_uids)) {
            for ($j=$i; $j < $depth+1; $j++) { 
                $team[$j] = [];
            }
            return $team;
        } else {
            $agent_uids = array_keys($agent_uids);
            $team[$i] = $agent_uids;
            $i += 1;
            return $this->getTeam($agent_uids,$depth,$i);
        }
    }

    public function getTeamSql($agent_uids=''){
        $team = [];
        $team[1] = "SELECT RoleID FROM [CD_UserDB].[dbo].[T_UserProxyInfo] WHERE ParentID='".$agent_uids."'";
        $team[2] = "SELECT RoleID FROM [CD_UserDB].[dbo].[T_UserProxyInfo] WHERE ParentID IN(".$team[1].")";
        $team[3] = "SELECT RoleID FROM [CD_UserDB].[dbo].[T_UserProxyInfo] WHERE ParentID IN(".$team[2].")";
        return $team;
    }

//    public function test()
//    {
//        $day = '2023-10-30';
//        $userDB = new UserDB();
//        $start_day = date('Y-m-d 00:00:00', strtotime($day));
//        $end_day = date('Y-m-d 00:00:00', strtotime("+1 day", strtotime($day)));
//        $subQuery = "(SELECT SUM(RealMoney) as Money,AccountID FROM [CD_UserDB].[dbo].[T_UserTransactionChannel] WHERE AddTime>'$start_day' AND AddTime<'$end_day' GROUP BY AccountID) as a";
//        return $userDB->getTableObject($subQuery)->sum('Money');
//
//    }
}