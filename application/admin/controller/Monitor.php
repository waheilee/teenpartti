<?php

namespace app\admin\controller;

use app\model;
use app\model\OperationLogsDB;
use app\model\UserDB;


class Monitor extends BaseController
{
    protected $defaultDate = "2021-01-01 00:00:00";

    /**
     * 运营数据监控
     */
    public function index()
    {


        return $this->fetch();
    }

    /**
     * Notes:基础监控
     */
    public function basicmonitoring()
    {
        if ($this->request->isAjax()) {
            $strartdate = input('strartdate', date("Y-m-d 00:00:00"));
            $enddate = input('enddate', date("Y-m-d 23:59:59"));
            $page = intval(input('page', 1));
            $limit = intval(input('limit', 15));
            $ddate = " and b.AddTime BETWEEN '$strartdate' AND '$enddate' ";
            //拼装sql
            $strFields = "t1.Rank as Level, ISNULL(t2.UserCount, 0) as UserCount";
            $tableName = " [OM_MasterDB].[dbo].[T_ChacRank] t1 LEFT JOIN (SELECT [ChacRank], COUNT(1) as UserCount FROM [CD_UserDB].[dbo].[T_UserChacRank] a,
            ( select RoleID,AddTime from [CD_UserDB].[dbo].[T_Role]) b WHERE [ChacRank] <= 100 and a.RoleId=b.RoleID $ddate GROUP BY [ChacRank]) t2 ON t1.Rank = t2.ChacRank ";
            $where = " where t1.Rank <= 100 ";
            $limits = "";
            //$limit = 100;
            $comm = new model\CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit);
            $count = $list['count'];
            $result = $list['list'];

//            $loss = 0; //等级流失
//            $arrive = 0; //等级到达
            $allplayer = array_sum(array_column($result, 'UserCount'));
            $num = $allplayer;
            for ($i = 0; $i < count($result); $i++) {
                if ($result[$i]['UserCount'] != 0) {
                    $num2 = $result[$i]['UserCount']; //当某等级有玩家时，先记录当前等级玩家数
                    $result[$i]['UserCount'] = $num;  //
                    $num = $num - $num2;
                } else {
                    $result[$i]['UserCount'] = $num;
                }
            }
            for ($i = 0; $i < count($result); $i++) {
                //顶部统计：等级流失 （当前等级人数）-（上一等级人数）｝/上一等级人数*100%
                if (($i != 0) && ($result[$i - 1]['UserCount'] != 0)) {
                    $loss = $result[$i]['UserCount'] - $result[$i - 1]['UserCount'];
                    $result[$i]['loss'] = round($loss / $result[$i - 1]['UserCount'] * 100, 2) . "%";
                } else
                    $result[$i]['loss'] = "0%";

                //顶部统计：等级到达 当前人数/总角色数
                if ($allplayer != 0) {
                    $result[$i]['arrive'] = round($result[$i]['UserCount'] / $allplayer * 100, 2) . "%";
                } else
                    $result[$i]['arrive'] = "0%";

            }

