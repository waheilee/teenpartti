<?php

namespace app\admin\lib;

use app\model\UserDB;

class DailyStatisticsLib {
    
    private $GameOCDB;
    private $DataChangelogsDB;
    private $AccountDB;
    private $UserDB;
    private $BankDB;

    private $startDate;
    
    public function __construct() {
        // sql server 数据库
        $this->GameOCDB = new \app\model\GameOCDB();
        $this->DataChangelogsDB = new \app\model\DataChangelogsDB();
        $this->UserDB = new \app\model\UserDB();
        $this->AccountDB = new \app\model\AccountDB();
        $this->BankDB = new \app\model\BankDB();
        
        // 流水开始生成日期  该日期才开始生成流水日志表
        $this->startDate =config('record_start_time');
        
    }
    
    /**
     * 从起始日期开始生成日况统计概要
     * $historical_stock 起始日期已记录的金币历史库存
     */
    public function recordDailyStatistics($start_date, $end_date) {
        $dsModel = new \app\model\DailyStatisticsModel();
        $buyFreeGameModel = new \app\model\BuyFreeGameDailyCountModel();
        $msg = "";
        $status = true;
        try {
            $start = strtotime($start_date);
            $end = strtotime($end_date);
            // 日况流水表
            $table_pre = "T_BankWeathChangeLog_";
            $table_pre1 = "T_UserGameChangeLogs_";
//            $gold_coins_historical_stock = $historical_stock;
            while ($start <= $end) {
                // 日况流水表的日期后缀
                $table_suffix = date('Ymd', $start);
                $day = date('Y-m-d', $start);
                // $start变更为下一个日期
                $start = strtotime('+1 day', $start);
                $table = $table_pre . $table_suffix;
                // 1. 判断表是否存在
                if (!$this->GameOCDB->IsExistTable($table)) {
                    // 表不存在则进行下一个日期
                    continue;
                }
                $new_record = [
                    'day' => $day,
                    'total_profit' => 0,
                    'game_profit_and_loss' => 0,
                    'platform_profit_and_loss' => 0,
                    'game_rtp' => 0,
                    'platform_rtp' => 0,
                    'free_gold_coin_output' => 0,
                    'game_gold_coin_output' => 0,
                    'colored_gold_coin_output' => 0,
                    'total_gold_consumption' => 0,
                    'active_users' => 0,
                    'new_users' => 0,
                    'average_online_time' => 0,
                    'total_recharge' => 0,
                    'total_withdrawal' => 0,
                    'mail_extra_gift_coins' => 0,
                    'free_game_consumption' => 0,
                    'first_charge_num' => 0,
                    'first_charge_amount' =>0,
                    'lottery_bonus' =>0,
                    'tax'=>0,
                ];

                $firstchage = $this->DataChangelogsDB->GetFirstCharge($day);
                save_log('apidata',json_encode($firstchage));

                $new_record['first_charge_num'] = $firstchage['totalnum'];
                $new_record['first_charge_amount'] = $firstchage['amount'];

                // 邮件额外补偿金币  目前需从视图中获取 
                // todo 目前数据存储在视图中, 没有视图时获取不到 故调整视图到sql时需修改方法
                $new_record['mail_extra_gift_coins'] = $this->UserDB->CountUserDailyMailExtraGiftCoins($day);//邮件赠送
                
                // 购买免费游戏的消耗
                $buy_free_game_type = [$dsModel::SBWCT_BUY_FREE_PROP];
                $new_record['free_game_consumption'] = abs($this->countDailyGoldCoinsByChangeType($table, $buy_free_game_type));

                // 总消耗 = (T_UserGameChangeLogs_表GameRoundRunning字段和)
                $table1 = $table_pre1 . $table_suffix;
                // 判断记录总消耗的表是否存在
                $consumption = 0;
                if ($this->GameOCDB->IsExistTable($table1)) {
                    $consumption = $this->countDailyCoinsConsumptionByChangeType($table1, []);
                }
                $new_record['total_gold_consumption'] = $consumption;
                
                // 当天总充值
                $total_recharge_type = [
                    $dsModel::SBWCT_USER_CHANNEL_RECHARGE, // 渠道充值
                ];
//                $new_record['total_recharge'] = $this->countDailyGoldCoinsByChangeType($table, $total_recharge_type)
//                                                + $this->DataChangelogsDB->CountDailyIsPayOrderMailExtraGiftCoins($day);
                $new_record['total_recharge'] = $this->countTotalRechargeByDay($day);//总充值
                // UserDrawBack 的 iMoney  筛选 IsDrawback = 100, UpdateTime
                $new_record['total_withdrawal'] = $this->BankDB->CountTotalDrawBackByDay($day);
                
                // 总盈利 = 当天充值 -  当天提现
                $new_record['total_profit'] = $new_record['total_recharge'] - $new_record['total_withdrawal'];

                // 统计流水表中 游戏金币产出(总产出)= 22 + 总消耗 - 购买免费游戏的消耗
                $game_coins_type = [$dsModel::SBWCT_SYS_REDUCE_GAME_MONEY];
                $new_record['game_gold_coin_output'] = $this->countDailyGoldCoinsByChangeType($table, $game_coins_type) + $consumption - $new_record['free_game_consumption'];

                // 统计流水表中彩金产出
                $colored_coins_type = [$dsModel::SBWCT_USER_MIN_JACKPOT_AWARD, $dsModel::SBWCT_USER_SUPER_JACKPOT_AWARD];
                $new_record['colored_gold_coin_output'] = $this->countDailyGoldCoinsByChangeType($table, $colored_coins_type);

                // 统计流水表中免费金币产出
                $free_coins_type = [
                    $dsModel::SBWCT_SYS_REGISTER_GIVE, //系统注册赠送
                    $dsModel::SBWCT_DAILY_SIGN, // 每日签到
                    $dsModel::SBWCT_USER_RUNNING_RETURN, // 流水奖励
                    $dsModel::SBWCT_CHAC_RANK_UP_AWARD, // 升级奖励
                    $dsModel::SBWCT_USER_WATCH_AD_AWARD, // 观看广告奖励
                    $dsModel::SBWCT_CHARGE_GIFT, // 首充奖励
                    $dsModel::SBWCT_BIND_PHONE_AWARD, // 绑定手机奖励
                    $dsModel::SBWCT_WEEK_SIGN , // 每周签到
                    $dsModel::SBWCT_MONTH_SIGN  , // 每月签到
                    $dsModel::SBWCT_PROXY_VALID_INVITE_AWARD, //代理有效邀请奖励
                    $dsModel::SBWCT_USER_RECHARGE_GIFT, //充值返利赠送
                    $dsModel::SBWCT_USER_SHOP_GIFT, //商店充值赠送 
//                  $dsModel::SBWCT_VIP_UP_AWARD   ,   //VIP升级奖励

                ];
                $new_record['free_gold_coin_output'] = $this->countDailyGoldCoinsByChangeType($table, $free_coins_type);
                // 产出 = (当天游戏金币产出(总产出) + 当天免费金币产出 + 当天彩金产出)
                $ouput_coins = $new_record['game_gold_coin_output'] + $new_record['free_gold_coin_output'] + $new_record['colored_gold_coin_output'];
                
                // 新增税收
                $static_tax =$this->GameOCDB->setTable('T_GameStatisticTotal')->getValueByTable('mydate=\''.$day.'\'','totaltax');
                if(is_null($static_tax))
                    $static_tax = 0;
                $new_record['tax'] = $static_tax;

                // 游戏盈亏(当天) = 当天总消耗 - (当天游戏金币产出(总产出) + 当天免费金币产出 + 当天彩金产出)
                $new_record['game_profit_and_loss'] = $new_record['total_gold_consumption'] - $ouput_coins;


                //平台盈亏=总消耗-游戏总产出 + 游戏总税收 - 免费金币 - 彩金产出
                $new_record['platform_profit_and_loss'] = $new_record['total_gold_consumption'] + $new_record['tax'] - $ouput_coins;
                
                // 当天游戏RTP = (当天游戏金币产出(总产出) + 当天免费金币产出 + 当天彩金产出) / 当天游戏总消耗
                if ($new_record['total_gold_consumption'] != 0) {
                    $new_record['game_rtp'] = ($new_record['game_gold_coin_output'] + $new_record['colored_gold_coin_output']) / $new_record['total_gold_consumption'];
                }

//                // 当天平台RTP
//                if ($new_record['total_gold_consumption'] != 0) {
//                    $new_record['platform_rtp'] = ($ouput_coins - $new_record['tax']) / $new_record['total_gold_consumption'];
//                }
                $new_record['platform_rtp'] =0;
                if($new_record['total_recharge']>0){
                    $new_record['platform_rtp'] = bcdiv($new_record['total_withdrawal'],$new_record['total_recharge'],4);
                }

                // 活跃用户（当天有在线用户)
                $new_record['active_users'] = $this->GameOCDB->GetDailyActiveUserCountByDay($table1);
                
                // 新增用户（当天注册用户）
                $new_record['new_users'] = $this->AccountDB->GetDailyRegistrCountByDay2($day);

                $new_record['AgentReward'] = $this->GameOCDB->GetDailyAgentRewardByDay($day);//代理佣金
                
                // 当天平均在线时长(s) = 当天总在线时长 / 活跃用户',
                // todo暂缓



                $daily_award_coin_type=[
                    $dsModel::SBWCT_USER_DAY_RECHARGE_BONUS,
                    $dsModel::ACTT_FIRST_DEPOSIT_BONUS,
                    $dsModel::ACTT_DAY_DEPOSIT_BONUS
                ];
                $new_record['DailyActivyAwardCoin'] = $this->countDailyGoldCoinsByChangeType($table, $daily_award_coin_type);//充值赠送

                $new_record['lottery_bonus'] = $this->countDailyGoldCoinsByChangeType($table, [$dsModel::ACTT_DAY_LOTTERY_BONUS]);

                // 添加日况记录
                $new_record['update_time'] = date('Y-m-d H:i:s', time());
                $dms_record = $dsModel->getDailyStatisticsByDay($day, 'id,day');

                if (!empty($dms_record)) {
                    $res = $dsModel->UpdateData($new_record,['id'=>$dms_record['id'],'day'=>$day]);
                } else {
                    // 新增数据
                    $res = $dsModel->AddData($new_record);
                }
                if (!$res) {
                    // 当日数据更新或失败后就没必要继续了
                    $msg = "日期为【".$day."】数据记录失败";
                    break;
                }
            }
            
        } catch (\Exception $ex) {
            $status = FALSE;
            $msg = $ex->getMessage();
        }
        return array('status'=>$status, 'msg'=>$msg);
    }

