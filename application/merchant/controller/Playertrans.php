<?php

namespace app\merchant\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\AccountDB;
use app\model\BankCode;
use app\model\BankDB;
use app\model\CountryCode;
use app\model\GamePayChannel;
use app\model\MasterDB;
use app\model\PayNotifyLog;
use app\model\UserDB;
use app\model\UserDrawBack;
use app\model\UserProxyInfo;
use app\model\UserWeeklyBonus;
use gdpaid\GDSdk;
use http\Encoding\Stream\Debrotli;
use redis\Redis;
use socket\QuerySocket;
use think\Cache;
use think\Exception;
use fmpay\FmPaySdk;
use think\Request;
use tpay\PaySdk;
use app\model\GameOCDB;
use easypay\PaySdk as EasyPay;
//use goldpay\PaySdk as GoldPay;
use sepropay\PaySdk as SeproPay;

//use aupay\PaySdk as AuPay;
//use doipa\PaySdk as DoiPay;
//use beepay\PaySdk as BeePay;
use payplus\PaySdk as PayPlus;
use inpays\PaySdk as InPay;
use serpay\PaySdk as SerPay;
use indipay\PaySdk as IndiPay;
use wepay\PaySdk as WePay;
use tgpay\PaySdk as TgPay;
use swiftpay\PaySdk as SwiftPay;
use dypay\PaySdk as DyPay;
use ssspay\PaySdk as SssPay;
use epay\PaySdk as EPay;
use fastpay\PaySdk as FastPay;
use xpay\PaySdk as XPay;
use wodeasy\PaySdk as WodEasy;
use hwepay\PaySdk as Hwepay;
use hwpay\PaySdk as Hwpay;
use joypay\PaySdk as Joypay;
use tpays\PaySdk as Tpays;
use winpay\PaySdk as Winpay;
use ydpay\PaySdk as Ydpay;
use xjpay\PaySdk as Xjpay;
use roeasypay\PaySdk as Roeasypay;
use socket\sendQuery;

/**
 * Class Playertrans
 * @package app\admin\controller
 */
class Playertrans extends Main
{

    public function index()
    {

//        $comm = new QuerySocket();
//        $pramArry = [9992298, 0, '100007', 7, 7];
//        $res = $comm->callback("SeMDUserChannelRecharge", $pramArry);
//        $array = unpack("cchars/nint", $res);//解析socket 数据
//        return phpinfo();

    }



    /**
     * 转出记录
     */
    public function record()
    {
        $Channel = $this->GetOutChannelInfo(1);
        $this->assign('channeInfo', $Channel);
        switch (input('Action')) {
            case 'list':
                $userdb = new UserDB();
                $result = $userdb->TViewUserDrawBack($Channel);
                $result['other']['TotalPay'] = $userdb->TotalPay();
                return $this->apiJson($result);
            case 'exec':
                $db = new UserDB();
                $result = $db->TViewUserDrawBack($Channel);
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
                        lang('玩家ID') => 'integer',
                        lang('提现金额') => '0.00',
                        lang('税收') => '0.00',
                        lang('总充值') => '0.00',
                        lang('总提现') => '0.00',
                        lang('邮件补偿') => '0.00',
                        lang('订单状态') => 'string',
                        lang('订单号') => 'string',
                        lang('支出通道') => 'string',
                        lang('银行名称') => 'string',
                        lang('银行卡号') => 'string',
                        lang('操作人员') => 'string',
                        lang('申请时间') => 'datetime',
                        lang('审核时间') => 'datetime',
                    ];
                    $filename = lang('订单管理') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['AccountID'],//玩家ID
                            $row['iMoney'],//提现金额
                            $row['Tax'],//税收
                            $row['totalPay'],//总充值
                            $row['TotalDS'],//总提现
                            $row['EamilMoney'],//邮件补偿
                            $row['orderType'],//订单状态
                            $row['OrderNo'],//订单号
                            $row['ChannelName'],//支出通道
                            $row['BankName'],//支出通道
                            $row['CardNo'],//支出通道
                            $row['checkUser'],//操作人员
                            $row['AddTime'],
                            $row['checkTime'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }
        //操作人员
        $db = new UserDB();
        $checkUser = $db->getTableObject('View_UserDrawBack')->group('checkUser')->column('checkUser');
        $checkUser = array_keys($checkUser);
        if (!in_array(session('username'), $checkUser)) {
            $checkUser[] = session('username');
        }
        $this->assign('checkUser', $checkUser);
        $this->assign('adminuser', session('username'));
        return $this->fetch();
    }


