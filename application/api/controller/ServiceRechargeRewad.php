<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2022/7/19
 * Time: 11:45
 */

namespace app\api\controller;

use app\model\AccountDB;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\UserDB;
use app\model\UserProxyInfo;
use phpseclib3\Crypt\EC\Curves\brainpoolP160r1;
use redis\Redis;
use socket\sendQuery;
use think\Collection;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

class ServiceRechargeRewad extends Controller
{

    public function runJob()
    {
        $day = input('day',1);
        if ($day > 1){
            for ($i=1 ;$i<=$day;$i++) {
                $this->RechargeDayJob($i);
            }
        }else{
            $this->RechargeDayJob(1);
        }

    }


    public function RechargeDayJob($day)
    {
        $activetitle = 'Rewards for recharging and returning activities';
        $descript = 'Dear player. You have obtained a recharge reward, please get it';
        $db = new UserDB();
        $dayTime = '-'.$day.' day';
        $yestoday = date('Y-m-d', strtotime($dayTime));
        $where = " datediff(d,addtime,'$yestoday')=0 ";


        $masterdb = new MasterDB();
        $config = $masterdb->getTableObject('T_GameConfig(nolock)')->where('CfgType', 'in', '194,195')->select();
        if (empty($config)) {
            save_log('rechargereturn', '读取配置出错');
            return;
        }

        $min_recharge = bcdiv($config[0]['CfgValue'], bl, 0); //充值最小金额  1000
        $return_rate = bcdiv($config[1]['CfgValue'], 100, 3); //返还比例  5

        if ($min_recharge <= 100 || $return_rate == 0) {
            return;
        }

        $page = 1;
        $row = 15;

        $list = $db->getTableList('T_UserTransactionChannel', $where, $page, $row, 'AccountID,sum(RealMoney) as RealMoney', ['AccountID' => 'asc'], 'AccountID');
        $list = $list['list'];
        while ($list) {
            foreach ($list as $k => $v) {
                if ($v['RealMoney'] >= $min_recharge) {
                    $key = $yestoday . $v['AccountID'];
                    if (!Redis::has($key)) {
                        $rewardcoin = bcmul($v['RealMoney'], $return_rate, 3);
                        $rewardcoin = $rewardcoin * 1000;
                        $sendQuery = new sendQuery();
                        $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2", [0, $v['AccountID'], 8, 10, $rewardcoin, 0, 10, 0, 1, $activetitle, $descript, '', '', '']);
                        $retcode = unpack('cint', $res)['int'];
                        save_log('rechargereturn', '玩家id:' . $v['AccountID'] . '========金额:' . $rewardcoin . ',更新状态' . json_encode($retcode));
                        Redis::set($key, 100);
                    }
                }
            }
            $list = $db->getTableList('T_UserTransactionChannel', $where, $page, $row, 'AccountID,sum(RealMoney) as RealMoney', ['AccountID' => 'asc'], 'AccountID');
            $list = $list['list'];
            $page++;
        }
        echo $yestoday . '任务已完成';
    }

    public function weekRechargeGive()
    {
        $activetitle = 'Rewards for recharging and returning activities';
        $descript = 'Dear player. You have obtained a recharge reward, please get it';
        $db = new UserDB();

        if (date('w') == 1) { //当前周一时的处理
            $lastMonday = date('Y-m-d', strtotime('last monday')); //当前周一，取上周一
        } else { //当前不是周一时处理
            $lastMonday = date('Y-m-d', strtotime('-1 week last monday')); //当前非周一，取上前推一周取周一
        }
//        $yestoday = date('Y-m-d', strtotime('-1 day'));
        $where = " datediff(d,addtime,'$lastMonday')=0 ";


        $masterdb = new MasterDB();
        $config = $masterdb->getTableObject('T_GameConfig(nolock)')
            ->where('CfgType', 'in', '194,195')->select();
        if (empty($config)) {
            save_log('rechargereturn', '读取配置出错');
            return;
        }
        $min_recharge = bcdiv($config[0]['CfgValue'], bl, 0); //充值最小金额  1000
        $return_rate = bcdiv($config[1]['CfgValue'], 100, 3); //返还比例  5

        if ($min_recharge <= 100 || $return_rate == 0) {
            save_log('rechargereturn', '赠送比例为0；');
            return;
        }

        $page = 1;
        $row = 15;

        $list = $db->getTableList('T_UserTransactionChannel', $where, $page, $row,
            'AccountID,sum(RealMoney) as RealMoney', ['AccountID' => 'asc'], 'AccountID');
        $list = $list['list'];
        while ($list) {
            foreach ($list as $k => $v) {
                if ($v['RealMoney'] >= $min_recharge) {
                    $key = $lastMonday . $v['AccountID'];
                    if (!Redis::has($key)) {
                        $rewardcoin = bcmul($v['RealMoney'], $return_rate, 3);
                        $rewardcoin = $rewardcoin * 1000;
                        $sendQuery = new sendQuery();
                        $res = $sendQuery->callback(
                            "CMD_MD_SYSTEM_MAILv2", [0, $v['AccountID'], 8, 10, $rewardcoin,
                            0, 10, 0, 1, $activetitle, $descript, '', '', '']);
                        $retcode = unpack('cint', $res)['int'];
                        save_log('rechargereturn', '玩家id:' . $v['AccountID'] . '========金额:' . $rewardcoin . ',更新状态' . json_encode($retcode));
                        Redis::set($key, 100);
                    }
                }
            }
            $list = $db->getTableList('T_UserTransactionChannel', $where, $page, $row,
                'AccountID,sum(RealMoney) as RealMoney', ['AccountID' => 'asc'], 'AccountID');
            $list = $list['list'];
            $page++;
        }
        echo $lastMonday . '任务已完成';
    }

