<?php


namespace app\model;

use think\Cache;

/**
 * 传统的 model 针对表
 * 这里的 model 针对库
 */
class UserDB extends BaseModel
{
    protected $table = 'T_NickName';
    private $TViewAccountinfo = 'View_Accountinfo';
    private $TViewIndex = 'View_Index';
    private $TOperatorViewIndex = 'View_Operator_Index';
    private $TUserSystemCtrlData = 'T_UserSystemCtrlData';

    const USER_SCAN_ORDER_TRANSACTION = 'T_UserScanTransaction';

    const USER_ACTIVITY_REWARD = 'T_UserActivityReward';
    const USER_ACTIVITY_PROGRESS = 'T_UserActivityProgress';

    const SCAN_ORDER_STATUS_WAIT_AUDIT = 0; // 待审核
    const SCAN_ORDER_STATUS_AUDITED = 1; // 已审核
    const SCAN_ORDER_STATUS_REVERSE_AUDIT = 2; // 反审核
    //
    // UserChannelPayOrder表 Status
    const CHANNEL_ORDER_STATUS_WAIT_PAY = 0; // 未付款
    const CHANNEL_ORDER_STATUS_ORDER_COMPLETED = 1; // 订单完成
    const CHANNEL_ORDER_STATUS_COINS_WAIT_RELEASE = 2; // 金币未发放
    const CHANNEL_ORDER_STATUS_THIRD_PARTY_FAILURE = 3; // 第三方失败
    const CHANNEL_ORDER_STATUS_REJECT_ISSUE_COINS = 4; // 金币拒绝发放


