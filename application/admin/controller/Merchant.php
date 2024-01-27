<?php

namespace app\admin\controller;

use app\model\BankDB;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\DataChangelogsDB;
use app\model\UserDB;
use think\Exception;
use think\exception\PDOException;
use think\response\Json;
use think\View;
use redis\Redis;
use app\model\User as userModel;

/**
 * Class PkMatch
 * @package app\admin\controller
 */
class Merchant extends Main
{
    public function index()
    {

        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $OperatorId = $this->request->param('OperatorId');
            $OperatorName = $this->request->param('OperatorName');
            $where = "1=1 and b.AccountType=0 ";

            if ($OperatorId != '') {
                $where .= " and a.OperatorId='" . $OperatorId . "'";
            }
            if ($OperatorName != '') {
                $where .= " and a.OperatorName like '%" . $OperatorName . "%'";
            }

            $data = (new MasterDB())->getTableObject('T_OperatorLink')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_OperatorSubAccount](NOLOCK) b', 'a.OperatorId=b.OperatorId', 'LEFT')
                ->join('[T_GameRoomInfo](NOLOCK) c', 'a.RedirectTypeId=c.RoomID', 'LEFT')
                ->where($where)
                ->field('a.*,b.OperatorName,b.AddTime,b.google_verify,c.RoomID,c.RoomName')
                ->order('AddTime desc')
                ->paginate($limit)
                ->toArray();