    /**
     * 用户周亏损奖励
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function weekLossReward()
    {
        // $testId = 92932198;
        // if (!empty($testId)) {
        //     $this->test($testId);
        //     die();
        // }
        save_log('weekLossReward', '任务开始');
        $activeTitle = 'Weekly game loss refund';
        $desc = 'Dear player. You have received a weekly game loss return, please claim it';

        // 定义每次查询的数据数量
        $limit = 1000;
        $userDB = new UserDB();
        // 查询总记录数
        $totalCount = $userDB->getTableObject('View_Accountinfo')->count();
        $totalPage = (int)ceil($totalCount / $limit);
        // 分批处理数据
        for ($page = 1; $page <= $totalPage; $page++) {
            // 查询当前批次的数据
            $dataList = $userDB->getTableObject('View_Accountinfo')
                ->where('Locked', 0)
                ->field('AccountID,VipLv,Locked')->limit($limit)
                ->page($page)->select();
            // 处理当前批次的数据
            foreach ($dataList as $data) {
                $userVipLevel = $data['VipLv'];
                $roleId = $data['AccountID'];
                $userSendMailRedisKey = 'USER_WEEK_LOSS_REWARD_SEND_MAIL_' . $roleId;
                $expireTime = 3600 * 20;
                if (Redis::has($userSendMailRedisKey)) {
                    continue;
                }
                $userWeekLossMoneySum = $this->userLastWeekLossAmountCount($roleId);
                if (!$userWeekLossMoneySum || $userWeekLossMoneySum > 0) {
                    continue;
                }
                $userWeekLossMoneySum = abs($userWeekLossMoneySum);//用户亏损数额
                $vipWeekLossRate = bcdiv($this->getVipLevelConfig($userVipLevel), 100, 2);//用户VIP等级周亏损赠送率
                $waitSendReward = bcmul($vipWeekLossRate, $userWeekLossMoneySum, 2);//赠送数额
                if ($waitSendReward <= 0) {
                    continue;
                }
                $sendQuery = new sendQuery();
                $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2",
                    [0, $roleId, 8, 10, $waitSendReward, 0, 10, 0, 1, $activeTitle, $desc, '', '', '']
                );
                $retCode = unpack('cint', $res)['int'];
                save_log('weekLossReward', '玩家id:' . $roleId . '========金额:' . $waitSendReward . ',更新状态' . json_encode($retCode));
                Redis::set($userSendMailRedisKey, $waitSendReward, $expireTime);

            }
            save_log('weekLossReward', '总页数：' . $totalPage . '==========处理页数:' . $page);
            // 释放内存
            unset($dataList);
        }
        save_log('weekLossReward', '任务结束');
    }


    /**
     * 获取vip等级表里周亏损奖励配置系数
     * @param $vipLevel
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getVipLevelConfig($vipLevel)
    {
        $vipConfigRedisKey = 'USER_VIP_LEVEL_CONFIG_KEY';
        $expireTime = 3600 * 20;
        if (!Redis::has($vipConfigRedisKey)) {
            $masterDB = new MasterDB();
            $vipConfigs = $masterDB->getTableObject('T_VipLevel')
                ->field('VipLevel,WeekLossRate')
                ->select();
            Redis::set($vipConfigRedisKey, $vipConfigs, $expireTime);
        } else {
            $vipConfigs = Redis::get($vipConfigRedisKey);
        }
        foreach ($vipConfigs as $vipConfig) {
            if ($vipLevel == $vipConfig['VipLevel']) {
                return $vipConfig['WeekLossRate'];
            }
        }
    }

    /**
     * 根据用户ID统计出用户在上周亏损数额
     * @param $roleId
     * @return float|int|string
     */
    public function userLastWeekLossAmountCount($roleId)
    {
        // 获取上周一和上周日的日期范围
        $lastMonday = date('Y-m-d', strtotime('-1 week last monday'));
        $lastSunday = date('Y-m-d', strtotime('last sunday'));
        // $lastMonday = date('Y-m-d', strtotime('2023-07-25'));
        // $lastSunday = date('Y-m-d', strtotime('2023-07-27'));
        // 构建日期范围的分表后缀数组
        $dateRange = [];
        $currentDate = $lastMonday;
        while ($currentDate <= $lastSunday) {
            $dateRange[] = $currentDate;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        // 查询UserGameChangeLogs表中指定日期范围内RoleID对应的Money字段总和
        $userWeekLossMoneySum = 0;
        foreach ($dateRange as $date) {
            // save_log('weekLossReward', '测试日期:' . $date );
            // 构建分表名
            $tableName = 'T_UserGameChangeLogs_' . str_replace('-', '', $date);
            $gameOcDB = new GameOCDB();
            $isTable = $gameOcDB->DBOriginQuery("select top 1 * from sysObjects where Id=OBJECT_ID(N'$tableName') and xtype='U'");
            if (!$isTable){
                continue;
            }
            // 使用查询构建器查询指定分表
            $sum = $gameOcDB->getTableObject($tableName)
                ->where('AddTime', 'between time', [$date . ' 00:00:00', $date . ' 23:59:59'])
                ->where('RoleID', '=', $roleId) // 假设$roleID为要查询的RoleID
                ->sum('Money');
            // 累加Money总和
            $userWeekLossMoneySum += $sum;
        }
        // save_log('weekLossReward', '测试玩家id:' . $roleId . '赠送额========金额:' . $userWeekLossMoneySum);
        return $userWeekLossMoneySum;
    }

    public function test($roleId)
    {
        $activeTitle = 'Rewards for recharging and returning activities';
        $desc = 'Dear player. You have obtained a recharge reward, please get it';
        $userDB = new UserDB();
        $data = $userDB->getTableObject('View_Accountinfo')
            ->where('AccountID', $roleId)
            ->where('Locked', 0)
            ->field('AccountID,VipLv,Locked')
            ->find();
        $userVipLevel = $data['VipLv'];
        $roleId = $data['AccountID'];
        $userWeekLossMoneySum = $this->userLastWeekLossAmountCount($roleId);
        if (!$userWeekLossMoneySum || $userWeekLossMoneySum > 0) {
            save_log('weekLossReward', '玩家id:' . $roleId . '值不存在========金额:' . $userWeekLossMoneySum);
            die();
        }
        $userWeekLossMoneySum = abs($userWeekLossMoneySum);//用户亏损数额
        $vipWeekLossRate = bcdiv($this->getVipLevelConfig($userVipLevel), 100, 2);//用户VIP等级周亏损赠送率
        $waitSendReward = bcmul($vipWeekLossRate, $userWeekLossMoneySum, 2);//赠送数额
        if ($waitSendReward <= 0) {
            save_log('weekLossReward', '测试玩家id:' . $roleId . '赠送额小于0========金额:' . $userWeekLossMoneySum);
            die();
        }
        $sendQuery = new sendQuery();
        $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2",
            [0, $roleId, 8, 10, $waitSendReward, 0, 10, 0, 1, $activeTitle, $desc, '', '', '']
        );
        $retCode = unpack('cint', $res)['int'];
        save_log('weekLossReward', '测试玩家id:' . $roleId . '========金额:' . $waitSendReward . ',更新状态' . json_encode($retCode));
    }


    public function testSend()
    {
        $sendQuery = new sendQuery();
        $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2", [39873346, 10000, 1]);
        echo 123;
    }
}