<?php

namespace app\admin\lib;

class BuyFreeGameDailyCountLib {
    
    private $GameOCDB;
    private $model;


    public function __construct() {
        // sql server 数据库
        $this->GameOCDB = new \app\model\GameOCDB();
        $this->model = new \app\model\BuyFreeGameDailyCountModel();
    }
    
    public function getFreeGameBuyTimes($table) {
        $sql = 'SELECT count(ID) as count FROM ' . $table . ' where RoundIndex=1';
        $data = $this->GameOCDB->DBOriginQuery($sql);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }
    
    /**
     * 获取免费购买有效的消耗
     * @param type $table
     * @param type $type
     * @return type
     */
    private function countDailyFreeGameConsumption($table, $type) {
        $fields = " ISNULL(sum(Money), 0) as count ";
        $filter = '  WHERE ChangeType = ' . $type;
        $data = $this->GameOCDB->DBQuery($table, $fields, $filter);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }
    
    /**
     * 统计某天免费购买游戏统计
     * @param type $day
     */
    public function recordBuyFreeGameDailyCountAboutOneDay($table, $day) {
        $dsModel = new \app\model\DailyStatisticsModel();
        $msg = "";
        $status = true; 
        try {
            // 获取$day日期的记录
            $record = $this->model->getDailyCountByDay($day, 'id, day');
            if (empty($record)) {
                $record = [
                    'day' => $day,
                ];
            }
            // 总局数 总购买次数统计roundindex=1的
            $record['total_games'] = $this->getFreeGameBuyTimes($table);
            
            // 总产出是RoundResult，实际盈亏两个相减，
            $fields = " ISNULL(sum(RoundResult), 0) as total_output";
            $count_data = $this->GameOCDB->DBQuery($table, $fields);
            // 总产出 
            $record['total_output'] = $count_data[0]['total_output'];
            
            // 总消耗 
            $table1 = 'T_BankWeathChangeLog_' . date('Ymd', strtotime($day));
            if (!$this->GameOCDB->IsExistTable($table1)) {
                // 判断表是否存在
                $record['total_consumption'] = 0;
            } else {
                $record['total_consumption'] = abs($this->countDailyFreeGameConsumption($table1, $dsModel::SBWCT_BUY_FREE_PROP));
            }

            // 总盈亏 = 消耗 - 产出
            $record['total_profit_and_loss'] = $record['total_consumption'] - $record['total_output'];
            // rtp = 产出 / 消耗
            if ($record['total_consumption'] > 0) {
                $record['rtp'] = $record['total_output'] / $record['total_consumption'];
            } else {
                $record['rtp'] = 0;
            }
            
            $record['update_time'] = sprintf("%.3f", microtime(true));
            if (isset($record['id']) && !empty($record['id'])) {
                $res = $this->model->UpdateData($record, ['id' => $record['id']]);
            } else {
                $res = $this->model->AddData($record);
            }
            if (!$res) {
                throw new \Exception("日期为【" . $day . "】的记录保存失败");
            }
        } catch (\Exception $ex) {
            $status = FALSE;
            $msg = $ex->getMessage();
        }
        return array('status'=>$status, 'msg'=>$msg);
    }
    
    /**
     * 更新某个日期范围内的每日免费购买游戏日统计数据记录
     * @param type $day
     */
    public function updateBuyFreeGameDailyCountInDayRange($start_date, $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $res = array('status'=>true, 'msg'=>'');
            
        $table_pre = 'T_UserGameWipeoutLogs_';
        while ($start <= $end) { 
            // 日期后缀
            $table_suffix = date('Ymd', $start);
            $day = date('Y-m-d', $start);
            // $start变更为下一个日期
            $start = strtotime('+1 day', $start);
            $table = $table_pre . $table_suffix;
            // 判断表是否存在
            if (!$this->GameOCDB->IsExistTable($table)) {
                // 表不存在则进行下一个日期
                continue;
            }
            $res = $this->recordBuyFreeGameDailyCountAboutOneDay($table, $day);
            if (!$res['status']) {
                break;
            }
        }
        return $res;
    }
    
    /**
     * 免费游戏购买日统计刷新
     */
    public function buyFreeGameDailyCountRefresh() {
        // 当天日期
        $c_date = date('Y-m-d', time());
        // 获取当天日期的统计记录
        $record = $this->model->getDailyCountByDay($c_date);
        // 获取10分钟前的时间值 如果当日统计的时间小于该时间则需要重新统计
        $c_time = strtotime("-10 minute", sysTime());
        if (!empty($record) && ($record['update_time'] >= $c_time)) { 
            // 已生成当天的统计记录则根据所获取记录的update_time看是否需要更新当日统计数据
            return array('status'=>TRUE, 'msg'=>'');
        } else {
            // 更新昨天和今天的统计记录
            $start_date = date('Y-m-d', strtotime('-1 day', sysTime()));
            $end_date = date('Ymd', time());
            return $this->updateBuyFreeGameDailyCountInDayRange($start_date, $end_date);
        }
    }
    
}