            foreach ($data['data'] as $key => &$val) {
                $roomid = [];
                $str_room = '--';
                $room_data = (new MasterDB())
                    ->getTableObject('T_OperatorGameType')->alias('a')
                    ->join('[T_GameRoomInfo](NOLOCK) c', 'a.RedirectTypeId=c.RoomID', 'LEFT')
                    ->where('OperatorId=' . $val['OperatorId'])
                    ->field('c.RoomID,c.RoomName')
                    ->select();
                if (!empty($room_data)) {
                    foreach ($room_data as $k => $v) {
                        $roomid[] = $v['RoomName'] . '--(' . $v['RoomID'] . ')';
                    }
                    $str_room = implode(',', $roomid);
                }
                $val['ChannelRoom'] = $str_room;
                $val['RechargeFee'] /= 1;
                $val['WithdrawalFee'] /= 1;
                $val['WithdrawRemain'] /= bl;
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            $APIFee = "API费用(PP,PG,EVO";
            if (config('has_spr') == 1) {
                $APIFee .= ",JDB";
            }
            if (config('has_haba') == 1) {
                $APIFee .= ",HABA";
            }
            if (config('has_hacksaw') == 1) {
                $APIFee .= ",HackSaw";
            }
            if (config('has_jili') == 1) {
                $APIFee .= ",JILI";
            }
            if (config('has_yesbingo') == 1) {
                $APIFee .= ",YES!BINGO";
            }

            if (config('has_tadagame') == 1) {
                $APIFee .= ",TADA";
            }
            if (config('has_fcgame') == 1) {
                $APIFee .= ",FCGame";
            }
            $APIFee .= ",PPLive";
            $APIFee .= ")";
            $this->assign('APIFee', $APIFee);
            return $this->fetch();
        }
    }

    public function edit()
    {
        if ($this->request->method() == 'POST') {
            $id = request()->param('id');
            $data = [];
            $data['ProxyExtendLink'] = $this->request->param('ProxyExtendLink');
            // $data['WhatsAppShareLink'] = $this->request->param('WhatsAppShareLink');
            // $data['FBShareLink']       = $this->request->param('FBShareLink');
            // $data['WithdrawRemain']    = $this->request->param('WithdrawRemain');
            $OperatorName = $this->request->param('OperatorName');
            $OperatorId = $this->request->param('OperatorId');
            $password = $this->request->param('PassWord');
            $data['RechargeFee'] = $this->request->param('RechargeFee') ?: 0;
            $data['WithdrawalFee'] = $this->request->param('WithdrawalFee') ?: 0;
            $data['APIFee'] = $this->request->param('APIFee') ?: 0;
//            $data['DivideFee'] = $this->request->param('DivideFee') ?: 0;
            $data['SingleUrl'] = $this->request->param('SingleUrl');
            $sub = [];
            $sub['OperatorName'] = $OperatorName;
            if (!empty($password)) {
                $sub['PassWord'] = md5($password);
            }
            if ($OperatorId !== '') {
                $res = (new MasterDB())->getTableObject('T_OperatorLink')->where('OperatorId', $OperatorId)->data($data)->update();
                $res = (new GameOCDB())->getTableObject('T_OperatorSubAccount')->where('OperatorId', $OperatorId)->data($sub)->update();
                return $this->apiReturn(0, '', '编辑渠道成功');
            } else {

                $has_OperatorName = (new GameOCDB())->getTableObject('T_OperatorSubAccount')->where('OperatorName', $OperatorName)->find();
                if ($has_OperatorName) {
                    return $this->apiReturn(1, '', '渠道名称已存在');
                }
                $OperatorId = $this->getRoleId();
                $data['OperatorId'] = $OperatorId;
                $res = (new MasterDB())->getTableObject('T_OperatorLink')->insert($data);
                $sub['AddTime'] = date('Y-m-d H:i:s');
                $sub['OperatorId'] = $OperatorId;
                $res = (new GameOCDB())->getTableObject('T_OperatorSubAccount')->insert($sub);
                $data1 = ['mydate' => date('Y-m-d'), 'OperatorId' => $OperatorId];
                $data2 = ['AddDate' => date('Y-m-d'), 'OperatorId' => $OperatorId];
                $res = (new GameOCDB())->getTableObject('T_Operator_GameStatisticPay')->insert($data1);
                $res = (new GameOCDB())->getTableObject('T_Operator_GameStatisticPayOut')->insert($data1);
                $res = (new GameOCDB())->getTableObject('T_Operator_GameStatisticTotal')->insert($data1);
                $res = (new GameOCDB())->getTableObject('T_Operator_GameStatisticUser')->insert($data1);
                $res = (new GameOCDB())->getTableObject('T_Operator_ProxyDailyShareCount')->insert($data2);

                return $this->apiReturn(0, '', '添加渠道成功');
            }

        }
        $OperatorId = request()->param('OperatorId');
        $data = [];
        if ($OperatorId !== '') {
            $data = (new MasterDB())->getTableObject('T_OperatorLink')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_OperatorSubAccount](NOLOCK) b', 'a.OperatorId=b.OperatorId', 'LEFT')
                ->where('a.OperatorId', $OperatorId)
                ->field('a.*,b.OperatorName,b.AddTime,b.google_verify')
                ->order('AddTime desc')
                ->find() ?: [];
        }
        if ($data) {
            $data['RechargeFee'] /= 1;
            $data['WithdrawalFee'] /= 1;
        }
        $APIFee = "API费用(PP,PG,EVO";
        if (config('has_spr') == 1) {
            $APIFee .= ",JDB";
        }
        if (config('has_haba') == 1) {
            $APIFee .= ",HABA";
        }
        if (config('has_hacksaw') == 1) {
            $APIFee .= ",HackSaw";
        }
        if (config('has_jili') == 1) {
            $APIFee .= ",JILI";
        }

        if (config('has_yesbingo') == 1) {
            $APIFee .= ",YES!BINGO";
        }
        if (config('has_tadagame') == 1) {
            $APIFee .= ",TADA";
        }

        if (config('has_fcgame') == 1) {
            $APIFee .= ",FCGame";
        }
        $APIFee .= ",PPLive";

        $APIFee .= ")";

        $this->assign('APIFee', $APIFee);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function getRoleId()
    {
        $roleId = mt_rand(500000, 599999);
        $isExist = (new MasterDB())->getTableObject('T_OperatorLink')->where('OperatorId=' . $roleId)->find();
        if ($isExist) {
            return $this->getRoleId();
        }
        return $roleId;
    }


    public function toIndex()
    {
        $OperatorId = request()->param('OperatorId');
        $Operator = (new GameOCDB())->getTableObject('T_OperatorSubAccount')->where('OperatorId=' . $OperatorId)->find();
        if ($Operator) {

            if (session('userid') > 10) {
                return json(['code' => 1]);
            } else {
                session('merchant_Id', $Operator['Id']);
                session('merchant_OperatorName', $Operator['OperatorName']);
                session('merchant_OperatorId', $Operator['OperatorId']);

                return json(['code' => 0]);
                // $this->redirect($url);
            }

        } else {
            return json(['code' => 1]);
        }
    }

    public function toIndex2()
    {
        $url = url('Merchant/index/index');
        $this->redirect($url);
    }

    public function quota()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $OperatorId = $this->request->param('OperatorId');
            $OperatorName = $this->request->param('OperatorName');
            $startdate = $this->request->param('startdate');
            $enddate = $this->request->param('enddate');
            $checkuser = $this->request->param('checkuser');
            $status = $this->request->param('status');
            $where = "AccountType=0";
            if ($OperatorId != '') {
                $where .= " and a.OperatorId='" . $OperatorId . "'";
            }
            if ($status != '') {
                $where .= " and a.status='" . $status . "'";
            }
            if ($OperatorName != '') {
                $where .= " and b.OperatorName like '%" . $OperatorName . "%'";
            }
            if ($startdate != '') {
                $where .= " and a.AddTime>'" . $startdate . "'";
            }
            if ($enddate != '') {
                $where .= " and a.AddTime<'" . $enddate . "'";
            }
            if ($checkuser != '') {
                $where .= " and a.checkuser='" . $checkuser . "'";
            }
            $data = (new GameOCDB())->getTableObject('T_Operator_TransferLog')->alias('a')
                ->join('[OM_GameOC].[dbo].[T_OperatorSubAccount](NOLOCK) b', 'a.OperatorId=b.OperatorId')
                ->where($where)
                ->field('a.*,b.OperatorName')
                ->order('AddTime desc')
                ->paginate($limit)
                ->toArray();

            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }

    public function addEd()
    {
        $OperatorId = $this->request->param('OperatorId');
        $amount = $this->request->param('amount');
        if ($amount == 0) {
            return $this->apiReturn(2, [], '额度有误');
        }
        $has = (new GameOCDB())->getTableObject('T_Operator_TransferLog')->where('OperatorId', $OperatorId)->where('Status', 1)->find();
        if ($has) {
            return $this->apiReturn(2, [], '请等待上一订单审核通过');
        }

        $res = (new GameOCDB())->getTableObject('T_Operator_TransferLog')->insert([
            'OperatorId' => $OperatorId,
            'ChangeMoney' => $amount,
            'AddTime' => date('Y-m-d H:i:s'),
            'checkuser' => session('username'),
            'Status' => 1

        ]);
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function ed()
    {
        $auth_ids = $this->getAuthIds();
        if (!in_array(10012, $auth_ids)) {
            return $this->apiReturn(2, [], '没有权限');
        }
        $id = $this->request->param('id');
        $status = $this->request->param('status');
        $order = (new GameOCDB())->getTableObject('T_Operator_TransferLog')->where('Id', $id)->find();
        if (empty($order)) {
            return $this->apiReturn(2, [], '订单不存在');
        }
        if ($order['Status'] != 1) {
            return $this->apiReturn(2, [], '订单状态有误');
        }
        $order['ChangeMoney'] *= bl;
        $res = 0;
        if ($status == 2) {
            $result = $this->sendGameMessage('CMD_MD_ADD_WITHDRAW_REMAIN', [$order['OperatorId'], $order['ChangeMoney']], "DC", 'returnComm');
            // $result['iResult'] = 0;
            if ($result['iResult'] == 0) {
                // (new MasterDB())->getTableObject('T_OperatorLink')->where('OperatorId',$order['OperatorId'])->setInc('WithdrawRemain',$order['ChangeMoney']);
                $data['Status'] = 2;
            } else {
                $data['Status'] = 1;
            }
        }
        if ($status == 3) {
            $data['Status'] = 3;
        }
        $dara['CheckTime'] = date('Y-m-d H:i:s');
        $res = (new GameOCDB())->getTableObject('T_Operator_TransferLog')->where('Id', $id)->data($data)->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    //渠道额度补偿
    public function emailquotalist()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $page = $this->request->param('page') ?: 1;
            $limit = $this->request->param('limit') ?: 15;
            $db = new GameOCDB();
            $users = $db->getTableList('T_OperatorSubAccount', ['AccountType' => 0], $page, $limit, '*');

            foreach ($users['list'] as $key => &$val) {
                $config = $db->getTableObject('T_OperatorEmailQuota')->where('OperatorId', $val['OperatorId'])->find();

                if (empty($config)) {
                    $val['DailyQuota'] = 0;
                    $val['TotalQuota'] = 0;
                } else {
                    $val['DailyQuota'] = $config['DailyQuota'];
                    $val['TotalQuota'] = $config['TotalQuota'];
                }

                $where = ' extratype in(1,7) and  replace(Operator,\'biz-\',\'\')
  in(SELECT  LoginAccount  FROM [OM_GameOC].[dbo].[T_ProxyChannelConfig] where OperatorId=' . $val['OperatorId'] . ') or Operator in(SELECT OperatorName  FROM [OM_GameOC].[dbo].[T_OperatorSubAccount] where AccountType=0 and OperatorId=' . $val['OperatorId'] . ')';
                $email_db = new DataChangelogsDB();
                $val['hasQuotaToday'] = $email_db->getTableObject('T_ProxyMsgLog')
                    ->where($where)
                    ->whereTime('AddTime', '>=', date('Y-m-d'))
                    ->sum('Amount') ?: 0;
                $val['hasQuotaToday'] /= bl;
                $val['hasQuotaTotal'] = $email_db->getTableObject('T_ProxyMsgLog')
                    ->where($where)
                    ->sum('Amount') ?: 0;
                $val['hasQuotaTotal'] /= bl;
            }
            return $this->apiJson($users);

        }
        if ($action == 'edit') {
            $id = (int)$this->request->param('id');
            $DailyQuota = $this->request->param('DailyQuota') ?: 0;
            $TotalQuota = $this->request->param('TotalQuota') ?: 0;

            $db = new GameOCDB();
            $has = $db->getTableObject('T_OperatorEmailQuota')->where('OperatorId', $id)->find();
            if ($has) {
                $res = $db->getTableObject('T_OperatorEmailQuota')->where('OperatorId', $has['OperatorId'])->update([
                    'DailyQuota' => $DailyQuota,
                    'TotalQuota' => $TotalQuota,
                ]);
            } else {
                $res = $db->getTableObject('T_OperatorEmailQuota')->insert([
                    'OperatorId' => $id,
                    'DailyQuota' => $DailyQuota,
                    'TotalQuota' => $TotalQuota,
                ]);
            }

            if ($res) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        return $this->fetch();
    }


    public function unBindGoogle()
    {
        $OperatorId = input('OperatorId', '');

        if (empty($OperatorId)) {
            return $this->apiReturn(100, '', '参数错误');
        }
        $db = new GameOCDB();
        $where = " OperatorId=$OperatorId and AccountType=0 ";
        $db->updateTable('T_OperatorSubAccount', ['google_verify' => ''], $where);
        return $this->apiReturn(0, '', '操作成功');

    }

    public function updateStatus()
    {
        $OperatorId = input('OperatorId', '');

        if (empty($OperatorId)) {
            return $this->apiReturn(100, '', '参数错误');
        }
        $db = new MasterDB();
        $where = "OperatorId=$OperatorId ";
        $db->updateTable('T_OperatorLink', ['status' => 2], $where);
        return $this->apiReturn(0, '', '操作成功');

    }


    public function apiCostSwitch()
    {
        $operatorId = input('id');
        $status = input('status');
        $db=new MasterDB();
        $info = $db->getTableObject('T_OperatorLink(nolock)')
            ->field('*')
            ->where('OperatorId',$operatorId)
            ->find();
        if ($status == 1){
            if ($info['CountApiStatus'] == 1){

                return $this->apiReturn(1, '', 'api费用计算已开启');
            }
            $db->getTableObject('T_OperatorLink')
                ->where('OperatorId',$operatorId)
                ->update(['CountApiStatus' =>1 ]);

        }else{
            if ($info['CountApiStatus'] == 2){
                return $this->apiReturn(1, '', 'api费用计算已关闭');
            }
            $db->getTableObject('T_OperatorLink')
                ->where('OperatorId',$operatorId)
                ->update(['CountApiStatus' => 0 ]);
        }
        return $this->apiReturn(0, '', '操作成功');

    }

    //额度管理
    public function quotaManage(){
        if (input('action') == 'list') {
            $data = (new \app\model\GameOCDB())->quotaManage();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }

    public function editQuota(){
        if ($this->request->method() == 'POST') {
            $OperatorId = request()->param('OperatorId');
            $data = request()->param();
            $res = (new GameOCDB())->getTableObject('T_OperatorQuotaManage')->where('OperatorId',$OperatorId)->data($data)->update();
            if ($res) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        $OperatorId = request()->param('OperatorId');
        $data = (new GameOCDB())->getTableObject('T_OperatorQuotaManage')->where('OperatorId',$OperatorId)->find();
        $this->assign('data',$data);
        return $this->fetch();
    }


}