    public function agentCoinApply()
    {

        $userproxyinfo = new UserProxyInfo();

        $action = $this->request->param('action');
        if ($action == 'list') {
            $page = intval(input('page')) ? intval(input('page')) : 1;  //页码
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('roleid', '');
            $start = input('start','');
            $end = input('end','');
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');
            // $where =['CorpType'=>0,'tempdata'=>['>',0]];
            $where = '1=1';
            
            if (!empty($roleid)) {
                $where .= " and roleid=" . $roleid;
            }
            if(!empty($start)){
                $where .= " and addtime >='$start'";
            }

            if(!empty($end)){
                $where .= " and addtime <='$end'";
            }
            $order = 'RoleID desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            if (session('merchant_OperatorId') && request()->module() == 'merchant') {
                $where .= " and OperatorId=".session('merchant_OperatorId');
            }
            $db = new GameOCDB();
           
            $subQuery = "(SELECT RoleID,AddTime,sum(RunningBonus) RunningBonus,sum(InviteBonus) InviteBonus,sum(FirstChargeBonus) FirstChargeBonus FROM [OM_GameOC].[dbo].[T_ProxyDailyBonus](NOLOCK) WHERE " . $where . " GROUP BY [RoleID],AddTime)";
            $data = $db->getTableObject($subQuery)->alias('a')
                ->order($order)
                // ->fetchSql(true)
                // ->select();
                ->paginate($limit)
                ->toArray();
            $list = $data['data'];
            $count = $data['total'];
            foreach ($data['data'] as $key => &$val) {
                $val['RunningBonus'] = FormatMoney($val['RunningBonus'] ?: 0);
                $val['InviteBonus'] = FormatMoney($val['InviteBonus'] ?: 0);
                $val['FirstChargeBonus'] = FormatMoney($val['FirstChargeBonus'] ?: 0);
                $val['TotalProfit'] = round(($val['RunningBonus'] + $val['InviteBonus'] + $val['FirstChargeBonus']), 2);
            }
            if ($action == 'list' && input('output') != 'exec') {
                $other = [
                    
                ];

                return $this->apiReturn(0, $data['data'], 'success', $data['total']);
            }

            if (input('output') == 'exec') {
                if (empty($list)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $data['data'];
                $result['count'] = $data['total'];
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('玩家ID') => 'string',
                        lang('代理投注返佣') => "string",
                        lang('邀请梯度奖励') => "string",
                        lang('代理邀请奖励') => "string",
                        lang('总收益') => 'string',
                    ];
                    $filename = lang('代理奖励列表') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['RoleID'],
                            $row['d1'],
                            $row['d2'],
                            $row['d3'],
                            $row['TotalProfit'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }

        }
        // $where =['CorpType'=>0,'tempdata'=>['>',0]];
        // $unclaimed= $userproxyinfo->getSum($where,'TempData');
        // $Received =$userproxyinfo->getSum(['CorpType'=>0],'ReceiveProfit');

        // $other =[
        //     'unclaimed'=>bcdiv($unclaimed,bl,3),
        //     'Received'=>bcdiv($Received,bl,3)
        // ];
        // $this->assign('data',$other);
        return $this->fetch();
    }

    //查看备注详情
    public function descript()
    {
        if ($this->request->isAjax()) {
            $orderno = input('orderno') ? input('orderno') : '';
            if (!$orderno) {
                return $this->apiReturn(1, [], '参数有误');
            }
            $db = new BankDB();
            $info = $db->getTableRow('UserDrawBack', "OrderNo='$orderno'", '');
            $descInfo['list'] = [];
            array_push($descInfo['list'], $info);
            $descInfo['code'] = 0;
            $descInfo['msg'] = '查询完成';


            return $this->apiJson($descInfo);

//            return $this->apiReturn($descInfo['code'], $descInfo['data'], $descInfo['message']);
        }

        $orderno = input('orderno') ? input('orderno') : '';
        $this->assign('orderno', $orderno);
        return $this->fetch();
    }
}
