<?php

namespace app\admin\controller;
use think\Db;
class DailyStatistics extends BaseController {
    
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 日况列表
     * @return type
     */
    public function Index() {
        if ($this->request->isAjax()) {
            
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            
            $filter = ' where 1=1 ';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and day >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and day < \'' . $end . ' \'';
            }
            $m = new \app\model\DailyStatisticsModel();
            $count = $m->getTotal($filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            //加充值人数统计
            $where2 = '1=1';
            if($start){
                $where2 .= ' AND mydate>=\''.$start.'\'';
            }
            if($end){
                $where2 .= ' AND mydate<\''.$end.'\'';
            }
            $recharge_num = (new \app\model\GameOCDB())->getTableObject('T_GameStatisticPay')->where($where2)->column('totaluserpaynum','mydate');
            $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
            foreach ($list as $key => &$val) {
                $val['recharge_num'] = $recharge_num[$val['day']]['totaluserpaynum'] ?? 0;
            }
            return $this->toDataGrid($count, $list);
        }
        // 访问刷新
        $lib = new \app\admin\lib\DailyStatisticsLib();
        $res = $lib->statisticsDailyRefresh();
        $this->assign('daily_update', json_encode($res, true));
        
        $this->assign('bl', bl);
        return $this->fetch();
    }
    
