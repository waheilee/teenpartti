<?php

namespace app\api\controller;

use app\model\AccountDB;
use app\model\BankDB;
use app\model\DataChangelogsDB;
use XLSXWriter;

class Export
{
    /**
     * @param $filename string 表格文件名称
     * @param $header_types array 表头定义
     * @param $rows         array 记录集 二维数组
     * @param $returnObj bool 是否自行处理行输出
     * @param int[] $width
     * @return XLSXWriter
     */
    public function GetExcel($filename, $header_types, &$rows, $returnObj = false, $width = [20, 20, 20, 20, 20, 20, 20, 20, 20, 20])
    {
        ob_clean();
        $writer = new XLSXWriter();
        // header
        if (1) {
            header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename("$filename.xlsx") . '"');
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        }
        /*每列的标题头
        $header_types = array(' 开单时间 ' => 'string', ' 收款时间 ' => 'string', ' 开票项目 ' => 'string',
            ' 票据编号 ' => 'string', ' 客户名称 ' => 'string', ' 实收金额 ' => '0.00', ' 收款方式 ' => 'string', ' 收款人 ' => 'string',
        );*/
        $sheet1 = 'sheet1'; //表名
        /* 设置标题头，指定样式 */
        $styles1 = array('freeze_rows' => 1, 'font' => ' 宋体 ', 'font-size' => 10, 'font-style' => 'bold', 'fill' => '#fff',
            'halign' => 'center', 'border' => 'left,right,top,bottom', 'widths' => $width);
        $writer->writeSheetHeader($sheet1, $header_types, $styles1);
        // 最后是数据，foreach 写入
        $styles2 = ['height' => 16, 'halign' => 'center',];
        if ($returnObj) {
            return $writer;
        } else {
            foreach ($rows as $index => &$row) {
                $writer->writeSheetRow($sheet1, $row, $styles2);
                unset($rows[$index]);
            }
        }

        unset($row);
        $writer->writeToStdOut();
        exit();
    }


    public function userFirstInfo()
    {
        $field = 'A.AccountID,A.AccountName,A.Mobile As Mobile,
        ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,
        ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,
        ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,
        ISNULL(B.Lv1DepositPlayers,0) AS Lv1DepositPlayers,ISNULL(B.Lv1Tax,0) AS Lv1Tax,
        ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,
        ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2DepositPlayers,0) AS Lv2DepositPlayers,
        ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,
        ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,
        ISNULL(B.Lv3DepositPlayers,0) AS Lv3DepositPlayers,ISNULL(B.Lv3Tax,0) AS Lv3Tax,
        ISNULL(B.Lv3Running,0) AS Lv3Running,ISNULL(B.Lv1WithdrawCount,0) AS Lv1WithdrawCount,
        ISNULL(B.Lv2WithdrawCount,0) AS Lv2WithdrawCount,ISNULL(B.Lv3WithdrawCount,0) AS Lv3WithdrawCount,
        ISNULL(B.Lv1WithdrawAmount,0) AS Lv1WithdrawAmount,ISNULL(B.Lv2WithdrawAmount,0) AS Lv2WithdrawAmount,
        ISNULL(B.Lv3WithdrawAmount,0) AS Lv3WithdrawAmount';

        $allUserList = (new \app\model\AccountDB())->getTableObject('T_Accounts')
            ->alias('A')
            ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) B', 'B.ProxyId=A.AccountID', 'LEFT')
            ->where('Lv1DepositPlayers', '>=', 5)
            ->where('Mobile', '<>', '')
            ->field($field)
            // ->fetchSql(true)
            ->select();
//        dump($data);die();

//        $allUserCache = Redis::get('ALL_USERS');
//        if ($allUserCache) {
//            $allUser = $allUserCache;
//        } else {
            $allUser = (new AccountDB())->getTableObject('T_Accounts')->alias('a')
                ->field('a.AccountID,a.AccountName,a.ProxyId,c.ParentID')
                ->join('[CD_UserDB].[dbo].[T_Role] b', 'b.RoleID = a.AccountID', 'left')
                ->join('[CD_UserDB].[dbo].[T_UserProxyInfo] c', 'c.RoleID = b.RoleID', 'left')
                ->where('a.AccountID', '>', '8889000')
                ->select();
//            Redis::set('ALL_USERS', $allUser, 86400);
//        }


//        $UserTransactionLogsCache =  Redis::get('T_USER_TRANSACTION_LOGS');
//        if ($UserTransactionLogsCache){
//            $depositPerson = $UserTransactionLogsCache;
//        }else{
//            $depositPerson = (new DataChangelogsDB())
//                ->getTableObject('T_UserTransactionLogs')
////                ->limit(10000)
//                ->select();
//            Redis::set('T_USER_TRANSACTION_LOGS', $depositPerson, 86400);
//        }

//        $userDrawbackCache =  Redis::get('USER_DRAWBACK');
//        if ($userDrawbackCache){
//            $drawbackPerson = $userDrawbackCache;
//        }else{
            $drawbackPerson = (new BankDB())
                ->getTableObject('UserDrawBack')
                ->where('IsDrawback',100)
//                ->limit(500)
                ->select();