            $totalsum['allplayer'] = $allplayer;
            $list = $result;
            return $this->apiReturn(
                $res['code'] = 0,
                $list,
                $res['message'] = '',
                0,  //输出查询条数，分页用
                $totalsum  //输出订单总数和总金额
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:安装建角监控
     */
    public function loginactionstatistic()
    {
        $strFields = "Id,mydate,StartGameCnt,CheckUpdateCnt,CheckUpdateFailedCnt,NoUpdateCnt,NeedUpdateCnt,UpdateFinishCnt,UpdateFailedCnt,NetConnectCnt,NetConnectFailedCnt,LoginAccountCnt,LoginHallCnt,AddTime";
        $tableName = " [OM_OperationLogsDB].[dbo].[T_LoginActionStatistic] ";
        $limits = "";
        $orderBy = " order by Id desc";
        if ($this->request->isAjax()) {
            $strartdate = input('strartdate', date("Y-m-d 00:00:00"));
            $enddate = input('enddate', date("Y-m-d 23:59:59"));
            $page = intval(input('page', 1));
            $limit = intval(input('limit', 15));
            //拼装sql
            $limits = " top " . ($page * $limit);
            $where = " WHERE mydate BETWEEN '$strartdate' AND '$enddate' ";
            $comm = new model\CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit, $orderBy);
            $count = $list['count'];
            $result = $list['list'];
            if (empty($result)) {
                $totalsum['startgamecnt'] = 0;
                $totalsum['updatefinishcnt'] = 0;
                $totalsum['loginhallcnt'] = 0;
                return $this->apiReturn(100, '', '暂无可用数据', 0, $totalsum);
            }
            $paysum = 0;
            $all_mydate = 0;
            $StartGameCnt = 0; //启动游戏次数
            $UpdateFinishCnt = 0;//热更完成次数
            $LoginHallCnt = 0; //登录大厅次数
            $list = $result;//$res['data']['ResultData']['list'];
            foreach ($list as $item => &$value) {
                //顶部统计：总安装数量，热更完成，进入大厅
                $StartGameCnt = $StartGameCnt + $value['StartGameCnt'];
                $UpdateFinishCnt = $UpdateFinishCnt + $value['UpdateFinishCnt'];
                $LoginHallCnt = $LoginHallCnt + $value['LoginHallCnt'];
                //汇总启动游戏网络（连接成功/失败）
                $value['qidong'] = $value['NetConnectCnt'] - $value['NetConnectFailedCnt'];
                $value['qidong'] = $value['qidong'] . "/" . $value['NetConnectFailedCnt'];
                //汇总热更情况（有/无热更）
                $value['regeng'] = $value['NeedUpdateCnt'] . "/" . $value['NoUpdateCnt'];
            }
            unset($value);
            $totalsum['startgamecnt'] = $StartGameCnt;
            $totalsum['updatefinishcnt'] = $UpdateFinishCnt;
            $totalsum['loginhallcnt'] = $LoginHallCnt;

            return $this->apiReturn(
                $res['code'] = 0,
                $list,
                $res['message'] = '',
                isset($count) ? $count : 0,  //输出查询条数，分页用
                $totalsum  //输出订单总数和总金额
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:机台进入监控
     */
    public function roomactionstatistic()
    {
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d");
            $enddate = input('enddate') ? input('enddate') : date("Y-m-d");
            $where = "AND mydate BETWEEN '$strartdate' AND '$enddate' ";
            //拼装sql
            if ($roomId > 0) $where .= " and RoomID = $roomId";
            $orderBy = "id";
            $base = new OperationLogsDB();
            $list = $base->TableRoomActionStatistic()->GetPage($where, "$orderBy desc");
            $count = $list['count'];
            $result = $list['list'];
            $jitai = '';
            $shichang = '';

            if (empty($result)) {
                $totalsum['jitai'] = $jitai;
                $totalsum['shichang'] = $shichang;
                return $this->apiReturn(100, '', '暂无可用数据', 0, $totalsum);
            }

            $list = $result;//$res['data']['ResultData']['list'];
            foreach ($list as $item => &$value) {
                //顶部统计：机台，平均进入时间
                //$jitai 		.= "<span >".$value['RoomID']."</span><br>";
                //$shichang 	.= "<span >".$value['LoadingSuccCost']/$value['LoadingSuccCnt']."s</span><br>";
                //总耗时 - 首次加载时间  = 非首次加载总耗时
                $value['shichang'] = number_format($value['LoadingSuccCost'] / $value['LoadingSuccCnt'], 3) . "s";
                //总耗时 - 首次加载时间  = 非首次加载总耗时
                $value['fei-time'] = $value['LoadingSuccCost'] - $value['FirstLoadingSuccCost'];
                //总加载次数 - 首次加载次数 = 非首次加载次数
                $value['fei-ci'] = $value['LoadingGameCnt'] - $value['FirstLoadingGameCnt'];
                //非首次加载成功，失败
                $value['fei-ci-succ'] = $value['LoadingSuccCnt'] - $value['FirstLoadingSuccCnt'];
                $value['fei-ci-failed'] = $value['LoadingFailedCnt'] - $value['FirstLoadingFailedCnt'];
            }
            $totalsum['jitai'] = $jitai;
            $totalsum['shichang'] = $shichang;
            return $this->apiReturn(
                $res['code'] = 0,
                $list,
                $res['message'] = '',
                isset($count) ? $count : 0,  //输出查询条数，分页用
                $totalsum  //输出订单总数和总金额
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:玩家行为分析
     */
    public function userloadinggamelogs()
    {
        ///OM_OperationLogsDB.dbo.Proc_UserLoadingGameLogs_Select
        /// 无数据 无法测试
        if ($this->request->isAjax()) {
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-1");
            $enddate = input('enddate') ? input('enddate') : date("Y-m-j");
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            $ProcName = " OM_OperationLogsDB.dbo.Proc_UserLoadingGameLogs_Select "; //存储过程名称
            $base = new UserDB();
            $sqlquery = "EXEC $ProcName '$strartdate','$enddate',$roleId,$roomId,$page,$limit ";

            $list = $base->getTableQuery($sqlquery);//
            $count = count($list);
            $result = $list;
            if (empty($result)) {
                return $this->apiReturn(100, '', '暂无可用数据');
            }
            /*
            汇总机台连接情况
            $result = array('roleid,MachieSerial,new,old,countryCode,DeviceType')
            */
            return $this->apiReturn(
                $res['code'] = 0,
                "",
                $res['message'] = '',
                isset($count) ? $count : 0  //输出查询条数，分页用
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:商品销售统计
     */
    public function salesstatistics()
    {
        if ($this->request->isAjax()) {
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;

            //拼装sql
            $where = ' 1=1 ';
            if ($roleId > 0) {
                $where .= " and  accountid =" . $roleId;
            }
            if ($strartdate != '') {
                $where .= " and AddTime>= '" . $strartdate . " 0:0:0'";
            }
            if ($enddate != '') {
                $where .= " and AddTime<= '" . $enddate . " 23:59:59'";
            }

            $comm = new model\SalesStatistics('CD_DataChangelogsDB');
            $list = $comm->getSalesStatistics($where, 'Id', $page, $limit, 1);

            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            $totalsum['totalsum'] = $list['totalsum'];
            return $this->apiReturn(
                $res['code'] = 0,
                isset($res['data']['list']) ? $res['data']['list'] : [],
                $res['message'] = '',
                $res['total'],  //输出查询条数，分页用
                $totalsum  //输出汇总数据
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:玩家消费监控
     */
    public function playerconsumption()
    {
        if ($this->request->isAjax()) {
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : date("2021-01-01 00:00:00");
            $enddate = input('enddate') ? input('enddate') : date("Y-m-d 23:59:59");
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $where = " AddTime BETWEEN '$strartdate' AND '$enddate' ";

            //拼装sql
//            $where = ' 1=1 ';
            if ($roleId > 0) {
                $where .= " and  accountid =" . $roleId;
            }
            $comm = new model\PlayerConsumption('CD_DataChangelogsDB');
            $list = $comm->getPlayerConsumption($where, 'Id', $page, $limit, 1);

            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            $totalsum['totalsum'] = $list['totalsum'];//"$".
            return $this->apiReturn(
                $res['code'] = 0,
                isset($res['data']['list']) ? $res['data']['list'] : [],
                $res['message'] = '',
                $res['total'],  //输出查询条数，分页用
                $totalsum   //输出汇总数据
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:用户留存情况
     */
    public function userretained()
    {
        if ($this->request->isAjax()) {
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $devicetype = intval(input('devicetype')) ? intval(input('devicetype')) : 0;
            $countrycode = input('countrycode') ? input('countrycode') : '';
            $strartdate = input('strartdate') ? input('strartdate') : date('Y-m-d', strtotime('-6 days'));
            $enddate = input('enddate') ? input('enddate') : date('Y-m-d');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;

            //拼装sql
            $where = ' 1=1 ';
            if ($roleId > 0) {
                $where .= " and  accountid =" . $roleId;
            }
            if ($devicetype > 0) {
                $where .= " and  devicetype =" . $devicetype;
            }
            if ($countrycode != '') {
                $where .= " and  countrycode ='" . $countrycode . "'";
            }
            if ($strartdate != '') {
                $where .= " and registertime>= '" . $strartdate . " 0:0:0'";
            }
            if ($enddate != '') {
                $where .= " and registertime<= '" . $enddate . " 23:59:59'";
            }
            $comm = new model\UserRetained();
            $list = $comm->getUserRetained($where, $strartdate, $enddate, 'registertime', $page, $limit, 1);

            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            $totalsum['totalsum'] = $list['totalsum'];
            return $this->apiReturn(
                $res['code'] = 0,
                isset($res['data']['list']) ? $res['data']['list'] : [],
                $res['message'] = '',
                $res['total'],  //输出查询条数，分页用
                $totalsum   //输出汇总数据
            );
        }
        return $this->fetch();
    }

    /**
     * Notes:用户付费情况
     */
    public function userpayinfo()
    {
    }

    //充值留存率;
    public function PayKeepRate()
    {
        $order = "MyDate desc";
        $start = input('strartdate');
        $end = input('enddate');
        $where = "";
        if ($start != null && $end != null) {
            $where = "And MyDate BETWEEN '$start' And '$end'";
        }
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->PayRate($where, $order);
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->PayRate($where, $order);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'datetime',
                        lang('充值人数') => 'integer',
                        lang('次日留存') => 'string',
                        lang('2日留存') => "string",
                        lang('3日留存') => "string",
                        lang('4日留存') => "string",
                        lang('5日留存') => "string",
                        lang('6日留存') => "string",
                        lang('7日留存') => "string",
                        lang('15日留存') => "string",
                        lang('30日留存') => "string",
                    ];
                    $filename = lang('充值留存').'-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
//        halt($result);
        return $this->fetch();
    }



    //付费留存率;
    public function PayFeeRate()
    {
        $order = "MyDate desc";
        $start = input('strartdate');
        $end = input('enddate');
        $where = "";
        if ($start != null && $end != null) {
            $where = "And MyDate BETWEEN '$start' And '$end'";
        }
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->PayfeeRate($where, $order);
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->PayfeeRate($where, $order);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'datetime',
                        lang('充值人数') => 'integer',
                        lang('次日留存') => 'string',
                        lang('2日留存') => "string",
                        lang('3日留存') => "string",
                        lang('4日留存') => "string",
                        lang('5日留存') => "string",
                        lang('6日留存') => "string",
                        lang('7日留存') => "string",
                        lang('15日留存') => "string",
                        lang('30日留存') => "string",
                    ];
                    $filename = lang('充值留存').'-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
//        halt($result);
        return $this->fetch();
    }


    //註冊流程
    public function userRegRate(){
        $order = "MyDate desc";
        $start = input('strartdate');
        $end = input('enddate');
        $where = "";
        if ($start != null && $end != null) {
            $where = "And mydate BETWEEN '$start' And '$end'";
        }
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->userRegRate($where, $order);
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->userRegRate($where, $order);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0 == 1 && $outAll = true)) {
                    $header_types = [
                        lang('日期') => 'datetime',
                        lang('新增人数') => 'integer',
                        lang('次日留存') => 'string',
                        lang('2日留存') => "string",
                        lang('3日留存') => "string",
                        lang('4日留存') => "string",
                        lang('5日留存') => "string",
                        lang('6日留存') => "string",
                        lang('7日留存') => "string",
                        lang('15日留存') => "string",
                        lang('30日留存') => "string"
                    ];
                    $filename = lang('注册留存').'-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
//        halt($result);
        return $this->fetch();
    }

    //首充留存
    public function remainFirstCharge(){
        $order = "mydate desc";
        $start = input('strartdate');
        $end = input('enddate');
        $where = "";
        if ($start != null && $end != null) {
            $where = "And mydate BETWEEN '$start' And '$end'";
        }
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->userFirstPayRate($where, $order);
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->userFirstPayRate($where, $order);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('日期') => 'datetime',
                        lang('新增人数') => 'integer',
                        lang('次日留存') => 'string',
                        lang('2日留存') => "string",
                        lang('3日留存') => "string",
                        lang('4日留存') => "string",
                        lang('5日留存') => "string",
                        lang('6日留存') => "string",
                        lang('7日留存') => "string",
                        lang('15日留存') => "string",
                        lang('30日留存') => "string",
                    ];
                    $filename = lang('首充留存').'-'.date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }
        return $this->fetch();
    }


    /**付款状态统计*/
    public function PaymentStatusStatistics()
    {
        if ($this->request->isAjax()) {

            $res['code'] = 0;
            return $this->apiJson($res);
        }
        return $this->fetch();
    }


    public function  remainlist(){
//        halt($result);
        return $this->fetch();
    }



    public function  gameDailyMonitor(){
        return $this->fetch();
    }








    
}