    const ACTIVITY_REWARD_STATUS_WAIT_GRANT = 0; // 待发放
    const ACTIVITY_REWARD_STATUS_GRANTED = 1; // 已发放
    const ACTIVITY_REWARD_STATUS_REJECT_GRANT = 2; // 拒绝发放

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '')
    {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }

    /**首页数据*/
    public function GetIndexData()
    {
        $this->table = $this->TViewIndex;
        $where = null;
        $StartTime = input('strartdate', 0);
        $EndTime = input('enddate');
        if ($StartTime && $EndTime) $where = " AND mydate BETWEEN '$StartTime' AND '$EndTime' ";
        $Filed = "mydate,regnew,highroom,highonline,highroom+highonline as ALLOnline,activenum,totalpayuser,totalpayout,totalpayorder"
            . ",Isnull(DailyRunning,0*1.0) DailyRunning,ISNULL(WinScore,0)totalyk,totaLoginUser,totaLoginCount"
            . ",(totaluserpaynum)TotalPayNumber,totalpayoutnum,GameRate,EmailAmount,GCount,ISNULL(GameUser,0) GameUser,ISNULL(TotalBet,0) RoundBets,TotalWater";

        $result = $this->GetPage($where, "Mydate desc", $Filed);

        //加首充人数
        $where2 = '1=1';
        if ($StartTime && $EndTime) $where = " AND mydate BETWEEN '$StartTime' AND '$EndTime' ";
        $recharge_num = (new \app\model\GameOCDB())->getTableObject('T_GameStatisticPay')->where($where2)->column('first_chargenum', 'mydate');

        foreach ($result['list'] as &$v) {
            $v['GameRate'] = sprintf("%.2f", $v['GameRate']) . "%";
            ConVerMoney($v['RoundBets']);
            ConVerMoney($v['DailyRunning']);
            ConVerMoney($v['totalyk']);
            ConVerMoney($v['totalpayout']);
            ConVerMoney($v['EmailAmount']);
            ConVerMoney($v['TotalWater']);
            $v['first_chargenum'] = $recharge_num[$v['mydate']]['first_chargenum'] ?? 0;
        }
        unset($v);
        //合计数据 TotalReg注册,TotalWater流水,TotalActive活跃,ToalPU充值营收,TotalPay总充值,TotolPayNumber充值人数,TotalOut总提现,TotalOutNum提现人数
        //Totalyk 流水盈亏(金币盈亏-赠送金币)
        $Filed = "SUM(cast(regnew as bigint))TotalReg,sum(cast(DailyRunning as bigint))TotalWater,"
            . "SUM(cast(activenum as bigint))TotalActive,sum(cast(totalpayuser-totalpayout as bigint))ToalPU,"
            . "SUM(cast(totalpayuser as bigint))TotalPay,"
            . "SUM(cast(totaluserpaynum as bigint))TotolPayNumber,"
            . "SUM(cast(totalpayout as bigint))TotalOut,"
            . "SUM(cast(totalpayoutnum as bigint))TotalOutNum,"
            . "SUM(cast(WinScore as bigint))Totalyk,"
            . "SUM(cast(EmailAmount as bigint))TotalEamil,"
            . "(SUM(cast(TotalBet as bigint)) * 1.00 - SUM(cast(WinScore as bigint))) / SUM(cast(CASE WHEN TotalBet>0 THEN TotalBet Else 1 END as bigint)) * 100 TotalGameRate,"
            . "SUM(cast(GCount as bigint)) TotalGCount,"
            . "SUM(cast(ISNULL(GameUser,0) as bigint)) TotalGameUser,"
            . "SUM(cast(ISNULL(TotalWater,0) as bigint)) TotalWage,"
            . "SUM(TotalBet) TotalBet";


        $other = $this->GetRow('', $Filed);
        $other_wage = $this->GetRow(' 1=1 ' . $where, $Filed);
        ConVerMoney($other['TotalWater']);
        ConVerMoney($other['TotalBet']);
        ConVerMoney($other['Totalyk']);
        ConVerMoney($other_wage['TotalWage']);
        $other['TotalGameRate'] = sprintf("%.2f", $other['TotalGameRate']) . "%";
        ConVerMoney($other['TotalOut']);
        ConVerMoney($other_wage['TotalOut']);
        $other['wagedata'] = $other_wage;
        $result['other'] = $other;
        return $result;
    }


    /**渠道首页数据*/
    public function GetOperatorIndexData()
    {
        $this->table = $this->TOperatorViewIndex;
        $operatorid = session('merchant_OperatorId');
        $where = ' AND OperatorId=' . $operatorid;
        $StartTime = input('strartdate', 0);
        $EndTime = input('enddate');
        if ($StartTime && $EndTime) $where .= " AND mydate BETWEEN '$StartTime' AND '$EndTime' ";
        $Filed = "mydate,regnew,highroom,highonline,highroom+highonline as ALLOnline,activenum,totalpayuser,totalpayout,totalpayorder"
            . ",Isnull(DailyRunning,0*1.0) DailyRunning,ISNULL(WinScore,0)totalyk,totaLoginUser,totaLoginCount"
            . ",(totaluserpaynum)TotalPayNumber,totalpayoutnum,GameRate,EmailAmount,GCount,ISNULL(GameUser,0) GameUser,ISNULL(TotalBet,0) RoundBets";

        $result = $this->GetPage($where, "Mydate desc", $Filed);
        foreach ($result['list'] as &$v) {
            $v['GameRate'] = sprintf("%.2f", $v['GameRate']) . "%";
            ConVerMoney($v['DailyRunning']);
            ConVerMoney($v['totalyk']);
            ConVerMoney($v['totalpayout']);
            ConVerMoney($v['EmailAmount']);
            ConVerMoney($v['RoundBets']);
        }
        unset($v);
        //合计数据 TotalReg注册,TotalWater流水,TotalActive活跃,ToalPU充值营收,TotalPay总充值,TotolPayNumber充值人数,TotalOut总提现,TotalOutNum提现人数
        //Totalyk 流水盈亏(金币盈亏-赠送金币)
        $Filed = "SUM(cast(regnew as bigint))TotalReg,sum(cast(DailyRunning as bigint))TotalWater,"
            . "SUM(cast(activenum as bigint))TotalActive,sum(cast(totalpayuser-totalpayout as bigint))ToalPU,"
            . "SUM(cast(totalpayuser as bigint))TotalPay,"
            . "SUM(cast(totaluserpaynum as bigint))TotolPayNumber,"
            . "SUM(cast(totalpayout as bigint))TotalOut,"
            . "SUM(cast(totalpayoutnum as bigint))TotalOutNum,"
            . "SUM(cast(WinScore as bigint))Totalyk,"
            . "SUM(cast(EmailAmount as bigint))TotalEamil,"
            . "(SUM(cast(TotalBet as bigint)) * 1.00 - SUM(cast(WinScore as bigint))) / SUM(cast(CASE WHEN TotalBet>0 THEN TotalBet Else 1 END as bigint)) * 100 TotalGameRate,"
            . "SUM(cast(GCount as bigint)) TotalGCount,"
            . "SUM(cast(ISNULL(GameUser,0) as bigint)) TotalGameUser,"
            . "SUM(TotalBet) TotalBet";


        $other = $this->GetRow(' OperatorId=' . $operatorid, $Filed);
        ConVerMoney($other['TotalWater']);
        ConVerMoney($other['Totalyk']);
        ConVerMoney($other['TotalBet']);
        $other['TotalGameRate'] = sprintf("%.2f", $other['TotalGameRate']) . "%";
        ConVerMoney($other['TotalOut']);
        $result['other'] = $other;
        return $result;
    }


    public function TViewAccount()
    {
        $this->table = $this->TViewAccountinfo;
        return $this;
    }

    public function TViewIndex()
    {
        $this->table = $this->TViewIndex;
        return $this;
    }

    //财富数据表
    public function TUserGameWealth()
    {
        $this->table = 'T_UserGameWealth';
        return $this;
    }

    /** 金币排行*/
    public function GetGoldRanklist(): array
    {
        $this->table = 'T_UserGameWealth(nolock)';
        $orderby = input('orderby', "Money");
        $ordertype = input('ordertype', 'desc');
        $limit = (int)input('limit', 20);
        $from = input('from','');

        $OperatorId = input('OperatorId') ?: '';
        $roleid = input('roleid') ?: '';
        $where = " GMTYPE<>0 ";
        $start_date = input('start_date') ?: '';
        $end_date = input('end_date') ?: '';

        $amount_min = input('amount_min','');
        $amount_max = input('amount_max','');

        $wage_min = input('wage_min','');
        $wage_max = input('wage_max','');

        $avg_min = input('avg_min','');
        $avg_max = input('avg_max','');

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $OperatorId = session('merchant_OperatorId');
        }

        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');
            $where .= " and ProxyChannelId in(".implode(',', $all_ProxyChannelId).")";
        }

        if ($OperatorId !== '') {
            $where .= ' and OperatorId='.$OperatorId;
        }
        if ($roleid !== '') {
            $where .= ' and AccountID='.$roleid;
        }
        if ($start_date != '') {
            $where .= ' and RegisterTime>=\'' . $start_date . '\'';
        }
        if ($end_date != '') {
            $where .= ' and RegisterTime<\'' . $end_date . '\'';
        }

        if(trim($amount_min)!=''){
            if(is_numeric($amount_min)){
                $where .=' and  money>='.$amount_min*bl;
            }
        }

        if(trim($amount_max)!=''){
            if(is_numeric($amount_max)){
                $where .=' and money<='.$amount_max*bl;
            }
        }
        if (strtolower(request()->action())  == 'wagetasklist') {
//            $where .=' and b.NeedWageRequire<>b.CurWageRequire';
            if(trim($wage_min)!=''){
                if(is_numeric($wage_min)){
                    $where .=' and b.NeedWageRequire>='.$wage_min*bl;
                }
            }

            if(trim($wage_max)!=''){
                if(is_numeric($wage_max)){
                    $where .=' and b.NeedWageRequire<='.$wage_max*bl;
                }
            }

            if(trim($avg_min)!=''){
                if(is_numeric($avg_min)){
                    $where .=' and (b.CurWageRequire/CAST(b.NeedWageRequire as FLOAT))>='.$avg_min/100;
                }
            }

            if(trim($avg_max)!=''){
                if(is_numeric($avg_max)){
                    $where .=' and (b.CurWageRequire/CAST(b.NeedWageRequire as FLOAT))<='.$avg_max/100;
                }
            }
        }
        if(config('is_dmrateset') == '1'){
            $result = $this->getTableObject('[View_Accountinfo](NOLOCK)')->alias('a')
                ->join('(SELECT * FROM [T_UserWageRequire] where id in(select max(Id) from  [T_UserWageRequire] group by roleid )) b','a.AccountID=b.roleid','left')
                ->join('[CD_UserDB].[dbo].[T_Job_UserInfo] c','c.RoleID=a.AccountID and c.job_key=3','left')
                ->join('[CD_UserDB].[dbo].[T_Job_UserInfo] d','d.RoleID=a.AccountID and d.job_key=4','left')
                ->join('(SELECT * FROM [T_UserWage]) e','a.AccountID=e.RoleId','left')
                ->where($where)
                ->field('AccountID,Money,OperatorId,TotalDeposit,TotalRollOut,RegisterTime,b.FreezonMoney as iFreezonMoney,CASE WHEN CAST(e.NeedWageRequire AS FLOAT) <> 0 THEN CAST(e.CurWageRequire AS FLOAT) / CAST(e.NeedWageRequire AS FLOAT) ELSE 0 END as percentage,ISNULL(c.value,0) as win_dmrateset,ISNULL(d.value,0) as lose_dmrateset')

                ->order("$orderby $ordertype")
                ->fetchSql(0)
                ->paginate($limit)
                ->toArray();

            //  CASE
            //cast(CASE WHEN b.CurWageRequire=0 OR b.NeedWageRequire=0 THEN 0 Else b.CurWageRequire/b.NeedWageRequire END as percentage)
            //    WHEN A = 0 OR B = 0 THEN 0
            //    ELSE A / B
            //  END AS C b.CurWageRequire as percentage_aa,b.NeedWageRequire as percentage,
        } else {
            $result = $this->getTableObject('[View_Accountinfo](NOLOCK)')->alias('a')
                ->join('(SELECT * FROM [T_UserWageRequire] where id in(select max(Id) from  [T_UserWageRequire] group by roleid )) b','a.AccountID=b.roleid','left')
                ->where($where)
                ->field('AccountID,Money,OperatorId,TotalDeposit,TotalRollOut,RegisterTime,b.FreezonMoney as iFreezonMoney,b.CurWageRequire/CAST(b.NeedWageRequire as FLOAT) as percentage')
                ->order("$orderby $ordertype")
                ->fetchSql(0)
                ->paginate($limit)
                ->toArray();
        }


        $result['list'] = $result['data'];
        $result['count'] = $result['total'];
        // $result = $this->GetPage($where, "$orderby $ordertype", 'RoleID AccountID,Money');
        foreach ($result['list'] as &$item) ConVerMoney($item['Money']);
        return $result;
    }


    /**
     *  战绩排行
     */
    public function GetWinScoreRankList()
    {
        $orderby = input('orderby', 'total');
        $ordertype = input('ordertype', 'Desc');
        $where = "";
        if (!empty($account)) $where .= " and  AccountName like '$account'";
        $result = $this->TViewAccount()->GetPage($where, "$orderby $ordertype", "AccountID,Money,TotalDeposit,TotalRollOut,TotalDeposit-TotalRollOut-Money as total");
        foreach ($result['list'] as &$item) {
            ConVerMoney($item['Money']);
            ConVerMoney($item['total']);
        }
        return $result;
    }

    /**
     * 总充值
     */
    public function TotalPay()
    {
        $this->table = 'T_UserTransactionChannel';
        $where = '1=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where = 'OperatorId=' . session('merchant_OperatorId');
        }
        return $this->GetRow($where, 'SUM(RealMoney) TotalPay')['TotalPay'];
    }

    //充值留存
    public function PayRate(&$where, &$order)
    {
        $this->table = 'View_PayRate';
        $result = $this->GetPage($where, $order, '', true);
        $list =& $result['list'];
        foreach ($list as $index => &$item) {
            $ddif = strtotime($item['mydate']) - strtotime(date('Ymd'));//数据日期 与当前日期对比
            $ddif = abs(round($ddif / 86400));//换算 相差多少天
            if (strlen($item['mydate']) == 0) {
                unset($list[$index]);
                continue;
            }
            //如果充值人数大于0
            if ($item['TotalPay'] > 0) {

                for ($i = 1; $i <= 9; $i++) {
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = $item['day' . $i] . " / " . sprintf('%.2f', ($item['day' . $i] / $item['TotalPay'] * 100)) . "%";
                    } else {
                        $item['day' . $i] = ""; //天数不够留空
                    }
                }
                continue;
            } else {
                for ($i = 1; $i <= 9; $i++) {
                    $item['TotalPay'] = 0;
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = '0/0';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
            }
        }
        unset($item);
        $list = array_merge($list);
        return $result;
    }

    //付费留存
    public function PayfeeRate(&$where, &$order)
    {
        $this->table = 'View_PayFeeRate';
        $result = $this->GetPage($where, $order, '', true);
        $list =& $result['list'];
        foreach ($list as $index => &$item) {
            if (strlen($item['mydate']) == 0) {
                unset($list[$index]);
                continue;
            }
            $ddif = strtotime($item['mydate']) - strtotime(date('Ymd'));//数据日期 与当前日期对比
            $ddif = abs(round($ddif / 86400));//换算 相差多少天
            //如果充值人数大于0
            if ($item['TotalPay'] > 0) {

                for ($i = 1; $i <= 9; $i++) {
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = $item['day' . $i] . " / " . sprintf('%.2f', ($item['day' . $i] / $item['TotalPay'] * 100)) . "%";
                    } else {
                        $item['day' . $i] = ""; //天数不够留空
                    }
                }
                continue;
            } else {
                for ($i = 1; $i <= 9; $i++) {
                    $item['TotalPay'] = 0;
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = '0/0';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
            }
        }
        unset($item);
        $list = array_merge($list);
        return $result;
    }

    /**注册留存*/
    public function userRegRate(&$where, &$order)
    {
        $this->table = 'View_UserRegRate';
        $switchcfg = config('regrate');
        $result = $this->GetPage($where, $order, '', true);
        $list =& $result['list'];
        foreach ($list as &$item) {
            $ddif = strtotime($item['mydate']) - strtotime(date('Ymd'));
            $ddif = abs(round($ddif / 86400));
            if ($item['TotalReg'] > 0) {
                for ($i = 1; $i <= 9; $i++) {
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $remainday = $item['day' . $i];
                        $remaintotal = $item['TotalReg'];
                        $remain_rate = bcdiv($remainday, $remaintotal, 4);
                        if ($switchcfg && $remain_rate > 0) {
                            $remain_rate = $remain_rate + ($switchcfg / 100);
                            $remainday = intval($remaintotal * $remain_rate);
                        }
                        $remain_rate = sprintf('%.2f', ($remain_rate * 100));
                        $item['day' . $i] = $remainday . ' / ' . $remain_rate . '%';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
                continue;
            } else {
                for ($i = 1; $i <= 9; $i++) {
                    $item['TotalReg'] = 0;
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = '0/0';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
            }

        }
        unset($item);
        return $result;
    }


    //首充留存
    public function userFirstPayRate(&$where, &$order)
    {
        $this->table = 'View_FirstPayRate';
        $result = $this->GetPage($where, $order, '', true);
        $list =& $result['list'];
        foreach ($list as &$item) {
            $ddif = strtotime($item['mydate']) - strtotime(date('Ymd'));
            $ddif = abs(round($ddif / 86400));
            if ($item['TotalPay'] > 0) {
                for ($i = 1; $i <= 9; $i++) {
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = $item['day' . $i] . " / " . sprintf('%.2f%%', ($item['day' . $i] / $item['TotalPay'] * 100));
                    } else {
                        $item['day' . $i] = "";
                    }
                }
                continue;
            } else {
                for ($i = 1; $i <= 9; $i++) {
                    $item['TotalPay'] = 0;
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = '0/0';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
            }
        }
        unset($item);
        return $result;
    }


    //渠道首充留存
    public function userFirstPayByChannel(&$where, &$order)
    {
        $this->table = 'T_UserRemainChannel';
        $result = $this->GetPage($where, $order, '*', true);
        $list =& $result['list'];
        foreach ($list as &$item) {
            $ddif = strtotime($item['mydate']) - strtotime(date('Ymd'));
            $ddif = abs(round($ddif / 86400));
            if ($item['TotalUser'] > 0) {
                for ($i = 1; $i <= 9; $i++) {
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = $item['day' . $i] . " / " . sprintf('%.2f%%', ($item['day' . $i] / $item['TotalUser'] * 100));
                    } else {
                        $item['day' . $i] = "";
                    }
                }
                continue;
            }
            else {
                for ($i = 1; $i <= 9; $i++) {
                    $item['TotalUser'] = 0;
                    $j = $i;
                    if ($i == 8) {
                        $j = 15;
                    }
                    if ($i == 9) {
                        $j = 30;
                    }
                    if ($j <= $ddif) {
                        $item['day' . $i] = '0/0';
                    } else {
                        $item['day' . $i] = "";
                    }
                }
            }
        }
        unset($item);
        return $result;
    }

    /**
     * 盘控汇总
     */
    public function TUserSystemCtrlData()
    {
        $this->table = $this->TUserSystemCtrlData;
        return $this;
    }

    public function TViewChannelPayOrder()
    {
        $this->table = "View_ChannelPayOrder";
        return $this;
    }

    //订单管理
    public function GetChannelPayOrderList()
    {
        if (1) {
            $this->table = "View_ChannelPayOrder";
//            $account = input('account', 0);
            $roleId = (int)input('roleid', 0);
            $tranNO = input('tranNO');
            // $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d").' 00:00:00';
            if (request()->has('strartdate')) {
                $strartdate = input('strartdate') ?: config('record_start_time');
            } else {
                $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d") . ' 00:00:00';
            }
            $enddate = input('enddate') ? input('enddate') : date("Y-m-d") . ' 23:59:59';
            $payChannel = (int)input('payChannel', -1);
            $payType = (int)input('payType', -1);
            $amount = input('amount', 0);
            $max_amount = input('max_amount', 0);
            $status = input('Status', -1);
            $isreturn = input('isReturn', -1);
            $where = '';
            if ($status == 100) {
                $status = 1;
                $isreturn = 1;
            }
//            if ($account > 0) $roleId = $this->GetUserIDByAccount($account);
            if ($roleId > 0) $where .= "AND AccountID=$roleId";
            if (!IsNullOrEmpty($tranNO)) $where .= " AND OrderId='$tranNO'";
            // if (!IsNullOrEmpty($strartdate)) $where .= " AND  PayTime>='$strartdate 00:00:00' ";
            // if (!IsNullOrEmpty($enddate)) $where .= " AND  PayTime<='$enddate 23:59:59'";
            if (!empty($strartdate)) {
                $where .= " AND PayTime >='$strartdate'";
            }
            if (!empty($enddate)) {
                $where .= " AND PayTime <='$enddate'";
            }

            if ($payChannel >= 0) $where .= " AND ChannelID=$payChannel";
            if ($payType >= 0) $where .= " AND PayType=$payType";
            if ($amount > 0) $where .= " AND RealMoney>=$amount";
            if ($max_amount > 0) $where .= " AND RealMoney<=$max_amount";
            if ($status >= 0) $where .= " AND Status=$status";
            if ($isreturn > -1) $where .= " AND isReturn=$isreturn";
        }
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' AND OperatorId=' . session('merchant_OperatorId');
        }
        $result = $this->GetPage($where, "ID desc");
        $result['other'] = $this->GetRow("1=1 $where", "ISNULL(Sum(1),0)totalCount,ISNULL(Sum(RealMoney),0)totalMoney");
        $payorder = $this->GetRow("1=1 $where and Status in(1,100)", "ISNULL(Sum(1),0) paytotalCount,ISNULL(Sum(RealMoney),0) paytotalMoney");
        $result['other']['paytotalCount'] = $payorder['paytotalCount'];
        $result['other']['paytotalMoney'] = $payorder['paytotalMoney'];
        $result['other']['success_rate'] = 0;
        if ($result['other']['totalCount'] > 0) {
            $result['other']['success_rate'] = bcdiv($result['other']['paytotalCount'] * 100, $result['other']['totalCount'], 2) . '%';
        }
        if (isset($result['list'])) {
            foreach ($result['list'] as &$item) {
                $item['oStatus'] = $item['Status'];
                switch ((int)$item['Status']) {
                    case 0:
                        $item['Status'] = lang("未付款");
                        $item['BaseGoodsValue'] = 0;
                        break;
                    case 1:
                        $item['Status'] = lang("订单完成");
                        $item['BaseGoodsValue'] = FormatMoney($item['BaseGoodsValue']);
                        break;
                    case 2:
                        $item['Status'] = lang("金币未发放");
                        $item['BaseGoodsValue'] = 0;
                        break;
                    case 3:
                        $item['Status'] = lang("第三方失败");
                        $item['BaseGoodsValue'] = 0;
                        break;
                    case 4:
                        $item['Status'] = lang("拒绝发放金币");
                        $item['BaseGoodsValue'] = 0;
                        break;
                }
                switch ((int)$item['PayType']) {
                    case 0:
                        $item['PayType'] = lang('商城');
                        break;
                    case 1:
                        $item['PayType'] = lang('首充礼包');
                        break;
                    case 2:
                        $item['PayType'] = lang('充值返利');
                        break;
                    case 3:
                        $item['PayType'] = lang('商店充值');
                        break;
                    case 4:
                        $item['PayType'] = lang('客服充值');
                        break;
                    case 5:
                        $item['PayType'] = lang('周卡充值');
                        break;
                    case 6:
                        $item['PayType'] = lang('月卡充值');
                        break;
                    default :
                        $item['PayType'] = lang('未知');
                        break;
                }


            }
        }
        return $result;
    }

    //手机列表
    public function getMobileList()
    {
        $this->table = $this->TViewAccountinfo;
        $roleid = input('roleid', 0);
        $order = input('orderfield');
        $otype = input('ordertype');
        if (!empty($order)) $order = "$order $otype";
        else $order = "BindPhoneTime desc";
        $where = " AND AccountType=3 AND Mobile !=''";
        if ($roleid > 0) $where .= " AND AccountID=$roleid";
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= " and OperatorId=" . session('merchant_OperatorId');
        }
        $data = $this->GetPage($where, "BindPhoneTime desc"
            , "AccountID,Mobile,LoginName,BindPhoneTime,TotalDeposit,TotalRollOut");
        foreach ($data['list'] as &$item) {
            if (!empty($item['Mobile'])) {
                // $item['Mobile'] = substr_replace($item['Mobile'], '**', -2);
            }
        }
        return $data;
    }