    /**
     * 获取参数day之前最近一条日况统计概要记录
     * @param type $day
     * @return type
     */
    private function getLastDailyStatistics($day) {
        $c_date = date('Y-m-d', $day);
        $dsModel = new \app\model\DailyStatisticsModel();
        // AddTime
        // 获取当前日期之前最近一条日况统计概要记录
        $filter = "SELECT * FROM " . $dsModel->getTableName() . " WHERE day < '" . $c_date . "' ORDER BY day desc LIMIT 1;"; 
        return $dsModel->query($filter);
    }
    
    /**
     * 获取数据库表中某个字段的sum值
     * @param type $table
     * @param type $fields
     * @param type $filter
     * @return int
     */
    private function CountDBField($table, $fields, $filter){
        $data = $this->GameOCDB->DBQuery($table, $fields, $filter);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }
    

    /**
     * 统计流水表中某种类型金币产出
     * @param type $table
     * @param type $type
     * @param type $where 附加过滤条件
     * @return int
     */
    private function countDailyGoldCoinsByChangeType($table, $type=[], $fields = '', $where='') {
        if (empty($fields)) {
            $fields = " ISNULL(sum(Money), 0) as count ";
        }
        $filter = '  WHERE 1=1 ';
        if (!empty($type)) {
            $filter .= " and ChangeType in (" . implode(',', $type) . ")";
        }
        if (!empty($where)) {
            $filter .= " and " . $where;
        }
        return $this->CountDBField($table, $fields, $filter);
    }
    
