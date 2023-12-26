<?php

namespace app\admin\controller\Export;

use app\admin\controller\Main;
use app\model\GameOCDB;
use think\Exception;
use XLSXWriter;
class AgentWaterDailyExport extends Main
{

    public function export()
    {
        $writer = new XLSXWriter();
        $startDate = input('start', date('Y-m-d', time()));
        $endDate = input('end', date('Y-m-d', time()));
        $roleId = input('roleid');

        $dateRange = range(strtotime($startDate), strtotime($endDate), 86400); // 86400 秒 = 1 天

        $datesArray = array_map(function ($timestamp) {
            return date('Ymd', $timestamp);
        }, $dateRange);

        $rows = $this->getExcelData($datesArray,$roleId);

        $writer->writeSheet($rows);
        $filename = lang('代理明细') . '-' . date('YmdHis');

        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename("$filename.xlsx") . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

// 将 Excel 文件输出到浏览器
        $writer->writeToStdOut();
        exit();
    }

    public function getExcelData($datesArray,$roleId)
    {
//        $data = [];
        $data[] = [
            lang('日期'),
            lang('代理ID'),
            lang('1+2级打码'),
            lang('个人总收益'),
            lang('个人充值'),
            lang('个人流水'),
            lang('一级人数'),
            lang('一级充值'),
            lang('一级流水'),
            lang('二级人数'),
            lang('二级充值'),
            lang('二级流水'),
            lang('三级人数'),
            lang('三级充值'),
            lang('三级流水'),
            lang('一级首充金额'),
            lang('二级首充金额'),
            lang('三级首充金额'),
            lang('一级提现金额'),
        ];
        foreach($datesArray as $day){
            $sourceData = $this->getDayData($day,$roleId);;
            foreach ($sourceData as $dayData){
                $lv1rate = config('agent_running_parent_rate')[1];
                $lv2rate = config('agent_running_parent_rate')[2];
                $lv3rate = config('agent_running_parent_rate')[3];
                $Lv1Reward = bcmul($dayData['Lv1Running'], $lv1rate, 4);
                $Lv2Reward = bcmul($dayData['Lv2Running'], $lv2rate, 4);
                $Lv3Reward = bcmul($dayData['Lv3Running'], $lv3rate, 4);
                $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
                $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
                $dayData['RewardAmount'] = $rewar_amount;


                $item['AddTime'] = $dayData['AddTime'];
                $item['ProxyId'] = $dayData['ProxyId'];
                $item['ReceivedIncome'] = $rewar_amount;
                $item['DailyDeposit'] = $dayData['DailyDeposit'];
                $item['DailyRunning'] = FormatMoney($dayData['DailyRunning']);
                $item['Lv1PersonCount'] = $dayData['Lv1PersonCount'];
                $item['Lv1Deposit'] = $dayData['Lv1Deposit'];
                $item['Lv1Running'] = FormatMoney($dayData['Lv1Running']);
                $item['Lv2PersonCount'] = $dayData['Lv2PersonCount'];
                $item['Lv2Deposit'] = $dayData['Lv2Deposit'];
                $item['Lv2Running'] = FormatMoney($dayData['Lv2Running']);
                $item['Lv3PersonCount'] = $dayData['Lv3PersonCount'];
                $item['Lv3Deposit'] = $dayData['Lv3Deposit'];
                $item['Lv3Running'] = FormatMoney($dayData['Lv3Running']);
                $item['Lv1FirstDepositMoney'] = 0;
                $item['Lv2FirstDepositMoney'] = 0;
                $item['Lv3FirstDepositMoney'] = 0;
                $item['Lv1WithdrawalMoney'] = 0;
                if(config('AgentWaterDaily') == 1){
                    $item['Lv1FirstDepositMoney'] = FormatMoney($dayData['Lv1FirstDepositMoney']);
                    $item['Lv2FirstDepositMoney'] = FormatMoney($dayData['Lv2FirstDepositMoney']);
                    $item['Lv3FirstDepositMoney'] = FormatMoney($dayData['Lv3FirstDepositMoney']);
                    $item['Lv1WithdrawalMoney'] = FormatMoney($dayData['Lv1WithdrawalMoney']);

                }
                $item['dm'] = bcadd($item['Lv1Running'], $item['Lv2Running'],3);
                //团队打码
                $data[] = $item;
            }


        }
        return $data;

    }


    public function getDayData($day,$roleId)
    {
        $gameOCDB = new GameOCDB();
        return $gameOCDB->getTableObject('T_ProxyDailyCollectData_'.$day)->alias('a')
            ->join('[CD_UserDB].[dbo].[T_UserProxyInfo](NOLOCK) b', 'a.ProxyId=b.RoleID', 'LEFT')
            ->join('[T_UserDailyDeposit](NOLOCK) c', $day.'=c.DayTime and a.ProxyId=c.RoleID', 'LEFT')
            ->field('AddTime,ProxyId,DailyDeposit,DailyTax,
        DailyRunning,Lv1PersonCount,Lv1Deposit,Lv1Tax,Lv1Running,
        Lv2PersonCount,Lv2Deposit,Lv2Tax,Lv2Running,Lv3PersonCount,
        Lv3Deposit,Lv3Tax,Lv3Running,Lv1FirstDepositPlayers,
        Lv2FirstDepositPlayers,Lv3FirstDepositPlayers,A.ValidInviteCount,
        Lv2ValidInviteCount,Lv3ValidInviteCount,FirstDepositMoney,
        c.Lv1FirstDepositMoney,c.Lv2FirstDepositMoney,c.Lv3FirstDepositMoney,
        c.Lv1WithdrawalMoney,c.Lv2WithdrawalMoney,c.Lv3WithdrawalMoney')
            ->where(function ($q) use($roleId){
                if (!empty($roleId)){
                    $q->where('a.ProxyId',$roleId);
                }
            })
            ->select();

    }
}