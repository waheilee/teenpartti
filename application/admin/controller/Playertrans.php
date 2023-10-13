<?php

namespace app\admin\controller;

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
use think\exception\PDOException;
use think\Request;
use tpay\PaySdk;
use app\model\GameOCDB;
use easypay\PaySdk as EasyPay;

//use goldpay\PaySdk as GoldPay;
use sepropay\PaySdk as SeproPay;
use think\Db;

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
use holidiapay\PaySdk as Holidiapay;
use socket\sendQuery;
use think\Collection;

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
     * 转出申请审核
     */
    public function apply()
    {
        $Channel = $this->GetOutChannelInfo(1);
        if ($this->request->isAjax()) {
            $db = new  UserDB();
            $result = $db->TViewUserDrawBack($Channel);
            return $this->apiJson($result);

        }
        //$this->assign('statdate',date('',))
        $this->assign('channellist', $Channel);
        return $this->fetch();
    }

    //提交到第三方列表
    public function toThree()
    {
        if ($this->request->isAjax()) {
            $OrderNo = input('OrderNo') ? input('OrderNo') : '';
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
            $status = $info['status'];
            //加入工单->提交到第三方列表
            if ($status == 6) {
                //加锁
                $key = 'lock_PayAgree_' . $OrderNo;
                if (!Redis::lock($key)) return $this->apiReturn(1, [], '请勿重复操作');
                // Redis::set($key,5);
                $status = 1;
                $data = ['status' => $status, 'IsDrawback' => $status, 'checkTime' => date('Y-m-d H:i:s')];
                //修改订单状态
                $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                //解除锁
                Redis::rm($key);
                $msg = lang('审核成功');
                //log记录
                GameLog::logData(__METHOD__, [$OrderNo, $msg], 1, $msg);
                return $this->apiReturn(0, [], $msg);
            }
        }
        $channel_list = $this->GetOutChannelInfo(1);
        $this->assign('channellist', $channel_list);

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

    //提交到第三方列表
    public function oneKeyToThree()
    {
        if ($this->request->isAjax()) {
            $OrderNos = explode(',', input('OrderNo'));
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            $success_num = 0;
            $error_num = 0;
            $res_data = [];
            foreach ($OrderNos as $k => $OrderNo) {
                $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
                $status = $info['status'];
                //加入工单->提交到第三方列表
                if ($status == 6) {
                    //加锁
                    $key = 'lock_PayAgree_' . $OrderNo;
                    if (!Redis::lock($key)) {
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => lang('重复操作')
                        ];
                        continue;
                    }
                    // Redis::set($key,5);
                    $status = 1;
                    $data = ['status' => $status, 'IsDrawback' => $status, 'checkTime' => date('Y-m-d H:i:s')];
                    //修改订单状态
                    $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                    //解除锁
                    Redis::rm($key);
                    $msg = lang('审核成功');
                    //log记录
                    GameLog::logData(__METHOD__, [$OrderNo, $msg], 1, $msg);
                    $success_num += 1;
                } else {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => lang('异常状态')
                    ];
                    continue;
                }
            }
            return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
        }
        $channel_list = $this->GetOutChannelInfo(1);
        $this->assign('channellist', $channel_list);

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

    public function applyPass()
    {
        $channel_list = $this->GetOutChannelInfo(1);
        if ($this->request->isAjax()) {
            $db = new  UserDB();
            $result = $db->TViewUserDrawBack($channel_list);
            $db = new GameOCDB();
            foreach ($result['list'] as $key => &$val) {
                $comment = $db->getTableObject('T_PlayerComment')->where('roleid', $val['AccountID'])->where('type', 1)->order('opt_time desc')->find();
                $val['comment'] = $comment['comment'] ?? '';
            }
            return $this->apiJson($result);
        }
        $this->assign('channellist', $channel_list);

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

    //仅用于展示服
    public function applyDirectPass()
    {
        $channel_list = $this->GetOutChannelInfo();
        if ($this->request->isAjax()) {
            $db = new  UserDB();
            $result = $db->TViewUserDrawBack($channel_list);
            $db = new GameOCDB();
            foreach ($result['list'] as $key => &$val) {
                $comment = $db->getTableObject('T_PlayerComment')->where('roleid', $val['AccountID'])->where('type', 1)->order('opt_time desc')->find();
                $val['comment'] = $comment['comment'] ?? '';
            }
            return $this->apiJson($result);
        }
        $this->assign('channellist', $channel_list);

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

    /*
        public function testfinish()
        {
            $orderid = input('orderno', '');
            $UserDrawBack = new UserDrawBack('userdrawback');
            $draw = $UserDrawBack->GetRow(['OrderNo' => $orderid], '*');
            if (!$draw) {
                return $this->apiReturn(100, '', '该提现订单不存在');
            }
    //        if ($draw['checkUser'] != session('username')) {
    //            return $this->apiReturn(100, '', '权限不足');
    //        }
            if (intval($draw['AccountID']) === 0) {
                return $this->apiReturn(100, '', '该提现订单玩家id为0，无法处理');
            }

            $draw['RealMoney'] = FormatMoney($draw['iMoney'] - $draw['Tax']);
            $sendQuery = new sendQuery();
            $realmoney = FormatMoney($draw['RealMoney']);
            $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$draw['AccountID'], 1, $orderid, $realmoney, $draw['RealMoney']]);
            $code = unpack("LCode", $res)['Code'];

            $save_data = [
                'status' => 100,
                'IsDrawback' => 100,
                'TransactionNo' => '',
                'UpdateTime' => date('Y-m-d H:i:s', time())
            ];
            $UserDrawBack->table = 'userdrawback';
            $UserDrawBack->UPData($save_data, ['OrderNo' => $orderid]);
            return $this->apiReturn(0, '', '订单更新成功');
        }
    */


    public function channelOutManger()
    {
        return $this->fetch();
    }

    /**
     * @return mixed
     */
    public function ChannelPayManger()
    {
        $request = request()->request();//获取request 数据
        $request['Status'] = isset($request['Status']) ? 1 : 0;//switch 赋值
        unset($request['s'], $request['Action'], $request['ID']);//删除表没有的元素
        $type = input('Type');
        $this->assign('Type', $type);

        //$this->assign('CountryCode', $this->getCountryCode());
        switch (input('Action')) {
            case 'list':
                $DB = new MasterDB();
                $ChannelId = intval(input('ChannelId'));
                $status = input('status', -1);
                $ChannelType = input('ChannelType', -1);
                $where = "";
                if ($ChannelId > 0) $where .= " AND ChannelId=$ChannelId";//
                if ($status > -1) $where .= " AND Status=$status";//
                if (!empty($ChannelType) && $ChannelType > -1) $where .= " AND ChannelType=$ChannelType";//
                if (!IsNullOrEmpty($type)) $where .= " AND Type=$type";

                $orderfield = input('orderfield', 'id');
                $ordertype = input('ordertype', 'desc');

                if (empty($orderfield)) {
                    $orderfield = 'id';
                }

                if (empty($ordertype)) {
                    $ordertype = 'desc';
                }
                $result = $DB->TGamePayChannel()->GetPage($where, "$orderfield $ordertype", '*', true);
                foreach ($result['list'] as $key => &$val) {
                    $val['ChannelCode'] = json_decode($val['MerchantDetail'], true)['code'] ?? 0;
                }
                return $this->apiJson($result);
            case 'edit'://编辑
                $DB = new MasterDB('T_GamePayChannel');
                $data["Id"] = intval(input('ID')) ? intval(input('ID')) : 0;
                //view
                if (request()->isGet()) {
                    $result = $DB->TGamePayChannel()->GetRow($data);
                    $this->assign('action', 'edit');
                    $this->assign('data', $result);

                    $gamepaychannel = new GamePayChannel();
                    $channelcode = $gamepaychannel->getListAll([], 'distinct(ChannelCode) as ChannelCode');
                    $this->assign('codelist', $channelcode);

                    $detail = json_decode($result['MerchantDetail'], true);
                    $detail_list = [];
                    foreach ($detail as $k => $v) {
                        $temp = [
                            'key' => $k,
                            'value' => $v
                        ];
                        array_push($detail_list, $temp);
                    }

                    $this->assign('detaillist', json_encode($detail_list));
                    return $this->fetch('pay_channel_edit');
                } //update
                else {
                    $request['CountryCode'] = '';
                    $row = $DB->TGamePayChannel()->UPData($request, $data);
                    GameLog::logData(__METHOD__, ['action' => lang('修改充值渠道'), $request], ($row > 0) ? 1 : 0);
                    if ($row > 0) {
                        Cache::rm($this->channelInfoKey);
                        Cache::rm($this->channelInfoKeyALL);
                        return $this->success('更新成功');
                    }
                    return $this->error('更新失败');
                }
                break;
            case 'add' ://添加
                //view
                if (request()->isGet()) {
                    $this->assign('action', 'add');
                    $this->assign('data', ['Id' => '', 'ChannelId' => '', 'ChannelName' => '', 'NoticeUrl' => '',
                            'MinMoney' => '', 'MaxMoney' => '', 'channelcode' => -1, 'Status' => 1, 'SortID' => '', 'Type' => $type, 'CountryCode' => '']
                    );
                    $gamepaychannel = new GamePayChannel();
                    $channelcode = $gamepaychannel->getListAll([], 'distinct(ChannelCode) as ChannelCode');
                    $this->assign('codelist', $channelcode);
                    return $this->fetch('pay_channel_edit');
                } //add
                else {
                    $DB = new MasterDB('T_GamePayChannel');
                    try {
                        $request['CountryCode'] = '';
                        $row = $DB->TGamePayChannel()->Insert($request);
                    } catch (Exception $exception) {
                        return $this->error('渠道ID已存在,换个别的试试吧');
                    }
                    GameLog::logData(__METHOD__, ['action' => lang('添加充值渠道'), $request], ($row > 0) ? 1 : 0);
                    Cache::rm($this->channelInfoKey);
                    Cache::rm($this->channelInfoKeyALL);
                    if ($row > 0) return $this->success('添加成功');
                    return $this->error('添加失败');
                }
                break;
            case 'copy'://编辑
                $DB = new MasterDB('T_GamePayChannel');
                $data["Id"] = intval(input('ID')) ? intval(input('ID')) : 0;
                //view
                if (request()->isGet()) {
                    $result = $DB->TGamePayChannel()->GetRow($data);
                    $this->assign('action', 'copy');
                    $result['ChannelId'] = '';
                    $this->assign('data', $result);

                    $gamepaychannel = new GamePayChannel();
                    $channelcode = $gamepaychannel->getListAll([], 'distinct(ChannelCode) as ChannelCode');
                    $this->assign('codelist', $channelcode);

                    $detail = json_decode($result['MerchantDetail'], true);
                    $detail_list = [];
                    foreach ($detail as $k => $v) {
                        $temp = [
                            'key' => $k,
                            'value' => $v
                        ];
                        array_push($detail_list, $temp);
                    }

                    $this->assign('detaillist', json_encode($detail_list));
                    return $this->fetch('pay_channel_edit');
                } //update
                else {
                    $id = input('ID');
                    $gamepaychannel = new GamePayChannel();
                    $oldinfo = $gamepaychannel->getRowById($id);
                    $DB = new MasterDB('T_GamePayChannel');
                    try {
                        $request['CountryCode'] = '';
                        $request['MerchantDetail'] = $oldinfo['MerchantDetail'];
                        $row = $DB->TGamePayChannel()->Insert($request);
                    } catch (Exception $exception) {
                        return $this->error('渠道ID已存在,换个别的试试吧');
                    }
                    GameLog::logData(__METHOD__, ['action' => lang('添加充值渠道'), $request], ($row > 0) ? 1 : 0);
                    Cache::rm($this->channelInfoKey);
                    Cache::rm($this->channelInfoKeyALL);
                    if ($row > 0) return $this->success('添加成功');
                    return $this->error('添加失败');
                }
                break;
            case 'del' ://删除
                $DB = new MasterDB('T_GamePayChannel');
                $row = $DB->delTableRow("T_GamePayChannel", ["Id" => intval(input('ID'))]);
                GameLog::logData(__METHOD__, ['action' => '删除支付渠道', ['ID' => input('ID')]], ($row > 0) ? 1 : 0);
                Cache::rm($this->channelInfoKey);
                Cache::rm($this->channelInfoKeyALL);
                if ($row > 0) return $this->success('删除成功');
                return $this->error('删除失败');
                break;
        }
        return $this->fetch();
    }


    public function BankManager()
    {
        $request = request()->request();//获取request 数据
        unset($request['s'], $request['Action'], $request['ID']);//删除表没有的元素
        //$this->assign('CountryCode', json_encode($this->getCountryCode()));
        switch (input('Action')) {
            case 'list':
                $DB = new MasterDB();
                $where = "";
                $result = $DB->TBankCode()->GetPage($where, 'id desc', '*', true);
                return $this->apiJson($result);
            case 'edit'://编辑
                $DB = new MasterDB();
                $data["ID"] = intval(input('ID')) ? intval(input('ID')) : 0;
                $row = $DB->TBankCode()->UPData($request, $data);
                GameLog::logData(__METHOD__, ['action' => lang('修改充值渠道'), $request], ($row > 0) ? 1 : 0);
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');

            case 'add' ://添加
                $DB = new MasterDB();
                $row = $DB->TBankCode()->Insert($request);
//                    GameLog::logData(__METHOD__, ['action' => '添加充值渠道', $request], ($row > 0) ? 1 : 0);
//                    Cache::rm($this->channelInfoKey);
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');

            case 'del' ://删除
                $ID = (int)input('ID');
                $DB = new MasterDB('T_BankCode');
                $row = $DB->TBankCode()->DeleteRow("Id=" . $ID);
                GameLog::logData(__METHOD__, ['action' => '删除支付渠道', ['ID' => input('ID')]], ($row > 0) ? 1 : 0);
                Cache::rm($this->channelInfoKey);
                Cache::rm($this->channelInfoKeyALL);
                if ($row > 0) return $this->success('删除成功');
                return $this->error('删除失败');
            case 'CountryCode':
                $data['ID'] = (int)input('ID');
                $DB = new MasterDB();
                $row = $DB->TBankCode()->UPData($request, $data);
                if ($row > 0) return $this->success();
        }
        return $this->fetch();
    }


    public function addbank()
    {

        if ($this->request->isAjax()) {
            $bankno = input('BankNo', '');
            $bankname = input('BankName', '');
            $countrycode = input('CountryCode', '');

            if (empty($bankno) || empty($bankname)) {
                return $this->apiReturn(100, '', '请完整填写表单');
            }
            $bankcode = new BankCode();
            $savedata = [
                'BankNo' => $bankno,
                'BankName' => $bankname,
                'CountryCode' => $countrycode
            ];
            $ret = $bankcode->add($savedata);
            if ($ret) {
                return $this->apiReturn(0, '', '数据提交成功');
            } else {
                return $this->apiReturn(100, '', '数据提交失败');
            }

        }
        $coutryconfig = new  CountryCode();
        $result = $coutryconfig->getListAll([], '', 'sortid');
        $this->assign('country', $result);
        return $this->fetch();
    }


    /**
     * 财务审核
     */
    public function finance()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $start = input('start') ? input('start') : '';
            $end = input('end') ? input('end') : '';
            $payway = intval(input('payway')) > 0 ? intval(input('payway')) : 0;
            $realname = input('realname') ? input('realname') : '';
            $data = ['page' => $page, 'pagesize' => $limit, 'roleid' => $roleId, 'startdate' => $start, 'enddate' => $end, 'OperateVerifyType' => 1, 'payway' => $payway, 'realname' => $realname];
            $res = Api::getInstance()->sendRequest($data, 'charge', 'outlist');
            if (isset($res['data']['list'])) {
                foreach ($res['data']['list'] as &$v) {
                    //$v['totaltax']       = config('site.tax')['tax'] * $v['totalmoney'] / 100;
                    $v['totalmoney'] /= 1000;
                    $v['tax'] /= 1000;
                    $v['withdrawstatus'] = config('site.withdraw');//自动/手动提现配置
                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $count = $res['total'], ['alltotal' => isset($res['data']['total']) ? $res['data']['total'] : 0]);

        }

        return $this->fetch();
    }


    //提交第三方
    public function thirdPay()
    {
        $userID = intval(input('UserID')) ? intval(input('UserID')) : 0;
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';
        save_log('playertrans', '提交三方参数' . '---用户ID:' . $userID . '---订单号:' . $OrderNo . '---通道id' . input('channelid') . '---审核人员' . input('checkUser'));
        if ($this->request->isAjax()) {
            try {
                $cacheOrderNo = Redis::get('PAYOUT_ORDER_NUMBER_'.$OrderNo);
                if($cacheOrderNo){
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '订单重复操作过于频繁！'
                    ];
                    save_log('mkcpay', 'function thirdPay----操作过于频繁:' . $OrderNo);
                    return $this->apiReturn(0, $res_data, '操作失败！');
                }
                $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
                if (!$channelid || $channelid <= 0) {
                    return $this->apiReturn(100, '', '提现通道未选择');
                }
                $UserDrawBack = new UserDrawBack('userdrawback');
                $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
                if (!$draw) {
                    return $this->apiReturn(100, '', '该提现订单不存在');
                }
                $draw['checkUser'] = input('checkUser') ?: $draw['checkUser'];
                if ($draw['checkUser'] != session('username')) {
                    return $this->apiReturn(100, '', '权限不足');
                }
                if (intval($draw['AccountID']) === 0) {
                    return $this->apiReturn(100, '', '该提现订单玩家id为0，无法处理');
                }
                $OperatorId = (new AccountDB())->getTableObject('T_Accounts')->where('AccountID', $draw['AccountID'])->value('OperatorId');
                if ($OperatorId > 0) {
                    $WithdrawRemain = (new MasterDB())->getTableObject('T_OperatorLink')->where('OperatorId', $OperatorId)->value('WithdrawRemain') ?: 0;
                    if ($WithdrawRemain <= 0) {
                        return $this->apiReturn(100, '', '玩家所属运营商额度不足，无法出款');
                    }
                }
                $draw['RealMoney'] = FormatMoney($draw['iMoney'] - $draw['Tax']);
                $db = new MasterDB();
                $channel = $db->getTableRow('T_GamePayChannel', ['ChannelId' => $channelid], '*');
                $config = json_decode($channel['MerchantDetail'], true);
                $extra = json_encode(['channelid' => $channelid]);
                $channelcode = strtolower(trim($channel['ChannelCode']));
                $result = [];
                switch ($channelcode) {
                    case 'tpay9':
                        $tpay9 = new PaySdk();
                        $result = $tpay9->payout($OrderNo, $draw, $config);
                        break;

                    case 'fmpay':
                        $fmpay = new FmPaySdk();
                        $result = $fmpay->payout($OrderNo, $draw, $config);
                        break;

                    case 'easypay':
                        $easypay = new EasyPay();
                        $result = $easypay->payout($OrderNo, $draw, $config);
                        break;

                    case 'goldpays':
                        $goldpay = new GoldPay();
                        $result = $goldpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'sepro':
                        $sepropay = new SeproPay();
                        $bankcode_model = new BankCode();
                        $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode');
                        if (empty($bccode)) {
                            return $this->apiReturn(100, '', '银行没有对应编码');
                        }
                        $result = $sepropay->payout($OrderNo, $draw, $config, $bccode);
                        break;
                    case 'gdpaid':
                        $gdpay = new GDSdk();
                        $bankcode_model = new BankCode();
                        $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankNo');
                        $result = $gdpay->payout($OrderNo, $draw, $config, $bccode);
                        break;
                    case 'aupay':
                        $aupay = new AuPay();
                        $result = $aupay->payout($OrderNo, $draw, $config);
                        break;
                    case 'doipay':
                        $doipay = new DoiPay();
                        $result = $doipay->payout($OrderNo, $draw, $config);
                        break;

                    case 'beepay':
                        $beepay = new BeePay();
                        $result = $beepay->payout($OrderNo, $draw, $config);
                        break;
                    case 'payplus':
                        $payplus = new PayPlus();
                        $result = $payplus->payout($OrderNo, $draw, $config);
                        break;

                    case 'inpay':
                        $inpay = new InPay();
                        $result = $inpay->payout($OrderNo, $draw, $config);
                        break;

                    case 'serpay':
                        $serpay = new SerPay();
                        $bankcode_model = new BankCode();
                        $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode2');
                        if (empty($bccode)) {
                            return $this->apiReturn(100, '', '银行没有对应编码');
                        }
                        $result = $serpay->payout($OrderNo, $draw, $config, $bccode);
                        break;

                    case 'wepay':
                        $wepay = new WePay();
//                        $bankcode_model = new BankCode();
                        $bccode = '';//$bankcode_model->getValue(['BankName'=>$draw['BankName']],'BankCode');
//                        if(empty($bccode)){
//                            return $this->apiReturn(100, '', '银行没有对应编码');
//                        }
                        $result = $wepay->payout($OrderNo, $draw, $config, $bccode);
                        break;

                    case 'tgpay':
                        $tgpay = new TgPay();
                        // $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                        if (config('app_type') == 2) {
                            $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                        } elseif (config('app_type') == 3) {
                            $result = $tgpay->payoutPhp($OrderNo, $draw, $config);
                        } else {
                            $result = $tgpay->payout($OrderNo, $draw, $config);
                        }
                        break;
                    case 'dypay':
                        $dypay = new DyPay();
                        $result = $dypay->payout($OrderNo, $draw, $config);
                        break;

                    case 'ssspay':
                        $ssspay = new SssPay();
                        $result = $ssspay->payout($OrderNo, $draw, $config);
                        break;
                    case 'swiftpay':
                        $swiftpay = new SwiftPay();
                        if (config('app_type') == 2) {
                            $result = $swiftpay->payoutBrazil($OrderNo, $draw, $config);
                        } else {
                            $result = $swiftpay->payout($OrderNo, $draw, $config);
                        }
                        break;
                    case 'epay':
                        $epay = new EPay();
                        $result = $epay->payout($OrderNo, $draw, $config);
                        break;
                    case 'fastpay':
                        $fastpay = new FastPay();
                        $result = $fastpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'xpay':
                        $xpay = new XPay();
                        $result = $xpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'wodeasy':
                        $wodeasy = new WodEasy();
//                        $bccode='';
//                        if($draw['PayWay']==2){
//                            $bankcode_model = new BankCode();
//                            $bccode = $bankcode_model->getValue(['BankName'=>$draw['BankName']],'BankCode3');
//                            if(empty($bccode)){
//                                return $this->apiReturn(100, '', '银行没有对应编码');
//                            }
//                        }
                        $result = $wodeasy->payout($OrderNo, $draw, $config);
                        break;
                    case 'hwepay':
                        $hwepay = new Hwepay();
                        $result = $hwepay->payout($OrderNo, $draw, $config);
                        break;
                    case 'hwpay':
                        $hwpay = new Hwpay();
                        $result = $hwpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'joypay':
                        $joypay = new Joypay();
                        $result = $joypay->payout($OrderNo, $draw, $config);
                        break;
                    case 'tpays':

                        $tpays = new Tpays();
                        $result = $tpays->payout($OrderNo, $draw, $config);
                        break;
                    case 'indipay':
                        $indipay = new IndiPay();
                        $bccode = '';
                        if ($draw['PayWay'] == 2) {
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode3');
                            if (empty($bccode)) {
                                return $this->apiReturn(100, '', '银行没有对应编码');
                            }
                        }
                        $result = $indipay->payout($OrderNo, $draw, $config, $bccode);
                        break;
                    case 'winpay':

                        $winpay = new Winpay();
                        $result = $winpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'ydpay':

                        $ydpay = new Ydpay();
                        $result = $ydpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'xjpay':

                        $xjpay = new Xjpay();
                        $result = $xjpay->payout($OrderNo, $draw, $config);
                        break;
                    case 'roeasypay':

                        $roeasypay = new Roeasypay();
                        $result = $roeasypay->payout($OrderNo, $draw, $config);
                        break;
                    case 'holidiapay':

                        $holidiapay = new Holidiapay();
                        $result = $holidiapay->payout($OrderNo, $draw, $config);
                        break;
                    default:
                        $class = '\\' . strtolower($channelcode) . '\PaySdk';
                        $pay = new $class();
                        Redis::set('PAYOUT_ORDER_NUMBER_'.$OrderNo,$OrderNo,30);
                        $result = $pay->payout($OrderNo, $draw, $config);
                        break;
                }

                if ($result['status']) {
                    $bankM = new BankDB();
                    $post_data = [
                        'ChannelId' => $channelid,
                        'TransactionNo' => $result['system_ref'],
                        'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                        'IsDrawback' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                        'UpdateTime' => date('Y-m-d H:i:s', time())
                    ];
                    $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_AUDIT_PASS]);
                    if (!$ret) {
                        return $this->apiReturn(100, '', '更新订单出错');
                    }
                    GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, lang('提交第三方成功')], 1, lang('提交第三方成功'));
                    return $this->apiReturn(0, '', 'success');
                } else {
                    GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, $result['message']], 1, $result['message']);
                    return $this->apiReturn(100, '', $result['message']);
                }
            } catch (\Exception $ex) {
                save_log('playertrans', $ex->getMessage() . $ex->getTraceAsString());
                return $this->apiReturn(100, '', $ex->getMessage());
            }
        }
        $GamePayChannel = new GamePayChannel();
        $db = new AccountDB();
        $UserCode = $db->TAccounts()->GetRow("AccountID=$userID", 'CountryCode')['CountryCode'];
        $list = $GamePayChannel->getListAll(['type' => 1, 'Status' => 1], '*');//, 'CountryCode' => $UserCode
        $this->assign('list', $list);
        $this->assign('orderno', $OrderNo);
        return $this->fetch();
    }


    //一键提交第三方
    public function onekeyThirdPay()
    {
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';

        if ($this->request->isAjax()) {

            try {

                $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
                if (!$channelid) {
                    return $this->apiReturn(100, '', '提现通道未选择');
                }
                $OrderNos = explode(',', input('OrderNo'));
                $UserDrawBack = new UserDrawBack('userdrawback');
                $success_num = 0;
                $error_num = 0;
                $res_data = [];
                foreach ($OrderNos as $key => &$OrderNo) {
                    $cacheOrderNo = Redis::get('PAYOUT_ORDER_NUMBER_'.$OrderNo);
                    if($cacheOrderNo){
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => '订单重复操作过于频繁！'
                        ];
                        save_log('mkcpay', 'function onekeyThirdPay操作过于频繁:' . $OrderNo);
                        continue;
//                        return $this->apiReturn(0, $res_data, '操作失败！');
                    }
                    $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
                    if (!$draw) {
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => '该提现订单不存在'
                        ];
                        continue;
                    }
                    if ($draw['checkUser'] != session('username')) {
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => '权限不足'
                        ];
                        continue;
                    }
                    if (intval($draw['AccountID']) === 0) {
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => '该提现订单玩家id为0，无法处理'
                        ];
                        continue;
                    }
                    $userID = $draw['AccountID'];
                    $draw['RealMoney'] = FormatMoney($draw['iMoney'] - $draw['Tax']);
                    $db = new MasterDB();
                    $channel = $db->getTableRow('T_GamePayChannel', ['ChannelId' => $channelid], '*');
                    $config = json_decode($channel['MerchantDetail'], true);
                    $extra = json_encode(['channelid' => $channelid]);
                    $channelcode = $channel['ChannelCode'];
                    $result = [];
                    switch ($channelcode) {
                        case 'tpay9':
                            $tpay9 = new PaySdk();
                            $result = $tpay9->payout($OrderNo, $draw, $config);
                            break;

                        case 'fmpay':
                            $fmpay = new FmPaySdk();
                            $result = $fmpay->payout($OrderNo, $draw, $config);
                            break;

                        case 'easypay':
                            $easypay = new EasyPay();
                            $result = $easypay->payout($OrderNo, $draw, $config);
                            break;

                        case 'goldpays':
                            $goldpay = new GoldPay();
                            $result = $goldpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'sepro':
                            $sepropay = new SeproPay();
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode');
                            if (empty($bccode)) {
                                $error_num += 1;
                                $res_data[] = [
                                    'OrderNo' => $OrderNo,
                                    'msg' => '银行没有对应编码'
                                ];
                            }
                            $result = $sepropay->payout($OrderNo, $draw, $config, $bccode);
                            break;
                        case 'gdpaid':
                            $gdpay = new GDSdk();
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankNo');
                            $result = $gdpay->payout($OrderNo, $draw, $config, $bccode);
                            break;
                        case 'aupay':
                            $aupay = new AuPay();
                            $result = $aupay->payout($OrderNo, $draw, $config);
                            break;
                        case 'doipay':
                            $doipay = new DoiPay();
                            $result = $doipay->payout($OrderNo, $draw, $config);
                            break;

                        case 'beepay':
                            $beepay = new BeePay();
                            $result = $beepay->payout($OrderNo, $draw, $config);
                            break;
                        case 'payplus':
                            $payplus = new PayPlus();
                            $result = $payplus->payout($OrderNo, $draw, $config);
                            break;

                        case 'inpay':
                            $inpay = new InPay();
                            $result = $inpay->payout($OrderNo, $draw, $config);
                            break;

                        case 'serpay':
                            $serpay = new SerPay();
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode2');
                            if (empty($bccode)) {
                                $error_num += 1;
                                $res_data[] = [
                                    'OrderNo' => $OrderNo,
                                    'msg' => '银行没有对应编码'
                                ];
                            }
                            $result = $serpay->payout($OrderNo, $draw, $config, $bccode);
                            break;

                        case 'wepay':
                            $wepay = new WePay();
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode');
                            if (empty($bccode)) {
                                $error_num += 1;
                                $res_data[] = [
                                    'OrderNo' => $OrderNo,
                                    'msg' => '银行没有对应编码'
                                ];
                            }
                            $result = $wepay->payout($OrderNo, $draw, $config, $bccode);
                            break;

                        case 'tgpay':
                            $tgpay = new TgPay();
                            // $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                            if (config('app_type') == 2) {
                                $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                            } elseif (config('app_type') == 3) {
                                $result = $tgpay->payoutPhp($OrderNo, $draw, $config);
                            } else {
                                $result = $tgpay->payout($OrderNo, $draw, $config);
                            }
                            break;
                        case 'dypay':
                            $dypay = new DyPay();
                            $result = $dypay->payout($OrderNo, $draw, $config);
                            break;

                        case 'ssspay':
                            $ssspay = new SssPay();
                            $result = $ssspay->payout($OrderNo, $draw, $config);
                            break;
                        case 'swiftpay':
                            $swiftpay = new SwiftPay();
                            if (config('app_type') == 2) {
                                $result = $swiftpay->payoutBrazil($OrderNo, $draw, $config);
                            } else {
                                $result = $swiftpay->payout($OrderNo, $draw, $config);
                            }
                            break;
                        case 'epay':
                            $epay = new EPay();
                            $result = $epay->payout($OrderNo, $draw, $config);
                            break;
                        case 'fastpay':
                            $fastpay = new FastPay();
                            $result = $fastpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'xpay':
                            $xpay = new XPay();
                            $result = $xpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'wodeasy':
                            $wodeasy = new WodEasy();
                            $result = $wodeasy->payout($OrderNo, $draw, $config);
                            break;
                        case 'hwepay':
                            $hwepay = new Hwepay();
                            $result = $hwepay->payout($OrderNo, $draw, $config);
                            break;
                        case 'hwpay':
                            $hwpay = new Hwpay();
                            $result = $hwpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'joypay':
                            $joypay = new Joypay();
                            $result = $joypay->payout($OrderNo, $draw, $config);
                            break;
                        case 'tpays':
                            $tgpay = new TgPay();
                            // $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                            if (config('app_type') == 2) {
                                $result = $tgpay->payoutBrazil($OrderNo, $draw, $config);
                            } elseif (config('app_type') == 3) {
                                $result = $tgpay->payoutPhp($OrderNo, $draw, $config);
                            } else {
                                $result = $tgpay->payout($OrderNo, $draw, $config);
                            }
                            break;
                        case 'indipay':
                            $indipay = new IndiPay();
                            $bankcode_model = new BankCode();
                            $bccode = $bankcode_model->getValue(['BankName' => $draw['BankName']], 'BankCode2');
                            if (empty($bccode)) {
                                $error_num += 1;
                                $res_data[] = [
                                    'OrderNo' => $OrderNo,
                                    'msg' => '银行没有对应编码'
                                ];
                            }
                            $result = $indipay->payout($OrderNo, $draw, $config, $bccode);
                            break;
                        case 'Winpay':
                            $Winpay = new Winpay();
                            $result = $Winpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'ydpay':

                            $ydpay = new Ydpay();
                            $result = $ydpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'xjpay':

                            $xjpay = new Xjpay();
                            $result = $xjpay->payout($OrderNo, $draw, $config);
                            break;
                        case 'roeasypay':

                            $roeasypay = new Roeasypay();
                            $result = $roeasypay->payout($OrderNo, $draw, $config);
                            break;
                        case 'holidiapay':

                            $holidiapay = new Holidiapay();
                            $result = $holidiapay->payout($OrderNo, $draw, $config);
                            break;
                        default:
                            $class = '\\' . strtolower($channelcode) . '\PaySdk';
                            $pay = new $class();
                            Redis::set('PAYOUT_ORDER_NUMBER_'.$OrderNo,$OrderNo,30);
                            $result = $pay->payout($OrderNo, $draw, $config);
                            break;
                    }
                    if ($result['status']) {
                        $bankM = new BankDB();
                        $post_data = [
                            'ChannelId' => $channelid,
                            'TransactionNo' => $result['system_ref'],
                            'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                            'IsDrawback' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                            'UpdateTime' => date('Y-m-d H:i:s', time())
                        ];
                        $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_AUDIT_PASS]);
                        if (!$ret) {
                            $error_num += 1;
                            $res_data[] = [
                                'OrderNo' => $OrderNo,
                                'msg' => '更新订单出错'
                            ];
                            continue;
                        }
                        $success_num += 1;
                        GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, lang('提交第三方成功')], 1, lang('提交第三方成功'));

                    } else {
                        GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, $result['message']], 1, $result['message']);
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => $result['message']
                        ];
                        continue;
                    }
                }
                return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
            } catch (\Exception $ex) {
                save_log('playertrans', $ex->getMessage() . $ex->getTraceAsString());
                return $this->apiReturn(100, '', $ex->getMessage());
            }
        }
        $GamePayChannel = new GamePayChannel();
        $list = $GamePayChannel->getListAll(['type' => 1, 'Status' => 1], '*');//, 'CountryCode' => $UserCode
        $this->assign('list', $list);
        $this->assign('orderno', $OrderNo);
        return $this->fetch();
    }

    /**
     * 同意退款
     */
    public function agree()
    {
        $status = intval(input('status')) ? intval(input('status')) : 0;
        $userID = (int)input('UserID', 0);
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';
        $description = input('description') ? input('description') : '';
        $checkUser = input('checkUser') ? input('checkUser') : session('username');
        $Money = 0;
        if ($this->request->isAjax()) {
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
            $status = $info['status'];
            //未审核->加入工单准备放款审核
            if ($status == 0) {
                //加锁
                $key = 'lock_PayAgree_' . $OrderNo;
                if (!Redis::lock($key)) return $this->apiReturn(1, [], '请勿重复操作');
                // Redis::set($key,5);
                $status = 6;
                $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => $checkUser, 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                //修改订单状态
                $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                //解除锁
                Redis::rm($key);
                $msg = lang('成功加入工单');
                //log记录
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $msg], 1, $msg);
                return $this->apiReturn(0, [], $msg);
            } else {
                $msg = lang('加入工单失败');
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $msg], 1, $msg);
                return $this->apiReturn(1, [], $msg);
            }
        }
        $this->assign('UserID', $userID);
        $this->assign('OrderNo', $OrderNo);
        $this->assign('status', $status);
        $this->assign('Money', $Money);
        // $this->assign('checkUser', session('username'));
        //操作人员
        $db = new UserDB();
        // $checkUser = $db->getTableObject('View_UserDrawBack')->group('checkUser')->column('checkUser');
        // $checkUser = array_keys($checkUser);
        $checkUser = $this->getAdminUser();
        $this->assign('checkUser', $checkUser);
        $this->assign('adminuser', session('username'));
        $this->assign('description', IsNullOrEmpty(input('description')) ? input('description') : '');
        return $this->fetch();
    }

    //获取有权限的管理员列表
    public function getAdminUser()
    {
        $group_ids = Db::table('game_auth_group')->where('rules', 'like', '%,41')->whereOr('rules', 'like', '%,41,%')->whereOr('rules', 'like', '41,%')->column('id');
        $admin_ids = Db::table('game_auth_group_access')->where('group_id', 'in', $group_ids)->column('uid');
        $checkuser = Db::table('game_user')->where('id', 'in', $admin_ids)->column('username');
        return $checkuser;

    }

    public function onekeyAgree()
    {
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';
        $description = input('description') ? input('description') : '';
        $checkUser = input('checkUser') ? input('checkUser') : session('username');
        $Money = 0;
        if ($this->request->isAjax()) {
            $OrderNos = explode(',', input('OrderNo'));
            $success_num = 0;
            $error_num = 0;
            $res_data = [];
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            foreach ($OrderNos as $k => $OrderNo) {
                $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
                $status = $info['status'];
                $userID = $info['AccountID'];
                //未审核->加入工单准备放款审核
                if ($status == 0) {
                    //加锁
                    $key = 'lock_PayAgree_' . $OrderNo;
                    if (!Redis::lock($key)) {
                        $error_num += 1;
                        $res_data[] = [
                            'OrderNo' => $OrderNo,
                            'msg' => lang('重复操作')
                        ];
                        continue;
                    }
                    // Redis::set($key,5);
                    $status = 6;
                    $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => $checkUser, 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                    //修改订单状态
                    $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                    //解除锁
                    Redis::rm($key);
                    $msg = lang('成功加入工单');
                    //log记录
                    GameLog::logData(__METHOD__, [$userID, $OrderNo, $msg], 1, $msg);
                    $success_num += 1;
                } else {

                    $msg = lang('加入工单失败');
                    GameLog::logData(__METHOD__, [$userID, $OrderNo, $msg], 1, $msg);
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => lang('异常状态')
                    ];
                    continue;
                }
            }
            return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
        }
        $this->assign('OrderNo', $OrderNo);
        // $this->assign('checkUser', session('username'));
        //操作人员
        $db = new UserDB();
        // $checkUser = $db->getTableObject('View_UserDrawBack')->group('checkUser')->column('checkUser');
        // $checkUser = array_keys($checkUser);
        $checkUser = $this->getAdminUser();
        $this->assign('checkUser', $checkUser);
        $this->assign('adminuser', session('username'));
        $this->assign('description', IsNullOrEmpty(input('description')) ? input('description') : '');
        return $this->fetch();
    }

    /** 拒绝退款*/
    public function refuse()
    {

        $status = intval(input('status')) ? intval(input('status')) : 0;
        $UserID = intval(input('UserID')) ? intval(input('UserID')) : 0;
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';
        $description = input('description') ? input('description') : '';
        if ($this->request->isAjax()) {
            //加锁
            $key = 'lock_refuseorder_' . $OrderNo;
            if (!Redis::lock($key)) {
                return $this->apiReturn(1, [], '请勿重复操作');
            }
            // Redis::set($key,5);
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
            $status = $info['status'];
            if ($info['checkUser'] != session('username') && $status == 1) {
                return $this->apiReturn(1, '', '权限不足');
            }
            if ($status == 0 || $status == 1 || $status == 6) {
                $status = 2;
                $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => session('username'), 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
//                $llRealMoney = ($info['iMoney'] - $info['Tax']) / bl;
                $opuser = session('username');
                save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：开始');
                $res = $this->sendGameMessage("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$UserID, $status, $OrderNo, $info['iMoney'], $info['iMoney'], $info['DrawBackWay'], $info['FreezonMoney'], $info['CurWaged'], $info['NeedWaged']]);
                $res = unpack("Cint", $res)['int'];
                save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：结束');
                $msg = lang('操作成功.DC状态码:') . $res;
                $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => session('username'), 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                if ($res == 0) {
                    //$BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                } else {
                    save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：失败');
                    $msg = lang('操作失败!DC状态码:') . $res;
                }
                GameLog::logData(__METHOD__, [$UserID, $OrderNo, $msg], !$res, $msg);
            }

            Redis::rm($key);
            //log记录

            return $this->apiReturn(0, [], '操作成功');

        }
        $this->assign('roleid', $UserID);
        $this->assign('orderid', $OrderNo);
        $this->assign('status', $status);
        $this->assign('checkuser', session('username'));
        $this->assign('descript', empty($description) ? $description : '');
        return $this->fetch();
    }

    //一键拒绝
    public function onekeyRefuse()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10011, $auth_ids)) {
            return $this->apiReturn(1, [], '没有权限');
        }
        try {
            $OrderNos = explode(',', input('OrderNo'));
            $description = input('description') ? input('description') : '';
            $sendQuery = new  sendQuery();
            $success_num = 0;
            $error_num = 0;
            $res_data = [];
            $BankDB = new BankDB('OM_BankDB');
            foreach ($OrderNos as $key => &$OrderNo) {
                $key = 'lock_refuseorder_' . $OrderNo;
                if (!Redis::lock($key)) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '请勿重复操作'
                    ];
                }
                $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
                $UserID = $info['AccountID'];
                $draw = $info;
                if (!$draw) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单不存在'
                    ];
                    continue;
                }
                if ($draw['checkUser'] != session('username')) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '权限不足'
                    ];
                    continue;
                }
                if (intval($draw['AccountID']) === 0) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单玩家id为0，无法处理'
                    ];
                    continue;
                }

                $status = $info['status'];

                if ($status == 0 || $status == 1 || $status == 6) {
                    $status = 2;
                    $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => session('username'), 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                    $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                    //                $llRealMoney = ($info['iMoney'] - $info['Tax']) / bl;
                    $opuser = session('username');
                    save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：开始');
                    $res = $this->sendGameMessage("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$UserID, $status, $OrderNo, $info['iMoney'], $info['iMoney'], $info['DrawBackWay'], $info['FreezonMoney'], $info['CurWaged'], $info['NeedWaged']]);
                    $res = unpack("Cint", $res)['int'];
                    save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：结束');
                    $msg = lang('操作成功.DC状态码:') . $res;
                    $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => session('username'), 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                    if ($res == 0) {
                        //$BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
                    } else {
                        save_log('drawback', '操作人:' . $opuser . ',单号：' . $OrderNo . ',金额：' . $info['iMoney'] . ',订单状态：失败');
                        $msg = lang('操作失败!DC状态码:') . $res;
                    }
                    GameLog::logData(__METHOD__, [$UserID, $OrderNo, $msg], !$res, $msg);
                    $success_num += 1;
                    Redis::rm($key);
                    continue;
                } else {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '订单状态有误'
                    ];
                    Redis::rm($key);
                    continue;
                }


            }
            return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
        } catch (\Exception $ex) {
            save_log('playertrans', $ex->getMessage() . $ex->getTraceAsString());
            return $this->apiReturn(100, '', $ex->getMessage());
        }
    }

    /**完成付款款*/
    public function compete()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10011, $auth_ids)) {
            return $this->apiReturn(1, [], '没有权限');
        }
        $userID = intval(input('UserID')) ? intval(input('UserID')) : 0;
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';

        $UserDrawBack = new UserDrawBack('userdrawback');
        $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
        if (!$draw) {
            return $this->apiReturn(100, '', '该提现订单不存在');
        }
        if ($draw['checkUser'] != session('username')) {
            return $this->apiReturn(100, '', '权限不足');
        }
        if (intval($draw['AccountID']) === 0) {
            return $this->apiReturn(100, '', '该提现订单玩家id为0，无法处理');
        }


        $bankM = new BankDB();
        $post_data = [
            'ChannelId' => 0,
            'TransactionNo' => 0,
            'status' => $bankM::DRAWBACK_STATUS_ORDER_COMPLETED,
            'IsDrawback' => $bankM::DRAWBACK_STATUS_ORDER_COMPLETED,
            'UpdateTime' => date('Y-m-d H:i:s', time())
        ];
        $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo]);


        $order_coin = intval($draw['iMoney']);
        $realmoney = intval($order_coin / 1000);
        $sendQuery = new  sendQuery();
        $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$userID, 1, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
        save_log('shoudo', '通知服务器状态:' . json_encode($res));

        if (!$ret) {
            return $this->apiReturn(100, '', '更新订单出错');
        }
        GameLog::logData(__METHOD__, [$userID, $OrderNo, 0, lang('提现手动完成付款')], 1, lang('提现手动完成付款'));
        return $this->apiReturn(0, '', 'success');
    }

    public function onekeyCpmpete()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10011, $auth_ids)) {
            return $this->apiReturn(1, [], '没有权限');
        }
        try {
            $bankM = new BankDB();
            $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
            $OrderNos = explode(',', input('OrderNo'));
            $UserDrawBack = new UserDrawBack('userdrawback');
            $sendQuery = new  sendQuery();
            $success_num = 0;
            $error_num = 0;
            $res_data = [];
            foreach ($OrderNos as $key => &$OrderNo) {
                $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
                if (!$draw) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单不存在'
                    ];
                    continue;
                }
                if ($draw['checkUser'] != session('username')) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '权限不足'
                    ];
                    continue;
                }
                if (intval($draw['AccountID']) === 0) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单玩家id为0，无法处理'
                    ];
                    continue;
                }
                if (intval($draw['status']) != 4) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单状态有误'
                    ];
                    continue;
                }

                $post_data = [
                    'ChannelId' => 0,
                    'TransactionNo' => 0,
                    'status' => $bankM::DRAWBACK_STATUS_ORDER_COMPLETED,
                    'IsDrawback' => $bankM::DRAWBACK_STATUS_ORDER_COMPLETED,
                    'UpdateTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo]);

                $userID = $draw['AccountID'];
                $order_coin = intval($draw['iMoney']);
                $realmoney = intval($order_coin / 1000);

                $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$userID, 1, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
                save_log('shoudo', '通知服务器状态:' . json_encode($res));

                if (!$ret) {
                    // return $this->apiReturn(100, '', '更新订单出错');
                }
                GameLog::logData(__METHOD__, [$userID, $OrderNo, 0, lang('提现手动完成付款')], 1, lang('提现手动完成付款'));
                $success_num += 1;
                continue;
            }
            return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
        } catch (\Exception $ex) {
            save_log('playertrans', $ex->getMessage() . $ex->getTraceAsString());
            return $this->apiReturn(100, '', $ex->getMessage());
        }
    }

    ///处理提交到三方的订单为失败
    public function processThirdFaild()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10011, $auth_ids)) {
            return $this->apiReturn(1, [], '没有权限');
        }
        $userID = intval(input('UserID')) ? intval(input('UserID')) : 0;
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';

        $UserDrawBack = new UserDrawBack('userdrawback');
        $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
        if (!$draw) {
            return $this->apiReturn(100, '', '该提现订单不存在');
        }
        if ($draw['checkUser'] != session('username')) {
            return $this->apiReturn(100, '', '权限不足');
        }
        if (intval($draw['AccountID']) === 0) {
            return $this->apiReturn(100, '', '该提现订单玩家id为0，无法处理');
        }


        $bankM = new BankDB();
        $post_data = [
            'ChannelId' => 0,
            'TransactionNo' => 0,
            'status' => $bankM::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN,
            'IsDrawback' => $bankM::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN,
            'UpdateTime' => date('Y-m-d H:i:s', time())
        ];
        $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo]);


        $order_coin = intval($draw['iMoney']);
        $realmoney = intval($order_coin / 1000);
        $sendQuery = new  sendQuery();

        $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$userID, 2, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
        save_log('shoudo', '通知服务器状态:' . json_encode($res));

        if (!$ret) {
            return $this->apiReturn(100, '', '更新订单出错');
        }
        GameLog::logData(__METHOD__, [$userID, $OrderNo, 0, lang(' 三方失败返还金币')], 1, lang('三方失败返还金币'));
        return $this->apiReturn(0, '', 'success');
    }

    public function onekeyProcessfaild()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10011, $auth_ids)) {
            return $this->apiReturn(1, [], '没有权限');
        }
        try {
            $bankM = new BankDB();
            $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
            $OrderNos = explode(',', input('OrderNo'));
            $UserDrawBack = new UserDrawBack('userdrawback');
            $sendQuery = new  sendQuery();
            $success_num = 0;
            $error_num = 0;
            $res_data = [];
            foreach ($OrderNos as $key => &$OrderNo) {
                $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo], '*');
                if (!$draw) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单不存在'
                    ];
                    continue;
                }
                if ($draw['checkUser'] != session('username')) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '权限不足'
                    ];
                    continue;
                }
                if (intval($draw['AccountID']) === 0) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单玩家id为0，无法处理'
                    ];
                    continue;
                }
                if (intval($draw['status']) != 4) {
                    $error_num += 1;
                    $res_data[] = [
                        'OrderNo' => $OrderNo,
                        'msg' => '该提现订单状态有误'
                    ];
                    continue;
                }

                $post_data = [
                    'ChannelId' => 0,
                    'TransactionNo' => 0,
                    'status' => $bankM::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN,
                    'IsDrawback' => $bankM::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN,
                    'UpdateTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo]);

                $userID = $draw['AccountID'];
                $order_coin = intval($draw['iMoney']);
                $realmoney = intval($order_coin / 1000);

                $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$userID, 2, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
                save_log('shoudo', $OrderNo . '通知服务器状态:' . json_encode($res, 320));

                if (!$ret) {
                    // return $this->apiReturn(100, '', '更新订单出错');
                }
                GameLog::logData(__METHOD__, [$userID, $OrderNo, 0, lang(' 三方失败返还金币')], 1, lang('三方失败返还金币'));
                $success_num += 1;
                continue;
            }
            return $this->apiReturn(0, $res_data, '操作成功。成功：' . $success_num . ',失败：' . $error_num);
        } catch (\Exception $ex) {
            save_log('playertrans', $ex->getMessage() . $ex->getTraceAsString());
            return $this->apiReturn(100, '', $ex->getMessage());
        }
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
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
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
                        lang('备注') => 'string',
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
                            $row['Descript'],

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

    //冻结没收

    /**
     * @return mixed
     */
    public function freeze()
    {
        $OrderNo = input('OrderNo') ? input('OrderNo') : '';
        $description = input('description') ? input('description') : '';
        if ($this->request->isAjax()) {
            //加锁
            $key = 'lock_refuseorder_' . $OrderNo;
            if (!Redis::lock($key)) {
                return $this->apiReturn(1, [], '请勿重复操作');
            }
            Redis::set($key);
            //获取订单信息
            $BankDB = new BankDB('OM_BankDB');
            $info = $BankDB->getTableRow("userdrawback", ['OrderNo' => $OrderNo]);
            $status = $info['status'];
            if ($info['checkUser'] != session('username') && $status == 1) {
                return $this->apiReturn(1, '', '权限不足');
            }
            if ($status == 0 || $status == 1 || $status == 6) {
                $status = 3;
                $data = ['status' => $status, 'IsDrawback' => $status, 'checkUser' => session('username'), 'Descript' => $description, 'checkTime' => date('Y-m-d H:i:s')];
                //修改订单状态
                $info = $BankDB->updateTable('userdrawback', $data, ['OrderNo' => $OrderNo]);
            }
            Redis::rm($key);
            //log记录
            GameLog::logData(__METHOD__, $this->request->request(), (isset($info['code']) && $info['code'] == 0) ? 1 : 0, $info);
            return $this->apiReturn(0, [], '操作成功');
//            return $this->apiReturn($res['code'], $res['data'], 操作成功);
        }

        $this->assign('orderid', $OrderNo);
        $this->assign('descript', empty($description) ? $description : '');
        return $this->fetch();
    }

    //补发
    public function bufa()
    {
        $request = $this->request->request();

        if (!$request['orderno']) {
            return $this->apiReturn(1, [], '参数有误');
        }
        //加锁
        $key = 'lock_bufaorder_' . $request['orderno'];
        if (!Redis::lock($key)) {
            return $this->apiReturn(2, [], '请勿重复操作');
        }

        $BankDB = new BankDB('OM_BankDB');
        $orderInfo = $BankDB->getTableRow("userdrawback", ['OrderNo' => $request['orderno']]);
        if ($orderInfo) {
            if (($orderInfo['status'] == 4 || $orderInfo['data']['status'] == 5) && $orderInfo['data']['isReturn'] == 0 && is_numeric($orderInfo['imoney']) && $orderInfo['imoney'] > 0) {
                //拒绝或者银行未通过 && 未返还
                //给玩家返还
                //先更新状态  已返还
                $update = Api::getInstance()->sendRequest([
                    'orderno' => $request['orderno'],
                    'status' => 1,
                ], 'charge', 'updatereturn');

                if ($update['code'] == 0) {

                    //发钱
                    $socket = new QuerySocket();
//                    $a = $socket->addRoleMoney($request['roleid'], $orderInfo['data']['imoney']);
                    $a = $socket->addRoleMoney($request['roleid'], $orderInfo['data']['imoney'], 0, 1, getClientIP());
                    save_log('returnmoney', json_encode($update) . ' addmoneycode : ' . $a['iResult']);

                    Redis::rm($key);
                    GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
                    return $this->apiReturn(0, [], '补发成功');
                } else {
                    Redis::rm($key);
                    return $this->apiReturn(3, [], '补发失败');
                }
            } else {
                Redis::rm($key);
                return $this->apiReturn(4, [], '补发失败');
            }
        } else {
            Redis::rm($key);
            return $this->apiReturn(5, [], '未找到订单信息');
        }
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

    //手动打款
    public function handle()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            //加锁
            $key = 'lock_handleorder_' . $request['orderid'];
            if (!Redis::lock($key)) {
                return $this->apiReturn(1, [], '请勿重复操作');
            }

            $res = Api::getInstance()->sendRequest([
                'roleid' => $request['roleid'],
                'orderid' => $request['orderid'],
                'status' => 5,
                'checkuser' => $request['checkuser'],
                'descript' => $request['descript']
            ], 'charge', 'updatecheck');


            if ($res['code'] == 0) {
                //手动已打款
                $update = Api::getInstance()->sendRequest([
                    'orderno' => $request['orderid'],
                    'status' => 3
                ], 'charge', 'updatereturn');
            }
            Redis::rm($key);
            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        $this->assign('roleid', input('roleid'));
        $this->assign('orderid', input('orderid') ? input('orderid') : '');
        $this->assign('status', input('status') ? input('status') : '');
        $this->assign('checkuser', session('username'));
        $this->assign('descript', input('descript') ? input('descript') : '');
        return $this->fetch();
    }

    //玩家基础信息
    public function baseplayer()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $BankDB = new BankDB('OM_BankDB');
        $where = '';
        if ($roleId > 0) $where .= 'AccountID=' . $roleId;
        //显示信息不明确 暂时搁置
        return "显示信息不明确 暂时搁置";
        $result = $BankDB->getTableRow('UserDrawBack', $where);
        $data = isset($result) ? $result : [];
        $this->assign('roleid', $roleId);
        $this->assign('data', $data);
        return $this->fetch();
    }

    //玩家流水详情
    public function coinlog()
    {
        $changeType = config('site.bank_change_type');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $changtype = intval(input('changetype')) ? intval(input('changetype')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'page' => $page,
                'changetype' => $changtype,
                'pagesize' => $limit

            ], 'player', 'coin');

            if (isset($res['data']['list'])) {
                foreach ($res['data']['list'] as &$v) {
                    if ($v['changetype'] == 1 || $v['changetype'] == 2 || $v['changetype'] == 3 || $v['changetype'] == 12 || $v['changetype'] == 21) {
                        $v['premoney'] = $v['balance'] + $v['changemoney'];
                    } else {
                        $v['premoney'] = $v['balance'] - $v['changemoney'];
                    }
                    foreach ($changeType as $k2 => $v2) {
                        if ($v['changetype'] == $k2) {
                            $v['changename'] = $v2;
                            break;
                        }
                    }
                }
                unset($v);
            }
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total']);
        }

        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $this->assign('roleid', $roleId);
        $this->assign('changeType', $changeType);
        return $this->fetch();
    }

    //总游戏记录
    public function gamelog()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $winlost = intval(input('winlost')) >= 0 ? intval(input('winlost')) : -1;
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomid,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'page' => $page,
                'winlost' => $winlost,
                'pagesize' => $limit
            ], 'player', 'game');

            if (isset($res['data']['list'])) {
                foreach ($res['data']['list'] as &$v) {
                    $v['premoney'] = $v['lastmoney'] - $v['money'];
                }
                unset($v);
            }

            $sumdata = [
                'win' => isset($res['data']['win']) ? $res['data']['win'] : 0,
                'sum' => isset($res['data']['sum']) ? $res['data']['sum'] : 0,
                'lose' => isset($res['data']['lose']) ? $res['data']['lose'] : 0,
                'escape' => isset($res['data']['escape']) ? $res['data']['escape'] : 0,
            ];
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], ['alltotal' => $sumdata]);
        }
        $res2 = Api::getInstance()->sendRequest([
            'id' => 0
        ], 'room', 'kind');
        $selectData = $res2['data'];
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    //个人游戏记录
    public function selfgamelog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $winlost = intval(input('winlost')) >= 0 ? intval(input('winlost')) : -1;
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomid,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'page' => $page,
                'winlost' => $winlost,
                'pagesize' => $limit
            ], 'player', 'game');

            if (isset($res['data']['list'])) {
                foreach ($res['data']['list'] as &$v) {
                    $v['premoney'] = $v['lastmoney'] - $v['money'];
                }
            }
            unset($v);
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total']);
        }

        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $res2 = Api::getInstance()->sendRequest([
            'id' => 0
        ], 'room', 'kind');
        $selectData = $res2['data'];
        $this->assign('selectData', $selectData);
        $this->assign('roleid', $roleId);
        return $this->fetch();
    }

    //每日房间游戏记录
    public function gamedailylog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $date = trim(input('date')) ? trim(input('date')) : '';
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'orderby' => $orderby,
                'date' => $date,
                'page' => $page,
                'asc' => $asc,
                'pagesize' => $limit
            ], 'room', 'gamedailylog');

            if (isset($res['data']['ResultData']['list']) && $res['data']['ResultData']['list']) {
                foreach ($res['data']['ResultData']['list'] as &$v) {
                    //盈利
                    $v['winmoney'] = $v['winmoney'] / 1000;
                    $v['tax'] = $v['tax'] / 1000;
                    //活跃度
                    $v['totalwater'] = $v['totalwater'] / 1000;
                    $v['date'] = date("Y-m-d");

                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']['ResultData']['list']) ? $res['data']['ResultData']['list'] : [], $res['message'], isset($res['data']['total']) ? $res['data']['total'] : 0, [
                'orderby' => isset($res['data']['ResultData']['orderby']) ? $res['data']['ResultData']['orderby'] : 0,
                'asc' => isset($res['data']['ResultData']['asc']) ? $res['data']['ResultData']['asc'] : 0,
            ]);

        }

        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $this->assign('roleid', $roleId);
        return $this->fetch();
    }

    //玩家充值记录
    public function chargelog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;

            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'page' => $page,
                'pagesize' => $limit
            ], 'player', 'userpaylog');

            if (isset($res['data']) && $res['data']) {
                foreach ($res['data'] as &$v) {
                    //盈利
                    $v['balance'] = $v['balance'] / 1000;
                    $v['rolename'] = trim($v['rolename']);
                    $v['descript'] = trim($v['descript']);
                    $v['changemoney'] = $v['changemoney'] / 1000;
                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']) ? $res['data'] : [], $res['message'], isset($res['total']) ? $res['total'] : 0);
        }

        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $this->assign('roleid', $roleId);
        return $this->fetch();
    }

    //查询同IP,mac,支付宝，银行卡的玩家
    public function getsameuser()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = 0;
            $roomId = 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $mobile = '';
            $ipaddr = trim(input('ip')) ? trim(input('ip')) : '';
            $mac = trim(input('mac')) ? trim(input('mac')) : '';
            $bank = trim(input('bank')) ? trim(input('bank')) : '';
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomId,
                'orderby' => $orderby,
                'page' => $page,
                'asc' => $asc,
                'ipaddr' => $ipaddr,
                'mobile' => $mobile,
                'pagesize' => $limit,
                'mac' => $mac,
                'bank' => $bank,
            ], 'player', 'all');

            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
                    //活跃度
                    $v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;
                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);
        }

        $ipaddr = trim(input('ip')) ? trim(input('ip')) : '';
        $mac = trim(input('mac')) ? trim(input('mac')) : '';
        $bank = trim(input('bank')) ? trim(input('bank')) : '';
        $roleid = trim(input('roleid')) ? trim(input('roleid')) : '';

        $this->assign('ipaddr', $ipaddr);
        $this->assign('mac', $mac);
        $this->assign('bank', $bank);
        $this->assign('roleid', $roleid);
        return $this->fetch();
    }


    //接口日志
    public function notifyLog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;  //页码
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $orderid = input('orderid', '');
            $keyword = input('keyword', '');
            $start = input('start') ? input('start') : '';//开始时间
            $end = input('end') ? input('end') : '';

            $paynotifylog = new PayNotifyLog();
            $where = [];
            if ($orderid)
                $where['OrderId'] = $orderid;

            if (!empty($start) && empty($end)) {
                $where['addtime'] = ['>', $start];
            } else if (empty($start) && !empty($end)) {
                $where['addtime'] = ['<', $end];
            } else if (!empty($start) && !empty($end)) {
                $where['addtime'] = ['between', "$start,$end"];
            }

            if (!empty($keyword)) {
                $where['Parameter'] = ['like', "%" . $keyword . "%"];
            }

            //获取分页
            $result = $paynotifylog->getList($where, $page, $limit, '*', "AddTime desc");
            $count = $paynotifylog->getCount($where);
            foreach ($result as $k => &$v) {
                $v['Error'] = lang($v['Error']);
            }
            unset($v);
            return $this->apiReturn(
                0,
                isset($result) ? $result : [],
                'success',
                $count
            );
        }
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
            $start = input('start', '');
            $end = input('end', '');
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');
            // $where =['CorpType'=>0,'tempdata'=>['>',0]];
            $where = '1=1';
            if (!empty($roleid)) {
                $where .= " and roleid=" . $roleid;
            }

            if (!empty($start)) {
                $where .= " and addtime >='$start'";
            }

            if (!empty($end)) {
                $where .= " and addtime <='$end'";
            }

            $order = 'RoleID desc';
            if (!empty($orderby)) {
                $order = " $orderby $orderytpe";
            }
            if (config('is_portrait') == 1) {
                $db = new GameOCDB();
                $subQuery = "(SELECT RoleID,AddTime,sum(RunningBonus) RunningBonus,sum(InviteBonus) InviteBonus,sum(FirstChargeBonus) FirstChargeBonus,Sum(RunningBonus+InviteBonus+FirstChargeBonus) as TotalProfit FROM [OM_GameOC].[dbo].[T_ProxyDailyBonus](NOLOCK) WHERE " . $where . " GROUP BY [RoleID],AddTime)";
                $data = $db->getTableObject($subQuery)->alias('a')
                    ->order($order)
                    // ->fetchSql(true)
                    // ->select();
                    ->paginate($limit)
                    ->toArray();


                $resultLast = [];
                foreach ($data['data'] as $key => &$val) {
                    $item = [];
                    $time = date('Ymd', strtotime($val['AddTime']));
                    $liushui = $db->getTableObject('T_ProxyDailyCollectData_' . $time)
                        ->where('ProxyId', $val['RoleID'])
                        ->find();
                    $item['firstLevelRechargeRate'] = '0%';
                    $item['firstLevelAverageRecharge'] = '0.00';
                    if (isset($liushui)) {
                        $item['Lv1PersonCount'] = $liushui['Lv1PersonCount'];//1级人数
                        $item['ValidInviteCount'] = $liushui['ValidInviteCount'];//有效人数
                        $item['Lv1FirstDepositPlayers'] = $liushui['Lv1FirstDepositPlayers'];//一级首充人数
                        $item['Lv1Deposit'] = $liushui['Lv1Deposit'];//一级充值人数
                        $item['Lv1Running'] = $liushui['Lv1Running'];//一级流水
                        if ($item['Lv1Running'] > 0 && $item['ValidInviteCount'] > 0) {
                            $item['firstLevelAverageRecharge'] = FormatMoney(bcdiv($item['Lv1Running'], $item['ValidInviteCount']));
                        }
                        if ($item['Lv1FirstDepositPlayers'] > 0 && $item['Lv1PersonCount'] > 0) {
                            $item['firstLevelRechargeRate'] = bcmul(bcdiv($item['Lv1FirstDepositPlayers'], $item['Lv1PersonCount'], 4), 100, 2) . '%';
                        }
                    }
                    $item['RoleID'] = $val['RoleID'];
                    $item['AddTime'] = $val['AddTime'];
                    $item['RunningBonus'] = FormatMoney($val['RunningBonus'] ?: 0);
                    $item['InviteBonus'] = FormatMoney($val['InviteBonus'] ?: 0);
                    $item['FirstChargeBonus'] = FormatMoney($val['FirstChargeBonus'] ?: 0);
                    $item['TotalProfit'] = FormatMoney($val['TotalProfit'] ?: 0);
                    $resultLast[] = $item;
                }

//                var_dump($resultLast);
//                die();
//                foreach ($data['data'] as $key => &$val) {
//                    $val['RunningBonus'] = FormatMoney($val['RunningBonus'] ?: 0);
//                    $val['InviteBonus'] = FormatMoney($val['InviteBonus'] ?: 0);
//                    $val['FirstChargeBonus'] = FormatMoney($val['FirstChargeBonus'] ?: 0);
//                    $val['TotalProfit'] = FormatMoney($val['TotalProfit'] ?: 0);
//                    //$val['TotalProfit'] = round(($val['RunningBonus'] + $val['InviteBonus'] + $val['FirstChargeBonus']), 2);
//                }
                $list = $resultLast;
                $count = $data['total'];
                if ($action == 'list' && input('output') != 'exec') {
                    $other = [

                    ];
                    return $this->apiReturn(0, $list, '', $count, $other);
                }
            } else {
                $where .= ' and (ReceiveProfit>0 or AbleProfit>0)';

                $list = $userproxyinfo->getList($where, $page, $limit, 'RoleID,AbleProfit,ReceiveProfit,TotalProfit', $order);
                foreach ($list as $k => &$value) {
                    $value['TotalProfit'] = bcdiv($value['TotalProfit'], bl, 3);
                    $value['ReceiveProfit'] = bcdiv($value['ReceiveProfit'], bl, 3);
                    $value['AbleProfit'] = bcdiv($value['AbleProfit'], bl, 3);
                    $value['ProfitType'] = lang('代理税收返利');
                }
                unset($value);
                $count = $userproxyinfo->getCount($where);
                if ($action == 'list' && input('output') != 'exec') {
                    $unclaimed = $userproxyinfo->getSum($where, 'TempData');
                    $Received = $userproxyinfo->getSum($where, 'ReceiveProfit');

                    $other = [
                        'unclaimed' => bcdiv($unclaimed, bl, 3),
                        'Received' => bcdiv($Received, bl, 3)
                    ];
                    return $this->apiReturn(0, $list, '', $count, $other);
                }
            }


            if (input('output') == 'exec') {
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                if (empty($list)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                };
                $result = [];
                $result['list'] = $list;
                $result['count'] = $count;
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
                    if (config('is_portrait') == 1) {
                        $header_types = [
                            lang('玩家ID') => 'string',
                            lang('代理流水返利') => "string",
                            lang('代理邀请奖励') => "string",
                            lang('代理首充奖励') => "string",
                            lang('总收益') => 'string',
                        ];
                    } else {
                        $header_types = [
                            lang('玩家ID') => 'string',
                            lang('待领取金额') => "string",
                            lang('领取类型') => "string",
                            lang('已领取金额') => "string",
                            lang('总收益') => 'string',
                        ];
                    }

                    $filename = lang('代理奖励列表') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        if (config('is_portrait') == 1) {
                            $item = [
                                $row['RoleID'],
                                $row['d1'],
                                $row['d2'],
                                $row['d3'],
                                $row['TotalProfit'],
                            ];
                        } else {
                            $item = [
                                $row['RoleID'],
                                $row['AbleProfit'],
                                $row['ProfitType'],
                                $row['ReceiveProfit'],
                                $row['TotalProfit'],
                            ];
                        }

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
        if (config('is_portrait') == 1) {
            return $this->fetch('agent_coin_apply_s');
        } else {
            return $this->fetch();
        }
    }


    public function agentWeeklyApply()
    {

        $userweekly = new UserWeeklyBonus();
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;  //页码
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = input('roleid', '');

            $where = ['status' => 0, 'WeekExtraBonus' => ['>', 0]];
            if (!empty($roleid)) {
                $where['roleid'] = $roleid;
            }
            $list = $userweekly->getList($where, $page, $limit, 'Id,Date,RoleID,WeekExtraBonus,WeekTaxProfit,[status]', 'RoleID');
            foreach ($list as $k => &$value) {
                $value['WeekExtraBonus'] = bcdiv($value['WeekExtraBonus'], bl, 3);
                $value['WeekTaxProfit'] = bcdiv($value['WeekTaxProfit'], bl, 3);
                //$value['AbleProfit'] = bcdiv($value['AbleProfit'],bl,3);
                $value['status'] = $value['status'] ? lang('已领取') : lang('未领取');
                $value['ProfitType'] = lang('代理返利');
            }
            unset($value);
            $count = $userweekly->getCount($where);
            return $this->apiReturn(0, $list, '', $count);

        }
        $where = ['status' => 0, 'WeekExtraBonus' => ['>', 0]];
        $unclaimed = $userweekly->getSum($where, 'WeekExtraBonus');
        $Received = $userweekly->getSum(['status' => 1], 'WeekExtraBonus');

        $other = [
            'unclaimed' => bcdiv($unclaimed, bl, 3),
            'Received' => bcdiv($Received, bl, 3)
        ];
        $this->assign('data', $other);
        return $this->fetch();
    }


    public function agentcoincheck()
    {

        $id = input('Id', 0);
        $roleid = input('roleid', '');
        $status = input('status', '');
        $userproxyinfo = new UserProxyInfo();
        $userweekly = new UserWeeklyBonus();
        if ($roleid == 0 || $id == 0) {
            return $this->apiReturn(100, '', '参数错误');
        }

        if ($status == 100) { //同意
            $result = $userweekly->getDataRow(['id' => $id], 'WeekExtraBonus,WeekTaxProfit,status');
            $weeklybouns = $userproxyinfo->getValue(['RoleID' => $roleid], 'WeekExtraBonus');
            if (!empty($result)) {
                if ($result['status'] == 0 && $weeklybouns == 0) {
                    $userproxyinfo->updateByWhere(['roleid' => $roleid], ['WeekExtraBonus' => $result['WeekExtraBonus'], 'WeekTaxProfit' => $result['WeekTaxProfit']]);
                    $userweekly->updateByWhere(['Id' => $id], ['Status' => 100]);
                } else {
                    return $this->apiReturn(100, '', '玩家奖励还未领取');
                }
            }
        } else if ($status == 200) { //没收
            $userweekly->updateByWhere(['Id' => $id], ['Status' => 2]);

        } else if ($status == 300) { //拒绝


        }
        return $this->apiReturn(0, '', '处理成功');
    }


    public function setqueryorder()
    {
        $OrderNo = input('orderno') ? input('orderno') : '';
        $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
        $UserDrawBack = new UserDrawBack('userdrawback');
        $draw = $UserDrawBack->GetRow(['OrderNo' => $OrderNo]);
        if (!$draw) {
            return $this->apiReturn(100, '', '该提现订单不存在');
        }
        if ($draw['status'] != 4) {
            return $this->apiReturn(100, '', '当前订单不是提交第三方状态');
        }
        $db = new MasterDB();
        $channel = $db->getTableRow('T_GamePayChannel', ['ChannelId' => $channelid], '*');
        $config = json_decode($channel['MerchantDetail'], true);
        $extra = json_encode(['channelid' => $channelid]);
        $channelcode = $channel['ChannelCode'];
        if ($OrderNo == 'OrderNo') {
            $save_data = [
                'status' => 5,
                'IsDrawback' => 5,
                'TransactionNo' => '',
                'UpdateTime' => date('Y-m-d H:i:s', time())
            ];
            $bankM = new BankDB();
            $ret = $bankM->updateTable('userdrawback', $save_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING]);
            $order_coin = $draw['iMoney'];
            $realmoney = intval($order_coin / 1000);
            $sendQuery = new sendQuery();
            $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$draw['AccountID'], 2, $OrderNo, $realmoney, $order_coin, $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
            $res = unpack("Cint", $res)['int'];
            if ($res != 0) {
                $log_txt = '第三方处理失败金币未返还';
            }
            save_log('queryhand', '返回数据：' . ',退币状态：' . json_encode($res) . $log_txt);
            return $this->apiReturn(0, '', lang('支付通道失败并退币'));
        }
        if ($OrderNo == 'OrderNo') {
            $save_data = [
                'status' => 100,
                'IsDrawback' => 100,
                'TransactionNo' => '',
                'UpdateTime' => date('Y-m-d H:i:s', time())
            ];
            $bankM = new BankDB();
            $ret = $bankM->updateTable('userdrawback', $save_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING]);
            $log_txt = '通知成功';
            $order_status = 100;
            $order_coin = $draw['iMoney'];
            $realmoney = intval($order_coin / 1000);
            $sendQuery = new sendQuery();
            $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$draw['AccountID'], 1, $OrderNo, $realmoney, $order_coin, $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
            $text = "OK";
            save_log('queryhand', '返回数据：' . ',处理成功：' . json_encode($res) . $log_txt);
            return $this->apiReturn(0, '', lang('订单处理成功'));
        }
        switch ($channelcode) {
            case 'tgpay':
                $tgpay = new TgPay();
                $result = $tgpay->queryorder($config, $OrderNo);
                //$result=['refCode'=>1,'msg'=>'success','transaction_id'=>'222222','status'=>'success'];
                if ($result['status'] == 'success') {
                    if ($result['refCode'] == '1') {
                        $save_data = [
                            'status' => 100,
                            'IsDrawback' => 100,
                            'TransactionNo' => $result['transaction_id'],
                            'UpdateTime' => date('Y-m-d H:i:s', time())
                        ];
                        $bankM = new BankDB();
                        $ret = $bankM->updateTable('userdrawback', $save_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING]);
                        $log_txt = '通知成功';
                        $order_status = 100;
                        $order_coin = $draw['iMoney'];
                        $realmoney = intval($order_coin / 1000);
                        $sendQuery = new sendQuery();
                        $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$draw['AccountID'], 1, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
                        $text = "OK";
                        save_log('queryhand', '返回数据：' . json_encode($result) . ',处理成功：' . json_encode($res) . $log_txt);
                        return $this->apiReturn(0, '', lang('订单处理成功'));
                    } else if ($result['refCode'] == '2' || $result['refCode'] == '5') {
                        $save_data = [
                            'status' => 5,
                            'IsDrawback' => 5,
                            'TransactionNo' => $result['transaction_id'],
                            'UpdateTime' => date('Y-m-d H:i:s', time())
                        ];
                        $bankM = new BankDB();
                        $ret = $bankM->updateTable('userdrawback', $save_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING]);
                        $order_coin = $draw['iMoney'];
                        $realmoney = intval($order_coin / 1000);
                        $sendQuery = new sendQuery();
                        $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$draw['AccountID'], 2, $OrderNo, $realmoney, $order_coin, $draw['DrawBackWay'], $draw['FreezonMoney'], $draw['CurWaged'], $draw['NeedWaged']]);
                        $res = unpack("Cint", $res)['int'];
                        if ($res != 0) {
                            $log_txt = '第三方处理失败金币未返还';
                        }
                        save_log('queryhand', '返回数据：' . json_encode($result) . ',退币状态：' . json_encode($res) . $log_txt);
                        return $this->apiReturn(0, '', lang('支付通道失败并退币'));
                    } else {
//                        $json = '{"str":"'.$result['msg'].'"}';
//                        $arr = json_decode($json,true);
                        return $this->failJSON(urldecode($result['refMsg']));
                    }
                } else if ($result['status'] == 'error') {
                    if ($result['refCode'] == '7') {
                        $bankM = new BankDB();
                        $save_data = [
                            'status' => 0,
                            'IsDrawback' => 0,
                            'TransactionNo' => '',
                            'UpdateTime' => date('Y-m-d H:i:s', time())
                        ];
                        $ret = $bankM->updateTable('userdrawback', $save_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING]);
                        save_log('queryhand', '返回数据：' . json_encode($result) . ',未查询到订单退回：' . json_encode($ret));
                        return $this->apiReturn(0, '', lang('支付通道未查询到订单退回'));
                    }
                } else {
                    $json = '{"str":"' . $result['refMsg'] . '"}';
                    $arr = json_decode($json, true);
                    return $this->apiReturn(0, '', $arr['str']);
                }
                break;
        }
        return $this->apiReturn(100, '', '查询失败');
    }


    public function UpdateJson()
    {
        $channelid = input('channelid', 0);
        $key = input('key', '');
        $values = input('value', '');
        if ($channelid == 0 || $key == '' || $values == '') {
            return $this->failJSON('参数错误');
        }
        $gamepaychannel = new GamePayChannel();
        $info = $gamepaychannel->getRowById($channelid);

        if (!$info) {
            return $this->failJSON('渠道不存在');
        }
        if (empty($info['MerchantDetail'])) {
            return $this->failJSON('配置不存');
        }

        $detail = json_decode($info['MerchantDetail'], true);
        $detail[$key] = $values;
        $json = json_encode($detail);
        $gamepaychannel->updateById($channelid, ['MerchantDetail' => $json]);
        return $this->successJSON('');
    }


    //自动出款设置
    public function ChannelAutoConfig()
    {
        $action = $this->request->param('action');
        $gameoc = new GameOCDB();
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $data = $gameoc->getTableObject('T_PayRiskConfig(NOLOCK)')
                ->order('RiskLevel desc')
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            $risk_config = $gameoc->getTableObject('T_PayAutoConfig(NOLOCK)')->where('Id', 1)->find();
            $this->assign('risk_config', $risk_config);

            $Channel = $this->GetOutChannelInfo(1);
            $this->assign('channeInfo', $Channel);
            return $this->fetch();
        }
    }

    //
    public function updatePayAutoConfig()
    {
        $param = $this->request->param();
        $gameoc = new GameOCDB();
        $res = $gameoc->getTableObject('T_PayAutoConfig')
            ->where('Id', 1)
            ->data($param)
            ->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function updatePayRiskConfigStatus()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status');
        $gameoc = new GameOCDB();
        $res = $gameoc->getTableObject('T_PayRiskConfig')
            ->where('Id', $id)
            ->data(['Status' => $status])
            ->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function deletePayRiskConfig()
    {
        $id = $this->request->param('id');
        $gameoc = new GameOCDB();
        $res = $gameoc->getTableObject('T_PayRiskConfig')
            ->where('Id', $id)
            ->delete();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function editPayRiskConfig()
    {
        $gameoc = new GameOCDB();
        if ($this->request->method() == 'POST') {
            $id = request()->param('id');
            $data = [];
            $data['Days'] = request()->param('Days');
            $data['TotalWin'] = request()->param('TotalWin');
            $data['RiskLevel'] = request()->param('RiskLevel');
            $data['Status'] = request()->param('Status');
            if ($id) {
                $res = $gameoc->getTableObject('T_PayRiskConfig')->where('Id', $id)->data($data)->update();
            } else {
                $res = $gameoc->getTableObject('T_PayRiskConfig')->insert($data);
            }
            if ($res) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        $id = request()->param('id');
        $data = [];
        if ($id) {
            $data = $gameoc->getTableObject('T_PayRiskConfig(NOLOCK)')->where('Id', $id)->find();
        }
        if (empty($data)) {
            $data['Status'] = 0;
        }
        $this->assign('data', $data);
        return $this->fetch();
    }
}