//    public function getGDrank($where,$order){
//        /***
//        $filed = "AccountID,LoginName,Money,0-TotalWin SGD,TotalRunning,TotalDeposit,TotalRollOut," .
//            "CASE WHEN TotalRunning>0 THEN (TotalRunning+TotalWin)/TotalRunning*100   ELSE 0 END GameRate";
//        ***/
//        $result= $this->TViewAccount()->GetPage($where,$order,$filed);
//        if (isset($result['list']) && $result['list']) {
//            foreach ($result['list'] as &$v) {
//                $v['GameRate'] = sprintf("%.2f", $v['GameRate']) . "%";
//                $v['SGD'] =ConverMoney( $v['SGD']) ;
//                $v['Money'] = ConverMoney( $v['Money']) ;
//                $v['TotalRunning'] = ConverMoney( $v['TotalRunning']) ;
//                unset($v);
//            }
//        }
//        return $result;
//    }
    public function TViewUserDrawBack(&$Channel)
    {
        if (true) {
            $this->table = "View_UserDrawBack";
            $roleid = (int)input('roleid', -1);
            $status = input('Status', -1);
            $account = input('account', 0);
            $tranNO = input('tranNO', 0);
            $amount = input('amount', 0);
            $max_amount = input('max_amount', 0);
            // $start = input('start')?: config('record_start_time');
            $start = input('start') ?: config('record_start_time') . ' 00:00:00';
            $end = input('end') ?: date('Y-m-d') . ' 23:59:59';
            $payChannel = input('payChannel', -1);
            $payWay = input('payWay', -1);
            $payType = (int)input('payType', 0);
            $orderby = input('orderby', '');
            $ordertype = input('ordertype', '');
            $cpf = input('cpf', '');
            $operatorId = input('OperatorId', '');
            if (strtotime($start) < strtotime(config('record_start_time'))) {
                $start = config('record_start_time');
            }
            $order = "AddTime desc";
            if (!empty($ordertype) && !empty($orderby)) {
                $order = $orderby . ' ' . $ordertype;
            }
            $limit = (int)input('limit', 20);
            //拼接 Where;
            $where = "";
            if ($status != 0) {
                $checkUser = input('checkUser', session('username'));
                if (request()->module() != 'business') {
                    if ($checkUser != '0') $where .= " and checkUser like '$checkUser'";
                }
            }
            if (!empty($account)) $roleid = $this->GetUserIDByAccount($account);
            if ($roleid > 0) $where .= " and AccountID=$roleid";
            if ($status >= 0) $where .= " and status = $status";
            if ($tranNO != 0) $where .= " and OrderNo='$tranNO'";
            if ($amount > 0) {
                $min_amount = $amount * bl;
                $where .= " and iMoney>=" . $min_amount;
            }
            if ($max_amount > 0) {
                $max_amount = $max_amount * bl;
                $where .= " and iMoney<=" . $max_amount;
            }
            // if (!empty($start)) $where .= " and UpdateTime Between '$start 00:00:00' and '$end 23:59:59'";
            $where .= " and UpdateTime>='" . $start . "' and UpdateTime<='" . $end . "'";
            if ($payChannel > 0) $where .= " and ChannelId=$payChannel";
            if ($payWay > 0) $where .= " and PayWay=$payWay";
            if ($payType == 1) $where .= " AND totalPay>0";
            if ($payType == 2) $where .= " AND totalPay=0 AND EamilMoney=0";
            if (!empty($cpf)) $where .= " AND Province like '%{$cpf}%'";

        }
        // var_dump($where);die();
        $feild = "iMoney,Tax,OrderNo,AccountID,PayWay,RealName,CardNo,IsDrawback,BankName,status,checkTime,AddTime,UpdateTime,TransactionNo,ChannelId,checkUser,totalPay,TotalDS,EamilMoney,cheatLevel,Province,Descript,City,OperatorId,DrawBackWay,ProxyChannelId";
        if (config('app_name') == 'TATUWIN') {
            $feild .= ',RiskLevel';
        }
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' AND OperatorId=' . session('merchant_OperatorId');
        } elseif (session('business_ProxyChannelId') && request()->module() == 'business') {
            $where .= ' AND ProxyChannelId=' . session('business_ProxyChannelId');

        }{
            if (!empty($operatorId)) {
                $where .= ' AND OperatorId=' . $operatorId;
            }
        }
        // $result = $this->GetPage($where, "$order", $feild);
        $data = (new BankDB())->getTableObject('View_UserDrawBack')->alias('a')
            ->join('[CD_UserDB].[dbo].[T_UserGameWealth](NOLOCK) b', 'b.RoleID=a.AccountID', 'left')
            ->join('[CD_UserDB].[dbo].[T_UserWage](NOLOCK) c', 'c.RoleID=a.AccountID', 'left')
            ->field('a.*,b.Money,c.CurWageRequire')
            ->where('1=1 ' . $where)
            ->order($order)
            ->paginate($limit)
            ->toArray();
        $result = [];
        $result['list'] = $data['data'];
        $result['count'] = $data['total'];
        unset($data);
        $comments = (new \app\model\GameOCDB)->getTableObject("[OM_GameOC].[dbo].[T_PlayerComment]")->where('type', 1)->select();
        foreach ($result['list'] as &$item) {
            $item['ChannelName'] = "";
            foreach ($Channel as &$p) if ($p['ChannelId'] == $item['ChannelId']) $item['ChannelName'] = $p['ChannelName'];
            ConVerMoney($item['iMoney']);
            ConVerMoney($item['Tax']);
            ConVerMoney($item['EamilMoney']);
            ConVerMoney($item['TotalDS']);
            $item['Money'] = $item['Money'] ?? 0;
            ConVerMoney($item['Money']);
            $item['RealPay'] = $item['iMoney'] - $item['Tax'];//实际金额  提交-税
            if ($item['checkTime'])
                $item['checkTime'] = date('Y-m-d H:i:s', strtotime($item['checkTime']));
            else
                $item['checkTime'] = '';
            $item['UpdateTime'] = date('Y-m-d H:i:s', strtotime($item['UpdateTime']));
            $item['AddTime'] = date('Y-m-d H:i:s', strtotime($item['AddTime']));
            $str = "";

            switch ((int)$item['status']) {
                case 0:
                    $str = lang('待加入工单');
                    break;
                case 6:
                    $str = lang('已加入工单，待审核');
                    break;
                case 1:
                    $str = lang('已审核');
                    break;
                case 2:
                    $str = lang('拒绝并退币');
                    break;
                case 3:
                    $str = lang('拒绝并没收金币');
                    break;
                case 4:
                    $str = lang('第三方处理中');
                    break;
                case 5:
                    $str = lang('处理失败并退金币');
                    break;
                case 100:
                    $str = lang('订单完成');
                    break;
            }
            $item['orderType'] = $str;

            $strcheat = '';
            switch (intval($item['cheatLevel'])) {
                case 14:
                    $strcheat = lang('疑似TP导分');
                    break;
                case 15:
                    $strcheat = lang('不同IP同押注');
                    break;
                case 18:
                    $strcheat = lang('第一次下注直接梭哈');
                    break;
                case 19:
                    $strcheat = lang('同IP押注有一点差异');
                    break;
                case 20:
                    $strcheat = lang('同IP同押注');
                    break;
                default:
                    if (intval($item['cheatLevel'] > 0)) {
                        $strcheat = lang('其他异常');
                    }
                    break;
            }
            $item['cheatLevelDesc'] = $strcheat;
            if (empty($item['Descript'])) {
//                ->where('roleid', $item['AccountID'])->order('opt_time desc')->find();
                foreach($comments as $comment){
                    if ($comment['roleid'] == $item['AccountID']){
                        $item['Descript'] = $comment['comment'];
                    }
                }
            }
            $item['DrawWayName'] = '';
            if ($item['DrawBackWay'] == 1) {
                $item['DrawWayName'] = lang('余额提现');
            } else if ($item['DrawBackWay'] == 2) {
                $item['DrawWayName'] = lang('活动奖励提现');
            }

        }
        $field = "SUM(iMoney-Tax) AS TotalScore , SUM(Tax)AS TotalTax, SUM(iMoney) AS TotalGold";
        $result['other'] = $this->GetRow("1=1 $where", $field);
        ConVerMoney($result['other']['TotalScore']);
        ConVerMoney($result['other']['TotalTax']);
        ConVerMoney($result['other']['TotalGold']);
        return $result;
    }

    /**
     * 统计所有用户当前所拥有的金币数量
     * @return int
     */
    public function CountUserTotalCoinsStock()
    {
        $table = "T_UserGameWealth";
        $fields = " ISNULL(sum(Money), 0) as count ";
        $data = $this->DBQuery($table, $fields);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    /**
     * 统计所有用户邮件补偿金币
     * @return int
     *
     *
     */
    public function CountUserDailyMailExtraGiftCoins($day)
    {
        $this->table = $this->TViewIndex;
        $where = " mydate ='" . $day . "'";
        $field = "EmailAmount";
        $data = $this->GetRow($where, $field);
        if (!empty($data) && isset($data['EmailAmount'])) {
            return $data['EmailAmount'];
        } else {
            return 0;
        }
    }

    //获取邀请码
    public function addChannelAgent($parameter)
    {
        try {
            $strsql = "exec CD_UserDB.dbo.Proc_CreateChannelUser :LoginID,:AccountName,:PassWord,:NickName,:Descript";
            $res = $this->connection->query($strsql, $parameter);
            return $res;
        } catch (PDOException $e) {
            save_log('error', $e->getMessage());
            return false;
        }
    }

    /**
     * 彩金分配表
     * @return $this
     */
    public function TUserJackpotDistribute()
    {
        $this->table = "T_UserJackpotDistribute";
        return $this;
    }


    /**
     * 彩金中奖记录
     * @return $this
     */
    public function TUserJackpotRecord()
    {
        $this->table = 'T_UserJackpotRecord';
        return $this;
    }

    public function GetUserScanOrderTable()
    {
        $this->table = $this::USER_SCAN_ORDER_TRANSACTION;
        return $this;
    }

    public function GetUserChannelPayOrderTable()
    {
        $this->table = 'T_UserChannelPayOrder';
        return $this;
    }

    public function getXbusiness($ProxyChannelIds)
    {
        static $result = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid', 'in', $ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result = array_unique(array_merge($result, $xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
    }

    public function getBloggerData(){
        $limit          = request()->param('limit') ?: 15;
        $RoleID         = request()->param('RoleID');
        $OperatorId     = request()->param('OperatorId');
        $ProxyChannelId = request()->param('ProxyChannelId');
        $start_date     = request()->param('start_date');
        $end_date       = request()->param('end_date');
        $orderby        = request()->param('orderby');
        $ordertype      = request()->param('ordertype');

        $where = 'A.IsBlogger=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and A.OperatorId='.session('merchant_OperatorId');
        }

        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $where .= ' and A.ProxyChannelId='.session('business_ProxyChannelId');
        }

        if ($RoleID != '') {
            $where .= ' and A.AccountID=' . $RoleID;
        }
        if ($OperatorId != '') {
            $where .= ' and A.OperatorId=' . $OperatorId;
        }
        if ($ProxyChannelId != '') {
            $where .= ' and A.ProxyChannelId=' . $ProxyChannelId;
        }
        if ($start_date != '') {
            $where .= ' and A.BloggerDate>=\'' . $start_date . '\'';
        }
        if ($end_date != '') {
            $where .= ' and A.BloggerDate<\'' . $end_date . '\'';
        }
        if (empty($orderby) || empty($ordertype)) {
            $orderby = 'BloggerDate';
            $ordertype = 'desc';
        }
        $field = 'A.AccountID,A.BloggerDate,A.WeChat,A.OperatorId,A.ProxyChannelId,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1DepositPlayers,0) AS Lv1DepositPlayers,ISNULL(B.Lv1WithdrawCount,0) AS Lv1WithdrawCount,ISNULL(B.Lv1WithdrawAmount,0) AS Lv1WithdrawAmount,ValidInviteCount,C.emailAmount,D.withdrawAmount,(ISNULL(B.Lv1Deposit,0)*1000-ISNULL(B.Lv1WithdrawAmount,0)-ISNULL(D.withdrawAmount,0)) as profit,ISNULL(E.gmmoney,0) gmmoney';

        $mail_sql = "(select RoleId as AccountID,sum(Amount) emailAmount from [CD_DataChangelogsDB].[dbo].[T_ProxyMsgLog](NOLOCK) where RecordType=8 and Amount>0  and VerifyState=1 group by RoleId) as C";

        $withdraw_sql  ="(SELECT sum(iMoney) withdrawAmount,AccountID FROM [OM_BankDB].[dbo].[UserDrawBack](NOLOCK) WHERE status=100 GROUP BY AccountID) as D";

        $gm_sql  ="(select RoleId,sum(Money) gmmoney from [OM_GameOC].[dbo].[T_GMSendMoney] where operatetype in(1,3) and status=1 group by RoleId) as E";

        $data =  (new \app\model\AccountDB())->getTableObject('T_Accounts(NOLOCK)')
            ->alias('A')
            ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) B', 'B.ProxyId=A.AccountID', 'LEFT')
            ->join($mail_sql,'C.AccountID=A.AccountID', 'LEFT')
            ->join($withdraw_sql,'D.AccountID=A.AccountID', 'LEFT')
            ->join($gm_sql,'E.RoleId=A.AccountID', 'LEFT')
            ->where($where)
            ->field($field)
            ->order("$orderby $ordertype")
            ->paginate($limit)
            ->toArray();

        $AccountIDs = array_column($data['data'], 'AccountID');
        $AccountIDs = implode(',',$AccountIDs);

        $sql1 = "(select RoleId as AccountID,addtime,Amount from T_ProxyMsgLog where id in(select min(id) from T_ProxyMsgLog where RoleId in(".$AccountIDs.") and RecordType=8 and Amount>0 and VerifyState=1 group by RoleId)) as sql1";
        if ($AccountIDs) {
            $mail_first_time = (new \app\model\DataChangelogsDB())->getTableObject($sql1)->column('addtime,Amount','AccountID');
        } else {
            $mail_first_time = [];
        }


        $sql2 = "(select RoleId as AccountID,addtime,Amount from T_ProxyMsgLog where id in(select max(id) from T_ProxyMsgLog where RoleId in(".$AccountIDs.") and RecordType=8 and Amount>0 and VerifyState=1 group by RoleId)) as sql2";
        if ($AccountIDs) {
            $mail_last_time = (new \app\model\DataChangelogsDB())->getTableObject($sql2)->column('addtime,Amount','AccountID');
        } else {
            $mail_last_time = [];
        }
        $GameOCDB = new \app\model\GameOCDB();
        foreach ($data['data'] as $key => &$val) {
            $AccountID = $val['AccountID'];
            $val['emailFristDate'] = $mail_first_time[$AccountID]['addtime'] ?? '';
            $val['emailLastDate'] = $mail_last_time[$AccountID]['addtime'] ?? '';

            $val['emailAmount'] = FormatMoney($val['emailAmount']);
            $val['withdrawAmount'] = FormatMoney($val['withdrawAmount']);
            $val['Lv1WithdrawAmount'] = FormatMoney($val['Lv1WithdrawAmount']);
            $val['profit'] = FormatMoney($val['profit']);

            $val['gamesf'] = $GameOCDB->getTableObject('T_GMSendMoney')->where('RoleId',$AccountID)->where('status',1)->where('OperateType','in','1,2')->sum('Money')?:0;
            $val['commisionsf'] = $GameOCDB->getTableObject('T_GMSendMoney')->where('RoleId',$AccountID)->where('status',1)->where('OperateType','in','3,4')->sum('Money')?:0;
            $val['totalsf'] = bcadd($val['gamesf'], $val['commisionsf'],2)/1;
        }
        return $data;
    }

    //掉绑记录
    public function unbindRecord(){
        $limit          = request()->param('limit') ?: 15;
        $RoleID         = request()->param('RoleID');
        $DisableBindParentId  = request()->param('DisableBindParentId');
        $OperatorId     = request()->param('OperatorId');
        $ProxyChannelId = request()->param('ProxyChannelId');
        $start_date     = request()->param('start_date');
        $end_date       = request()->param('end_date');

        $where = 'a.DisableBindParentId<>0';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and c.OperatorId='.session('merchant_OperatorId');
        }

        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $where .= ' and c.ProxyChannelId='.session('business_ProxyChannelId');
        }

        if ($RoleID != '') {
            $where .= ' and a.RoleID=' . $RoleID;
        }
        if ($DisableBindParentId != '') {
            $where .= ' and a.DisableBindParentId=' . $DisableBindParentId;
        }
        if ($OperatorId != '') {
            $where .= ' and c.OperatorId=' . $OperatorId;
        }
        if ($ProxyChannelId != '') {
            $where .= ' and c.ProxyChannelId=' . $ProxyChannelId;
        }
        if ($start_date != '') {
            $where .= ' and c.RegisterTime>=\'' . $start_date . '\'';
        }
        if ($end_date != '') {
            $where .= ' and c.RegisterTime<\'' . $end_date . '\'';
        }
        $data = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
            ->join('[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) d', 'd.RoleID=a.RoleID', 'left')
            ->where($where)
            ->field('a.RoleID as AccountID,a.ParentID,a.DisableBindParentId,c.RegisterTime,c.OperatorId,c.ProxyChannelId,c.RegIP,c.LastLoginTime,d.TotalDeposit,d.TotalRollOut')
            ->paginate($limit)
            ->toArray();
        foreach ($data['data'] as $key => &$val) {
            $val['RegisterTime'] = date('Y-m-d H:i:s', strtotime($val['RegisterTime']));
            $val['TotalDeposit'] = bcdiv($val['TotalDeposit'] ?: 0, 1, 3) / 1;
            $val['TotalRollOut'] = bcdiv($val['TotalRollOut'] ?: 0, 1, 3) / 1;
        }
        return $data;
    }
}