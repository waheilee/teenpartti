<?php

namespace app\merchant\controller;
use think\Db;
use app\model\GameOCDB;
use app\model\OperatorDailyReport;
class DailyStatistics extends Main {

    /**
     * 日况列表
     * @return type
     */

    public function Index(){


        $operator_id =session('merchant_OperatorId');
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $operator =new OperatorDailyReport();

            $filter = 'OperatorId=\''.$operator_id.'\'';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and Date >= \'' . $start . '\'';
                // $filter[]= ['Date','>=',$start];
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and Date <= \'' . $end . '\'';
                // $filter[]= ['Date','>=',$end];
            }
            $list = $operator->getList($filter,$page,$limit,'*',['Date'=>'desc']);
            $count = $operator->getCount($filter);
            return $this->toDataGrid($count, $list);
        }
        $this->assign('bl', bl);
        return $this->fetch();
    }

//    public function Index() {
//        if ($this->request->isAjax()) {
//
//            $start = $this->request->get('start') ? $this->request->get('start') : '';
//            $end = $this->request->get('end') ? $this->request->get('end') : '';
//
//            $filter = ' where 1=1 ';
//            if (!empty($start)) {
//                $start = date('Y-m-d 00:00:00', strtotime($start));
//                $filter .= ' and day >= \'' . $start . '\'';
//            }
//            if (!empty($end)) {
//                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
//                $filter .= ' and day < \'' . $end . ' \'';
//            }
//            $m = ne();
//            $count = $m->getTotal($filter);
//            if (!$count) {
//                return $this->toDataGrid($count);
//            }
//            $list = $m->getDataList($this->getPageIndex(), $this->getPageLimit(), $filter, '', "day desc");
//            return $this->toDataGrid($count, $list);
//        }
//        // 访问刷新
//        $lib = new \app\admin\lib\DailyStatisticsLib();
//        $res = $lib->statisticsDailyRefresh();
//        $this->assign('daily_update', json_encode($res, true));
//        $this->assign('bl', bl);
//        return $this->fetch();
//    }
    
    /**
     * 导出日况统计概要
     */
    public function exportDailyStatistics(){
        $bl = bl;
        $header_types = [
            lang('日期')           => 'string',
            lang('金币历史库存')       => 'string',
            lang('平台RTP(%)')     => "string",
            lang('游戏RTP(%)')     => "string",
            lang('总充值')          => 'string',
            lang('总提现')          => "string",
            lang('充提差')          => "string",
            lang('游戏盈亏')          => "string",
            lang('税收')          => "string",
            lang('免费金币')       => "string",
            lang('游戏中奖')       => "string",
            lang('代理佣金')          => "string",
            lang('邮件赠送')         => "string",
            lang('充值赠送')         => "string",
            lang('总投注') => "string",
            lang('首充人数')     => "string",
            lang('首充金额')          => 'string',
            lang('活跃用户')         => "integer",
            lang('新增用户')         => "integer",
            lang('更新时间')         => "string"
        ];
        $start = $this->request->get('start') ? $this->request->get('start') : '';
        $end = $this->request->get('end') ? $this->request->get('end') : '';

        $filter = 'OperatorId=\''.session('merchant_OperatorId').'\'';
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $filter .= ' and Date >= \'' . $start . '\'';
        }
        if (!empty($end)) {
            $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
            $filter .= ' and Date <= \'' . $end . '\'';
        }
        $operator =new OperatorDailyReport();
        $list = $operator->getList($filter,1,999,'*',['Date'=>'desc']);

        $decimal = 3;
        $data = [];
        if (!empty($list)) {
            foreach ($list as $v) {
                $item = [
                    'Date' => $v['Date'],
                    'HistoryCoinStock' => !empty($v['HistoryCoinStock'])? $this->formatDecimal($v['HistoryCoinStock'], $decimal, $bl): '',
                    'PlatformRtp' => $this->formatDecimal($v['PlatformRtp']*100, 2),
                    'GameRTP' => $this->formatDecimal($v['GameRTP']*100, 2),
                    'TotalRecharge' => $this->formatDecimal($v['TotalRecharge'], $decimal, $bl),
                    'TotalWithDraw' => $this->formatDecimal($v['TotalWithDraw'], $decimal, $bl),
                    'TotalProfit' => $this->formatDecimal($v['TotalProfit'], $decimal, $bl),
                    'PlatformProfitLoss' => $this->formatDecimal($v['PlatformProfitLoss'], $decimal, $bl),
                    'Tax' => $this->formatDecimal($v['Tax'], $decimal, $bl),
                    'FreeCoinProduct' => $this->formatDecimal($v['FreeCoinProduct'], $decimal, $bl),
                    'GameCoinProduct' => $this->formatDecimal($v['GameCoinProduct'], $decimal, $bl),
                    'AgentReward' => $this->formatDecimal($v['AgentReward'], $decimal, $bl),
                    'MailExtraGiftCoins' => $this->formatDecimal($v['MailExtraGiftCoins'], $decimal, $bl),
                    'DailyActivyAwardCoin' => $this->formatDecimal($v['DailyActivyAwardCoin'], $decimal, $bl),
                    'TotalDayConsume' => $this->formatDecimal($v['TotalDayConsume'], $decimal, $bl),
                    'FirstChargeNum' => $v['FirstChargeNum'],
                    'FirstChargeAmount' =>  $this->formatDecimal($v['FirstChargeAmount'], $decimal, $bl),
                    'ActiveUsers' => $v['ActiveUsers'],
                    'NewUser' => $v['NewUser'],
                    'UpdateTime' => $v['UpdateTime']
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