//            Redis::set('USER_DRAWBACK', $drawbackPerson, 86400);
//        }
//        $msgs = (new DataChangelogsDB())
//            ->getTableObject('T_ProxyMsgLog')
//            ->field('RoleID,addtime')
//            ->order('addtime desc')
//            ->select();
        $data = [];
        foreach ($allUserList as $user) {
            $item = [];
            $item['mobile'] = $user['Mobile'];
            $item['personTotalMoney'] = $user['TotalDeposit'];
            $item['personDrawbackTotalMoney'] = $this->personDrawbackTotalMoney($drawbackPerson,$user);
            $subUser = [];
            foreach ($allUser as $value) {
                if ($value['ParentID'] == $user['AccountID']) {
                    $subUser[] = $value['AccountID'];
                }
            }
            if (empty($subUser)) {
                continue;
            }
//            $firstPlayerDeposit = array_filter((array)$depositPerson, function ($item) use ($subUser) {
//                    return in_array($item['RoleID'], $subUser);
//            });
//            $firstPlayerDepositCount = array_filter($firstPlayerDeposit, function ($item)  {
//                return $item['IfFirstCharge'] == 1;
//            });
//            $totalMoney = array_reduce($firstPlayerDeposit, function ($carry, $item) {
//                // 判断money字段是否存在且为数字
//                if (isset($item['TransMoney']) && is_numeric($item['TransMoney'])) {
//                    // 将当前money值累加到$carry中
//                    $carry += $item['TransMoney'];
//                }
//                return $carry;
//            }, 0);

            $personDrawback = array_filter((array)$drawbackPerson, function ($item) use ($subUser) {
                return in_array($item['AccountID'], $subUser);
//                return $item['AccountID'] == $user['AccountID'];
            });
            $firstPlayerDrawbackTotalMoney = array_reduce($personDrawback, function ($carry, $item) {
                // 判断money字段是否存在且为数字
                if (isset($item['iMoney']) && is_numeric($item['iMoney'])) {
                    // 将当前money值累加到$carry中
                    $carry += $item['iMoney'];
                }
                return $carry;
            }, 0);
            $msg = (new DataChangelogsDB())
                ->getTableObject('T_ProxyMsgLog')
                ->field('RoleID,addtime')
                ->where('RoleID',$user['AccountID'])
                ->order('addtime desc')
                ->find();
            $addTime = '';
            if ($msg){
                $addTime = $msg['addtime'];
            }
            $item['msgTime'] = $addTime;
            $item['firstPlayerCount'] = $user['Lv1PersonCount'];// count($subUser);
            $item['firstPlayerDepositCount'] = $user['Lv1DepositPlayers'];// count($firstPlayerDepositCount);
            $item['firstPlayerDepositSum'] = $user['Lv1Deposit'];// $totalMoney;
            $item['firstPlayerDrawbackSum'] = FormatMoney($firstPlayerDrawbackTotalMoney);
            $data[] = $item;
        }
        dump($data);die();
        $header_types = [
            lang('手机号码') => 'string',
            lang('个人充值') => 'string',
            lang('首次邮件时间') => 'string',
            lang('个人提现') => "string",
            lang('1级玩家数') => 'string',
            lang('1级充值数') => "string",
            lang('1级充值总金额') => "string",
            lang('1级出款总金额') => "string",
        ];
        $filename = lang('用户信息数据表') . '-' . date('YmdHis');
        $rows =& $data;

        $writer = $this->GetExcel($filename, $header_types, $rows, true);
        foreach ($rows as $index => &$row) {
            $item = [
                $row['mobile'],
                $row['personTotalMoney'],
                $row['msgTime'],
                $row['personDrawbackTotalMoney'],
                $row['firstPlayerCount'],
                $row['firstPlayerDepositCount'],
                $row['firstPlayerDepositSum'],
                $row['firstPlayerDrawbackSum']
            ];
            $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
            unset($rows[$index]);
        }
        unset($row, $item);
        $writer->writeToStdOut();
        exit();


    }

    public function personTotalMoney($depositPerson,$user)
    {
        $personDeposit = array_filter($depositPerson, function ($item) use ($user) {
            return $item['RoleID'] == $user['AccountID'];
        });
        return array_reduce($personDeposit, function ($carry, $item) {
            // 判断money字段是否存在且为数字
            if (isset($item['TransMoney']) && is_numeric($item['TransMoney'])) {
                // 将当前money值累加到$carry中
                $carry += $item['TransMoney'];
            }
            return $carry;
        }, 0);
    }

    public function personDrawbackTotalMoney($drawbackPerson,$user)
    {
        $personDrawback = array_filter($drawbackPerson, function ($item) use ($user) {
            return $item['AccountID'] == $user['AccountID'];
        });
        $personDrawbackTotalMoney = array_reduce($personDrawback, function ($carry, $item) {
            // 判断money字段是否存在且为数字
            if (isset($item['iMoney']) && is_numeric($item['iMoney'])) {
                // 将当前money值累加到$carry中
                $carry += $item['iMoney'];
            }
            return $carry;
        }, 0);
        return FormatMoney($personDrawbackTotalMoney);
    }




}

