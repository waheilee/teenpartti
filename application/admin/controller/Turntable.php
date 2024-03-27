<?php

namespace app\admin\controller;
use think\Db;
use app\model\MasterDB;

class Turntable extends Main
{

    public function checkRecord()
    {
        if (input('action') == 'list') {
            $limit          = request()->param('limit') ?: 15;
            $RoleID         = request()->param('RoleID');
            $start_date     = request()->param('start_date');
            $end_date       = request()->param('end_date');
            $check_user     = request()->param('check_user');
            $status         = request()->param('status');
            $ishistory      = request()->param('ishistory');
            $orderby = input('orderby', 'AddTime');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($RoleID != '') {
                $where .= ' and a.RoleID=' . $RoleID;
            }
            if ($start_date != '') {
                $where .= ' and a.AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.AddTime<\'' . $end_date . '\'';
            }
            if ($check_user != '') {
                $where .= ' and a.CheckUser like \'%' . $check_user . '%\'';
            }
            if ($status != '') {
                $where .= ' and a.Status='.$status;
            }
            if ($ishistory != '') {
                $where .= ' and a.Status<>0';
            }

            $data = (new \app\model\DataChangelogsDB())->getTableObject('T_AyllaWithdrawHistory')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](nolock) as b','b.ProxyId=a.RoleID')
                ->where($where)
                ->field('a.*,ISNULL(b.Lv1PersonCount,0) Lv1PersonCount,ISNULL(b.Lv1DepositPlayers,0) Lv1DepositPlayers')
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                $val['PreDrawMoney'] = FormatMoney($val['PreDrawMoney']);
                $val['LastDrawMoney'] = FormatMoney($val['LastDrawMoney']);
                $val['PGBonus'] = FormatMoney($val['PGBonus']);
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            $pddconfig = (new \app\model\MasterDB())->getTableObject('T_GameConfig')->where('CfgType',291)->value('CfgValue');
            $this->assign('pddconfig',$pddconfig);
            return $this->fetch();
        }
    }

    /**
     * 转盘开关
     */
    public function turntableSwitch()
    {
        $pddconfig = (new \app\model\MasterDB())->getTableObject('T_GameConfig')->where('CfgType',291)->value('CfgValue');
        if ($pddconfig == 1) {
            $switch = 0;
        } else {
            $switch = 1;
        }
        $res = (new \app\model\MasterDB())->getTableObject('T_GameConfig')
            ->where('CfgType', 291)
            ->data(['CfgValue'=>$switch])
            ->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }

    }


    /**
     * 全网一键退款
     */
    public function onekeyBack()
    {
        $data = $this->sendGameMessage('CMD_MD_AYLLA_GM_FEFUND_ALL', [], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    /**
     * 审核

     */
    public function check()
    {
        $ID = input('ID');
        $RoleID = input('RoleID');
        $Status = input('Status');

        $record = (new \app\model\DataChangelogsDB())->getTableObject('T_AyllaWithdrawHistory')->where('ID',$ID)->find();
        if (empty($record)) {
            return $this->apiReturn(1, '', '记录不存在');
        }
        if ($record['Status'] != 0) {
            return $this->apiReturn(1, '', '记录已审核');
        }
        (new \app\model\DataChangelogsDB())
                ->getTableObject('T_AyllaWithdrawHistory')
                ->where('ID',$ID)
                ->data([
                    'Status'=>$Status,
                    'CheckTime'=>date('Y-m-d H:i:s'),
                    'CheckUser'=>session('username')
                ])
                ->update();
        if ($Status == 2) {
            $data['iResult'] = 0;
        } else {
            $data = $this->sendGameMessage('CMD_MD_AYLLA_BONUS_TO_GAME', [$RoleID,$ID,$Status], "DC", 'returnComm');
        }
        
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            (new \app\model\DataChangelogsDB())
                ->getTableObject('T_AyllaWithdrawHistory')
                ->where('ID',$ID)
                ->data([
                    'Status'=>0,
                    'CheckTime'=>date('Y-m-d H:i:s'),
                    'CheckUser'=>session('username')
                ])
                ->update();
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    /**
     * 审核历史记录
     * @return mixed
     */
    public function historyCheckRecord()
    {
        return $this->fetch();
    }

    public function detailsOfRewardIncrease(){
        $config = (new \app\model\MasterDB())->getTableObject('T_AyllaCfg')->group('AyllaType,Description')->field('AyllaType,Description')->select();
        $pddconfig = [];
        foreach ($config as $key => &$val) {
            $pddconfig[$val['AyllaType']] = $val['Description'];
        }

        if (input('action') == 'list') {
            $limit          = request()->param('limit') ?: 15;
            $RoleID         = request()->param('RoleID');
            $start_date     = request()->param('start_date');
            $end_date       = request()->param('end_date');
            $type           = request()->param('type');
            $Description    = request()->param('Description');

            $orderby = input('orderby', 'AddTime');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($RoleID != '') {
                $where .= ' and a.RoleID=' . $RoleID;
            }
            if ($start_date != '') {
                $where .= ' and a.AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.AddTime<\'' . $end_date . '\'';
            }

            if ($type != '') {
                $where .= ' and a.ItemType='.$type;
            }

            if ($Description != '') {
                $where .= ' and a.ItemType='.$Description;
            }
            $data = (new \app\model\DataChangelogsDB())->getTableObject('T_AyllaBetBonusRecord')->alias('a')
                ->where($where)
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                if ($val['ItemType'] != 4) {
                    $val['ItemValue'] = FormatMoney($val['ItemValue']).'(金额)';
                } else {
                    $val['ItemValue'] = $val['ItemValue'].'(次数)';
                }
                $val['Description'] = $pddconfig[$val['ItemType']];
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {

            $this->assign('pddconfig',$pddconfig);
            return $this->fetch();
        }
    }


    /**
     * 分享统计
     */
    public function sharingStatistics()
    {
        if (input('action') == 'list') {
            $limit          = request()->param('limit') ?: 15;
            $RoleID         = request()->param('RoleID');

            $orderby = input('orderby', 'ProxyId');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($RoleID != '') {
                $where .= ' and b.ProxyId=' . $RoleID;
            }
            if ($orderby == 'PreDrawMoney') {
                $orderby = 'RotaryBonus';
            }
            $data = (new \app\model\UserDB())->getTableObject('T_ProxyCollectData')->alias('b')
                    ->join('[T_UserAyllaBet](nolock) a','b.ProxyId=a.RoleId','LEFT')
                    ->where($where)
                    ->field('a.RotaryBonus,a.PGBonus,a.AyllaBetTimes,a.TotalCompleteBonus,b.ProxyId,b.Lv1PersonCount,b.Lv1Deposit,b.Lv1DepositPlayers,b.Lv1WithdrawAmount,(b.Lv1Deposit*1000-b.Lv1WithdrawAmount) as retowi')
                    ->order("$orderby $orderType")
                    ->paginate($limit)
                    ->toArray();
            
            foreach ($data['data'] as $key => &$val) {
                $val['PreDrawMoney'] = FormatMoney($val['RotaryBonus']);
                $val['PGBonus'] = FormatMoney($val['PGBonus']);
                $val['TotalCompleteBonus'] = FormatMoney($val['TotalCompleteBonus']);
                $val['Lv1WithdrawAmount'] = FormatMoney($val['Lv1WithdrawAmount']);
                $val['retowi'] = FormatMoney($val['retowi']);
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }

    }

    /**
     * 手动发放奖励
     * @return mixed
     */
    public function handleAddReward()
    {
        $roleid = $this->request->param('roleid');
        $amount = $this->request->param('amount');
        $type = $this->request->param('type');
        $addorsub = $this->request->param('addorsub');

        if ($type == 0) {
            $amount = $amount * bl;
        }
        if ($addorsub == 1) {
            $amount = -$amount;
        }
        $data = $this->sendGameMessage('CMD_MD_PG_ADD_AYLLA_BONUS', [$roleid, $type, $amount], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    /**
     * 转盘手机号码列表
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function phoneList()
    {
        if ($this->request->isAjax()) {
            $page = input('page');
            $limit = input('limit');
            $phone = input('phone');
            $masterDB = new MasterDB();
            $count = $masterDB->getTableObject('T_PDDCode')
                ->count();
            $phoneList = $masterDB->getTableObject('T_PDDCode')
                ->where(function ($q) use ($phone) {
                    if ($phone) {
                        $q->where('Code', 'Code', $phone);
                    }
                })
                ->page($page, $limit)
                ->select();
            $data['count'] = $count;
            $data['list'] = $phoneList;
            return $this->apiJson($data);
        }
        return $this->fetch();
    }

    /**
     * 转盘手机号码列表添加号码
     * @return mixed
     * @throws \think\exception\PDOException
     */
    public function phoneListAdd()
    {
        $phone = input('phone');
        $phones = explode(',', $phone);
        $masterDB = new MasterDB();
        try {
            $masterDB->startTrans();
            $data = [];
            foreach ($phones as $phone) {
                if (empty($phone)) {
                    continue;
                }
                $item = [];
                $item['Code'] = $phone;
                $data[] = $item;
            }

            $add = $masterDB->getTableObject('T_PDDCode')
                ->insertAll($data);
            // 提交事务
            $masterDB->commit();
            if ($add) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (\Exception $e) {
            // 回滚事务
            $masterDB->rollback();
            return $this->apiReturn(1, '', '添加操作失败');
        }

    }

    /**
     * 转盘手机号码删除号码
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function phoneListDelete()
    {
        $id = input('id');
        $type = input('type');
        $masterDB = new MasterDB();
        if ($type == 1) {
            //单个删除
            $del = $masterDB->getTableObject('T_PDDCode')
                ->delete($id);
            if ($del) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } else {
            //批量删除
            $ids = explode(',', $id);
            try {
                $masterDB->startTrans();
                $del = $masterDB->getTableObject('T_PDDCode')
                    ->delete($ids);
                // 提交事务
                $masterDB->commit();
                if ($del) {
                    return $this->apiReturn(0, '', '操作成功');
                } else {
                    return $this->apiReturn(1, '', '操作失败');
                }
            } catch (\Exception $e) {
                // 回滚事务
                $masterDB->rollback();
                return $this->apiReturn(1, '', '操作失败');
            }

        }

    }
}