    /**
     * 计算总消耗
     * @param type $table
     * @param type $type
     * @param type $where 附加过滤条件
     * @return int]
     */
    private function countDailyCoinsConsumptionByChangeType($table, $type=[], $where='') {
        $fields = " ISNULL(sum(RoundBets), 0) as count ";
        $filter = '  WHERE 1=1 ';
        if (!empty($type)) {
            $filter .= " and ChangeType in (" . implode(',', $type) . ")";
        }
        if (!empty($where)) {
            $filter .= " and " . $where;
        }
        return $this->CountDBField($table, $fields, $filter);
    }
    
    /**
     * 先获取数据库中date之前最近一条记录, 然后生成从这条日况统计的日期直到今天的日况统计记录
     * 如果最近一条记录的时间是当天则会更新当天统计的数据
     * @param type $date
     */
    public function updateDailyStatisticsUntilOneDay($date) {
        $end_date = date('Ymd', time());
        $dsModel = new \app\model\DailyStatisticsModel();
        // 获取这天之前最近统计的一条日况统计记录
        $ds_record = $dsModel->getLastDailyStatisticsByDay($date);
        if (empty($ds_record)) {
           // 该日期之前尚未生成日况统计概要记录信息
           // 调用方法生成当前日期起的日况统计概要   从设定的日期开始记录日况统计概要信息
            $start_date = $this->startDate;
            $res = $this->recordDailyStatistics($start_date, $end_date);
           
        } else {
            // 从已有日况统计记录数据的后一天开始生成到当前日期的日况统计概要记录 即 从$ds_record记录的后一天开始生成或更新
            $start_date = date('Ymd', strtotime('+1 day', strtotime($ds_record['day'])));
            $res = $this->recordDailyStatistics($start_date, $end_date);
            
        }
        return $res;
    }
    
    
    /**
     * 日况统计列表访问刷新 
     * 当日的记录如果没有则生成 如果有则相差10分钟就重新统计 已注释
     */
    public function statisticsDailyRefresh() {
        // 当天日期
        $c_date = date('Y-m-d', time());
        $dsModel = new \app\model\DailyStatisticsModel();
        
        // 获取当天日期的日况统计记录
        $ds_record = $dsModel->getDailyStatisticsByDay($c_date);
        // 获取10分钟前的时间值 如果当日统计的时间小于该时间则需要重新统计
        $c_time = strtotime("-10 minute", sysTime());
        if (!empty($ds_record) && (strtotime($ds_record['update_time']) >= $c_time)) { 
            // 已生成当天日况则根据所获取记录的update_time看是否需要更新当日统计数据
            return array('status'=>TRUE, 'msg'=>'');
        } else {
            // 更新昨天和今天的日况统计
            $yesterday = date('Y-m-d', strtotime('-1 day', sysTime()));
            return $this->updateDailyStatisticsUntilOneDay($yesterday);
        }
        
    }
    