    /**
     * 导出日况统计概要
     */
    public function exportDailyStatistics(){
        $bl = bl;
        $header_types = [
            lang('日期') => 'string',
            lang('金币历史库存') => 'string',
            lang('平台RTP(%)') => "string",
            lang('游戏RTP(%)') => "string",
            lang('总充值') => 'string',
            lang('总提现') => "string",
            lang('总盈利') => "string",
            lang('平台盈亏') => "string",
            lang('免费金币产出') => "string",
            lang('游戏金币产出') => "string",
            lang('彩金产出') => "string",
            lang('lang(z邮件补偿金币') => "string",
            lang('购买免费有效消耗') => "string",
            lang('总消耗') => 'string',
            lang('活跃用户') => "integer",
            lang('新增用户') => "integer",
            lang('平均在线时长(s)') => "integer",
            lang('充值人数') => "integer",
            lang('首充人数') => 'integer',
            lang('首充金额')=> 'integer',
            lang('更新时间') => "string"
        ];
        $start = $this->request->get('start') ? $this->request->get('start') : '';
        $end = $this->request->get('end') ? $this->request->get('end') : '';

        $filter = ' where 1=1 ';
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $filter .= ' and day >= \'' . $start . '\'';
        }
        if (!empty($end)) {
            $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
            $filter .= ' and day < \'' . $end . ' \'';
        }
        $m = new \app\model\DailyStatisticsModel();
        $list = $m->getDataList(1, 999, $filter, '', "day desc");
        //加充值人数统计
        $where2 = '1=1';
        if($start){
            $where2 .= ' AND mydate>=\''.$start.'\'';
        }
        if($end){
            $where2 .= ' AND mydate<\''.$end.'\'';
        }
        $recharge_num = (new \app\model\GameOCDB())->getTableObject('T_GameStatisticPay')->where($where2)->column('totaluserpaynum','mydate');
        $decimal = 3;
        $data = [];
        if (!empty($list)) {
            foreach ($list as $v) {
                $item = [
                    'day' => $v['day'],
                    'gold_coins_historical_stock' => !empty($v['gold_coins_historical_stock'])? $this->formatDecimal($v['gold_coins_historical_stock'], $decimal, $bl): '',
                    'platform_rtp' => $this->formatDecimal($v['platform_rtp']*100, 2),
                    'game_rtp' => $this->formatDecimal($v['game_rtp']*100, 2),
                    'total_recharge' => $this->formatDecimal($v['total_recharge'], $decimal, $bl),
                    'total_withdrawal' => $this->formatDecimal($v['total_withdrawal'], $decimal, $bl),
                    'total_profit' => $this->formatDecimal($v['total_profit'], $decimal, $bl),
                    'platform_profit_and_loss' => $this->formatDecimal($v['platform_profit_and_loss'], $decimal, $bl),
                    'free_gold_coin_output' => $this->formatDecimal($v['free_gold_coin_output'], $decimal, $bl),
                    'game_gold_coin_output' => $this->formatDecimal($v['game_gold_coin_output'], $decimal, $bl),
                    'colored_gold_coin_output' => $this->formatDecimal($v['colored_gold_coin_output'], $decimal, $bl),
                    'mail_extra_gift_coins' => $this->formatDecimal($v['mail_extra_gift_coins'], $decimal, $bl),
                    'free_game_consumption' => $this->formatDecimal($v['free_game_consumption'], $decimal, $bl),
                    'total_gold_consumption' => $this->formatDecimal($v['total_gold_consumption'], $decimal, $bl),
                    'active_users' => $v['active_users'],
                    'new_users' => $v['new_users'],
                    'average_online_time' => $v['average_online_time'],
                    'recharge_num' => $recharge_num[$v['day']]['totaluserpaynum'] ?? 0,
                    'first_charge_num' => $v['first_charge_num'],
                    'first_charge_amount' =>  $this->formatDecimal($v['first_charge_amount'], $decimal, $bl),
                    'update_time' => $v['update_time']
                ];
                array_push($data, $item);
            }
        }
        $filename = lang('日况统计概要').'-' . date('YmdHis');
        $this->GetExcel($filename, $header_types, $data);
    }

    
    /**
     * 充值留存日统计列表
     */
    public function RechargeRetained() {
        if ($this->request->isAjax()) {
            
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            
            $filter = ' where 1=1 ';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and day >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and day < \'' . $end . ' \'';
            }
            $m = new \app\model\RechargeRetainedModel();
            $count = $m->getTotal($filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
            return $this->toDataGrid($count, $list);
        }
        // 访问刷新 间隔10分钟更新充值留存
        $lib = new \app\admin\lib\RetainedLib();
        $res = $lib->rechargeRetainedDailyRefresh();
        $this->assign('daily_update', json_encode($res, true));
        
        return $this->fetch();
    }
    
    /**
     * 日留存导出excel时格式化留存率
     * @param type $day_retained
     * @param type $recharge_num
     * @return string
     */
    public function getExportReatined($day_retained, $recharge_num) {
        if (empty($day_retained) || empty($recharge_num)) {
            return '';
        } else {
            return $day_retained . ' / ' . $this->formatDecimal($day_retained / $recharge_num * 100, 2) . '%';
        }
    }
    /**
     * 导出充值留存
     */
    public function exportRechargeRetained(){
        $header_types = [
            lang('日期') => 'string',
            lang('充值人数') => 'integer',
            lang('次日留存') => "string",
            lang('2日留存') => 'string',
            lang('3日留存') => "string",
            lang('4日留存') => "string",
            lang('5日留存') => "string",
            lang('6日留存') => "string",
            lang('7日留存') => "string",
            lang('15日留存') => "string",
            lang('30日留存') => "string",
            lang('更新时间') => "string",
        ];
        $start = $this->request->get('start') ? $this->request->get('start') : '';
        $end = $this->request->get('end') ? $this->request->get('end') : '';

        $filter = ' where 1=1 ';
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $filter .= ' and day >= \'' . $start . '\'';
        }
        if (!empty($end)) {
            $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
            $filter .= ' and day < \'' . $end . ' \'';
        }
        $m = new \app\model\RechargeRetainedModel();
        $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
        $data = [];
        if (!empty($list)) {
            foreach ($list as $v) {
                $item = [
                    'day' => $v['day'],
                    'recharge_user_num' => $v['recharge_user_num'],
                    'one_day_retained' => $this->getExportReatined($v['one_day_retained'], $v['recharge_user_num']),
                    'two_day_retained' => $this->getExportReatined($v['two_day_retained'], $v['recharge_user_num']),
                    'three_day_retained' => $this->getExportReatined($v['three_day_retained'], $v['recharge_user_num']),
                    'four_day_retained' => $this->getExportReatined($v['four_day_retained'], $v['recharge_user_num']),
                    'five_day_retained' => $this->getExportReatined($v['five_day_retained'], $v['recharge_user_num']),
                    'six_day_retained' => $this->getExportReatined($v['six_day_retained'], $v['recharge_user_num']),
                    'seven_day_retained' => $this->getExportReatined($v['seven_day_retained'], $v['recharge_user_num']),
                    'fifteen_day_retained' => $this->getExportReatined($v['fifteen_day_retained'], $v['recharge_user_num']),
                    'thirty_day_retained' => $this->getExportReatined($v['thirty_day_retained'], $v['recharge_user_num']),
                    'update_time' => date('Y-m-d H:i:s', $v['update_time']),
                ];
                array_push($data, $item);
            }
        }
        $filename = lang('充值留存').'-' . date('YmdHis');
        $this->GetExcel($filename, $header_types, $data);
    }
    
    /**
     * 免费游戏购买日统计列表
     * @return type
     */
    public function BuyFreeGameDailyCount() {
        if ($this->request->isAjax()) {
            
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            
            $filter = ' where 1=1 ';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and day >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and day < \'' . $end . ' \'';
            }
            $m = new \app\model\BuyFreeGameDailyCountModel();
            $count = $m->getTotal($filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
            return $this->toDataGrid($count, $list);
        }
        // 访问刷新
        $lib = new \app\admin\lib\BuyFreeGameDailyCountLib();
        $res = $lib->buyFreeGameDailyCountRefresh();
        $this->assign('daily_update', json_encode($res, true));
        $this->assign('bl', bl);
        
        return $this->fetch();
    }
    
    /**
     * 导出购买免费游戏日统计
     */
    public function exportBuyFreeGameDailyCount() {
        $bl = bl;
        $header_types = [
            lang('日期') => 'string',
            'RTP(%)' => 'string',
            lang('总局数') => "string",
            lang('总消耗') => 'string',
            lang('总产出') => "string",
            lang('总盈亏') => "string",
            lang('更新时间') => "string",
        ];
        $start = $this->request->get('start') ? $this->request->get('start') : '';
        $end = $this->request->get('end') ? $this->request->get('end') : '';

        $filter = ' where 1=1 ';
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $filter .= ' and day >= \'' . $start . '\'';
        }
        if (!empty($end)) {
            $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
            $filter .= ' and day < \'' . $end . ' \'';
        }
        $m = new \app\model\BuyFreeGameDailyCountModel();
        $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
        $data = [];
        $decimal = 3;
        if (!empty($list)) {
            foreach ($list as $v) {
                $item = [
                    'day' => $v['day'],
                    'rtp' => $this->formatDecimal($v['rtp']*100, 2),
                    'total_games' => $v['total_games'],
                    'total_consumption' => $this->formatDecimal($v['total_consumption'], $decimal, $bl),
                    'total_output' => $this->formatDecimal($v['total_output'], $decimal, $bl),
                    'total_profit_and_loss' => $this->formatDecimal($v['total_profit_and_loss'], $decimal, $bl),
                    'update_time' => date('Y-m-d H:i:s', $v['update_time']),
                ];
                array_push($data, $item);
            }
        }
        $filename = lang('购买免费游戏日统计').'-' . date('YmdHis');
        $this->GetExcel($filename, $header_types, $data);
    }


    /*
    日况数据修复
     */
    public function dataFix(){
        $GameOCDB = new \app\model\GameOCDB();
        $data = Db::table('game_daily_statistics')->where('id','>',0)->select();
        foreach ($data as $key => &$val) {
            $tax = $GameOCDB->setTable('T_GameStatisticTotal')->getValueByTable('mydate=\''.$val['day'].'\'','totaltax')?:0;
            if ($val['total_gold_consumption'] <= 0) {
                $game_rtp = $platform_rtp = 0;
            } else {
                $game_rtp = ($val['game_gold_coin_output'] + $val['colored_gold_coin_output']) / $val['total_gold_consumption'];
                $platform_rtp = ($val['game_gold_coin_output'] + $val['free_gold_coin_output'] + $val['colored_gold_coin_output'] - $tax) / $val['total_gold_consumption'];
            }
            Db::table('game_daily_statistics')->where('id',$val['id'])->update([
                'tax'=>$tax,
                'platform_profit_and_loss'=>$val['total_gold_consumption'] + $tax - ($val['game_gold_coin_output'] + $val['free_gold_coin_output'] + $val['colored_gold_coin_output']),
                'game_rtp'=>$game_rtp,
                'platform_rtp'=>$platform_rtp
            ]);
        }
    }
}
