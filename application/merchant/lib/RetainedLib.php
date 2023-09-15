<?php

/* 
 * 留存lib
 */


namespace app\admin\lib;

class RetainedLib {
    
    private $UserDB;
    
    private $recharge_table;
    private $retainedModel;


    public function __construct() {
        // sql server 数据库
        $this->UserDB = new \app\model\UserDB();
        
        // 流水开始生成日期  该日期才开始生成流水日志表
        $this->recharge_table = "T_UserTransactionChannel";
        $this->retainedModel = new \app\model\RechargeRetainedModel();
        
    }
    
    /**
     * 统计某天充值用户的数量
     * @param string $day  y-m-d H:i:s
     * @return type
     */
    public function countRechargeUserAboutOneDay($day) {
        $start = date('Y-m-d 00:00:00', strtotime($day));
        $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($day)));
        $sql = 'SELECT count(DISTINCT AccountID) as count from ' . $this->recharge_table . ''
                . ' WHERE AddTime >= \'' . $start . '\' and AddTime < \''. $end . '\'';
        $data = $this->UserDB->DBOriginQuery($sql);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
        
    }
    
    /**
     * 计算$r_day日期充值的用户 在$c_day日期有多少个有充值
     * @param type $r_day
     * @param type $c_day
     */
    public function countRechargeRetainedBetweenTwoDay($r_day, $c_day) {
        $table = $this->recharge_table;
        $r_day_start = date('Y-m-d 00:00:00', strtotime($r_day));
        $r_day_end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($r_day)));
        $c_day_start = date('Y-m-d 00:00:00', strtotime($c_day));
        $c_day_end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($c_day)));
        
        $query = 'SELECT a.AccountID from '.$table.' a where  a.AddTime >= \'' . $r_day_start . '\' and a.AddTime < \'' . $r_day_end . '\''
                . ' and a.AccountID  IN (SELECT b.AccountID FROM  '.$table.' b where AddTime >= \''.$c_day_start.'\' and AddTime < \''.$c_day_end.'\'  GROUP BY AccountID) GROUP BY a.AccountID';
        $sql = "SELECT count(AccountID) as count FROM (" . $query . ") as t";
        $data = $this->UserDB->DBOriginQuery($sql);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }
    
    /**
     * 计算$c_day日期决定的$many_days日留存
     * 
     * 例   $c_day=29  $many_days=2  则方法更新28号的2日留存字段
     * 
     * @param type $c_day 日期    Y-m-d 或 Y-m-d H:i:s 格式
     * @param type $many_days 几日留存
     * @return string
     */
    public function countRechargeRetainedAboutDayRange($c_day, $many_days) {
        $r_day = date('Y-m-d', strtotime('-' . $many_days . ' day', strtotime($c_day)));
        // 获取$many_days天前充值用户数量
        $before_recharge_users_num = $this->countRechargeUserAboutOneDay($r_day);
        if ($before_recharge_users_num <= 0) {
            // $many_days天前不存在充值用户则跳过
            return array('status'=>true);
        }        
        // 获取$c_day日期$many_days天前的日留存记录
        $record = $this->retainedModel->getDailyRechargeRetainedByDay($r_day, 'id, day');
        if (empty($record)) {
           $record = [
               'day' => $r_day,
           ]; 
        }
        $record['recharge_user_num'] = $before_recharge_users_num;
        // 计算留存数
        $retained_num = $this->countRechargeRetainedBetweenTwoDay($r_day, $c_day);
        if ($retained_num == 0) {
            // 日留存数为0则跳过
            return array('status'=>true);
        }
        switch ($many_days) {
            case 1:
                $record['one_day_retained'] = $retained_num;
                break;
            case 2:
                $record['two_day_retained'] = $retained_num;
                break;
            case 3:
                $record['three_day_retained'] = $retained_num;
                break;
            case 4:
                $record['four_day_retained'] = $retained_num;
                break;
            case 5:
                $record['five_day_retained'] = $retained_num;
                break;
            case 6:
                $record['six_day_retained'] = $retained_num;
                break;
            case 7:
                $record['seven_day_retained'] = $retained_num;
                break;
            case 15:
                $record['fifteen_day_retained'] = $retained_num;
                break;
            case 30:
                $record['thirty_day_retained'] = $retained_num;
                break;
            default :
                // 未定义
                return array('status'=>false, 'msg'=>'未定义日留存类型'.$many_days);
        }
        $record['update_time'] = sprintf("%.3f", microtime(true));
        $msg = '';
        if (isset($record['id']) && !empty($record['id'])) {
            $res = $this->retainedModel->UpdateData($record, ['id'=>$record['id'], 'day'=>$r_day]);
            $msg = "日期为【" . $r_day . "】的日留存记录更新失败, data:" . json_encode($record);
        } else {
            $res = $this->retainedModel->AddData($record);
            $msg = "日期为【" . $r_day . "】的日留存记录新增失败, data:" . json_encode($record);
        }
        if (!$res) {
            return array('status'=>false, 'msg'=>$msg);
        } else {
            return array('status'=>true);
        }
        
    }
    
    /**
     * 统计关于某日充值影响的日留存信息  已存在则更新, 反之则新增
     * 即统计$day当天充值影响的前几日对应的日留存
     * @param type $day  Y-m-d
     * @return type
     */
    public function recordRechargeRetainedAboutOneDay($day) {
        $msg = "";
        $status = true; 
        try {
            // 获取当日充值的用户id数组
            $recharge_user_num = $this->countRechargeUserAboutOneDay($day);
            // 判断是否存在用户充值 当日有用户充值才可能存在日留存
//            if ($recharge_user_num <= 0) {
//                $msg = "日期为【" . $day . "】不存在充值";
//            } else {
//            }
            // 获取$day日期的日留存数据
            $record = $this->retainedModel->getDailyRechargeRetainedByDay($day, 'id, day');
            if (empty($record)) {
                // 更新$day日期的充值人数
                $record = [
                    'day' => $day,
                ];
            }
            $record['recharge_user_num'] = $recharge_user_num;
            $record['update_time'] = sprintf("%.3f", microtime(true));
            if (isset($record['id']) && !empty($record['id'])) {
                $res = $this->retainedModel->UpdateData($record, ['id' => $record['id']]);
            } else {
                $res = $this->retainedModel->AddData($record);
            }
            if (!$res) {
                throw new \Exception("日期为【" . $day . "】的日留存记录保存失败");
            }

            // 次日留存
            $one_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 1);
            if (!$one_day_retained_res['status']) {
                throw new \Exception($one_day_retained_res['msg']);
            }
            // 2日留存
            $two_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 2);
            if (!$two_day_retained_res['status']) {
                throw new \Exception($two_day_retained_res['msg']);
            }
            // 3日留存
            $three_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 3);
            if (!$three_day_retained_res['status']) {
                throw new \Exception($three_day_retained_res['msg']);
            }
            // 4日留存
            $four_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 4);
            if (!$four_day_retained_res['status']) {
                throw new \Exception($four_day_retained_res['msg']);
            }
            // 5日留存
            $five_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 5);
            if (!$five_day_retained_res['status']) {
                throw new \Exception($five_day_retained_res['msg']);
            }
            // 6日留存
            $six_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 6);
            if (!$six_day_retained_res['status']) {
                throw new \Exception($six_day_retained_res['msg']);
            }
            // 7日留存
            $seven_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 7);
            if (!$seven_day_retained_res['status']) {
                throw new \Exception($seven_day_retained_res['msg']);
            }
            // 15日留存
            $fifteen_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 15);
            if (!$fifteen_day_retained_res['status']) {
                throw new \Exception($fifteen_day_retained_res['msg']);
            }
            // 30日留存
            $thirty_day_retained_res = $this->countRechargeRetainedAboutDayRange($day, 30);
            if (!$thirty_day_retained_res['status']) {
                throw new \Exception($thirty_day_retained_res['msg']);
            }
        } catch (\Exception $ex) {
            $status = FALSE;
            $msg = $ex->getMessage();
        }
        return array('status'=>$status, 'msg'=>$msg);
    }
    
    /**
     * 记录某个时间范围内充值 每个日期的充值数所影响的对应日留存记录
     * 例 $start = 27号  更新  26号的次留存 25号的2日留存  24号的3日留存 ...
     * @param string $start_date
     * @param string $end_date
     * @return type
     */
    public function recordRechargeRetainedInDayRange($start_date, $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $res = array('status'=>true, 'msg'=>'');
        while ($start <= $end) {
            $day = date('Y-m-d', $start);
            // $start变更为下一个日期
            $start = strtotime('+1 day', $start);
            $res = $this->recordRechargeRetainedAboutOneDay($day);
            if (!$res['status']) {
                break;
            }
        }
        return $res;
    }
    
    public function rechargeRetainedDailyRefresh() {
        // 当天日期
        $c_date = date('Y-m-d', time());
        
        // 获取当天日期的日况统计记录
        $ds_record = $this->retainedModel->getDailyRechargeRetainedByDay($c_date);
        // 获取10分钟前的时间值 如果当日统计的时间小于该时间则需要重新统计
        $c_time = strtotime("-10 minute", sysTime());
        if (!empty($ds_record) && ($ds_record['update_time'] >= $c_time)) { 
            // 已生成当天日况则根据所获取记录的update_time看是否需要更新当日统计数据
            return array('status'=>TRUE, 'msg'=>'');
        } else {
            // 更新今天的充值影响的对应日期的对应日留存
            return $this->recordRechargeRetainedAboutOneDay($c_date);
        }
    }
}