    /**
     * 统计某天的金币剩余库存
     */
    public function countDailyCoinsStock($day) {
        $dsModel = new \app\model\DailyStatisticsModel();
        $record = $dsModel->getDailyStatisticsByDay($day);
        // 统计一次用户剩余金币库存
        if (!empty($record)) {
            $UserDB = new \app\model\UserDB();
            $gold_coins_historical_stock = $UserDB->CountUserTotalCoinsStock();
            $data = [
                'update_time' => date('Y-m-d H:i:s', time()),
                'gold_coins_historical_stock' => $gold_coins_historical_stock,
            ];
            $res = $dsModel->UpdateData($data, ['id'=>$record['id']]);
            if (!$res) {
                return array('status'=>FALSE, 'msg'=>$day.'金币库存更新失败');
            }
            return array('status'=>TRUE, 'msg'=>'');
        } else {
            return array('status'=>FALSE, 'msg'=>$day.'不存在日况统计概要记录');
        }
    }

    /**
     * 统计某天充值总数
     * @return int
     */
    public function countTotalRechargeByDay($day)
    {
        $userDB = new UserDB();
        $start_day = date('Y-m-d 00:00:00', strtotime($day));
        $end_day = date('Y-m-d 00:00:00', strtotime("+1 day", strtotime($day)));
        $subQuery = "(SELECT SUM(RealMoney) as Money,AccountID FROM [CD_UserDB].[dbo].[T_UserTransactionChannel] WHERE AddTime>'$start_day' AND AddTime<'$end_day' GROUP BY AccountID) as a";
        return $userDB->getTableObject($subQuery)->sum('Money') * bl;
    }

}