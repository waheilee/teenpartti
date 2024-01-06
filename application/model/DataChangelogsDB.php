<?php


namespace app\model;


use think\exception\PDOException;

class DataChangelogsDB extends BaseModel
{


    private $transactionlog ='T_UserTransactionLogs';
    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '')
    {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->DataChangelogsDB);
    }

    /**
     * 系统邮件表
     */
    public function TProxyMsgLog()
    {
        $this->table = 'T_ProxyMsgLog';
        return $this;
    }



    public function GetEmailList()
    {
        $mailtype = config('mailtype');
        $extratype = config('extratype');

        $this->table = 'T_ProxyMsgLog';
        $RoleId = input('RoleId', -1);
        $VerifyState = input('VerifyState', -1);
        $ExtraType = input('ExtraType', -1);

        $Title = input('Title');
        $AmountMin = input('AmountMin');
        $AmountMax = input('AmountMax');
        $start = input('start');
        $end = input('end');
        $PayOrder = input('PayOrder');
        $where = "";
        if ($RoleId >= 0 && !empty($RoleId)) $where .= " AND RoleId=$RoleId";
        if ($AmountMin >= 0 && !empty($AmountMin)) $where .= " AND Amount>=" . $AmountMin * bl;
        if ($AmountMax >= 0 && !empty($AmountMax)) $where .= " AND Amount<=" . $AmountMax * bl;
        if (!empty($Title)) $where .= " AND (Title like '%$Title%' or  Notice like '%$Title%') ";
        if ($VerifyState >= 0) $where .= " AND VerifyState=$VerifyState";
        if ($ExtraType >= 0) $where .= " AND ExtraType=$ExtraType";
        if($PayOrder>-1) $where.=" AND RecordType=".$PayOrder;
        if (!empty($start)) $where .= " AND addtime BETWEEN '$start 00:00:00' AND '$end 23:59:59'";
        $db = new DataChangelogsDB();
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and RoleId in(select AccountID FROM [CD_Account].[dbo].[T_Accounts] WITH (NOLOCK) where OperatorId='.session('merchant_OperatorId').')';
        }

        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $where .= ' and RoleId in(select AccountID FROM [CD_Account].[dbo].[T_Accounts] WITH (NOLOCK) where ProxyChannelId='.session('business_ProxyChannelId').')';
        }

        $result = $this->GetPage($where, "id desc");
        if (empty($where)) $where = " AND ExtraType=1 AND VerifyState=1";
        $result['other'] = $this->GetRow("1=1" . $where, "Count(ID)TotalCount,SUM(Amount)TotalMoney");
        if (!empty($result['list'])) {
            foreach ($result['list'] as $k => &$v) {
                if (isset($mailtype[$v['RecordType']])) $v['RecordType'] = $mailtype[$v['RecordType']];
                $v['ExtraType'] = lang($extratype[$v['ExtraType']]);

                $v['RecordType'] = lang($v['RecordType']);


                ConVerMoney($v['Amount']);
                $v['addtime'] = substr($v['addtime'], 0, 19);
                switch ((int)$v['VerifyState']) {
                    case 0:
                        $v['opertext'] = lang('待审核');
                        break;
                    case 1:
                        $v['opertext'] = lang('已审核');
                        break;
                    case 2:
                        $v['opertext'] = lang('作废');
                        break;
                }
//                        $tick = strtotime($v['addtime']) - time();
//                        if ($tick > 0) {
//                            if (substr($v['addtime'], 0, 4) != '2099') $v['isfurture'] = 1;
//                            else  $v['opertext'] = '已撤回';
//                        }
            }
            unset($v);
            ConVerMoney($result['other']['TotalMoney']);
            return $result;
        }
        return [];
    }

    /**
     * PK赛统计报表
     * @param false $robot
     * @return array
     * @throws PDOException
     */
    public function PkMatchDayDataDay($robot = 0)
    {
        $where = "";
        $felid = "*,(JoinFee-WinAward)SysWin,CASE WHEN WinAward>0 THEN  WinAward *1.00/JoinFee*100 ELSE 0.00 END Rtp";
        $start = input('start');
        $end = input('end', date('Y-m-d'));
        if (!empty($start)) $where .= "AND MyDate between '$start' AND '$end'";
        switch ($robot) {
            case 0:
                $this->table = 'View_PKMatchDayALL';
                break;
            case 1:
                $this->table = 'View_PKMatchDayRobot';
                break;
            case 2:
                $this->table = 'View_PKMatchDay';
                break;
        }
        $result = $this->GetPage($where, '', $felid);
        return $result;
    }

    /**
     *PK赛明细报表
     */
    public function PkMatchDataInfo()
    {
        $this->table = 'View_PKMatchlogs';

        $order="MatchPlayTime desc";
        $where = "";
        $RoleID = input('RoleID', 0);
        $start = input('start');
        $end = input('end', date('Y-m-d'));
        if (!empty($start)) $where .= "AND MyDate between '$start' AND '$end'";
        if ($RoleID > 0) $where .= "AND (RoleID1=$RoleID OR RoleID2=$RoleID)";
        $result = $this->GetPage($where,$order);
        $result['other'] = $this->GetRow('1=1 ' . $where, "SUM(TotalJoinFee)TotalJoinFee,SUM(CASE WHEN IsRobotWin=0 THEN WinAward ELSE 0 END)TotalWinAward,SUM(SysWin)TotalWin");
        return $result;
    }


    public function GetFirstCharge($day){
        $this->table = $this->transactionlog;
        $where = " datediff(d,addtime,'$day')=0 and IfFirstCharge=1 ";
        $field = "count(distinct(roleid)) as totalnum, ISNULL(sum(changemoney),0) as amount";
        $data = $this->GetRow($where, $field);
        if (!empty($data)) {
            return $data;
        } else {
            return ['totalnum'=>0,'amount'=>0];
        }
    }


    public function CountDailyIsPayOrderMailExtraGiftCoins($day)
    {
        $this->table = 'T_ProxyMsgLog';
        $where = " datediff(d,'".$day."',addtime)=0 and payorder=1 and VerifyState=1 ";
        $field = "sum(amount) as amount ";
        $data = $this->GetRow($where, $field);
        if (!empty($data) && isset($data['amount'])) {
            return $data['amount'];
        } else {
            return 0;
        }
    }

    public function weekBonusLog(){
        $limit      = request()->param('limit') ?: 15;
        $OperatorId = request()->param('OperatorId');
        $RoleId     = request()->param('RoleId');
        $Status     = request()->param('Status');
        $start_date     = request()->param('start_date');
        $end_date       = request()->param('end_date');
        $orderby        = request()->param('orderby') ?: 'AddTime';
        $ordertype      = request()->param('ordertype') ?: 'desc';

        $where      = '1=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and b.OperatorId='.session('merchant_OperatorId');
        }
        if ($RoleId != '') {
            $where .= ' and a.RoleId='.$RoleId;
        }
        if ($OperatorId != '') {
            $where .= ' and b.OperatorId='.$OperatorId;
        }
        if ($Status != '') {
            $where .= ' and a.Status='.$Status;
        }
        if ($start_date != '') {
            $where .= ' and a.AddTime>=\'' . $start_date . '\'';
        }
        if ($end_date != '') {
            $where .= ' and a.AddTime<\'' . $end_date . '\'';
        }

        $data = $this->getTableObject('[T_WeekBonusLog](NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](nolock) as b','b.AccountID=a.RoleID')
            ->where($where)
            ->field('a.*')
            ->order("$orderby $ordertype")
            ->paginate($limit)
            ->toArray();
        foreach ($data['data'] as $key => &$val) {
            $val['WeekBonus'] = FormatMoney($val['WeekBonus']);
            $val['Lv1Running'] = FormatMoney($val['Lv1Running'] ?? 0);
        }

        $data['other']['hassend'] = $this->getTableObject('[T_WeekBonusLog](NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](nolock) as b','b.AccountID=a.RoleID')
            ->where($where)
            ->where('status',1)
            ->sum('WeekBonus')?:0;
        $data['other']['nosend'] = $this->getTableObject('[T_WeekBonusLog](NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](nolock) as b','b.AccountID=a.RoleID')
            ->where($where)
            ->where('status',0)
            ->sum('WeekBonus')?:0;

        $data['other']['hassend'] = FormatMoney($data['other']['hassend']);
        $data['other']['nosend'] = FormatMoney($data['other']['nosend']);
        return $data;
    }
}