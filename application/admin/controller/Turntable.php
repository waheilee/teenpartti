<?php

namespace app\admin\controller;

use app\common\GameLog;
use app\model\BankDB;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\UserDB;
use think\Db;

class Turntable extends Main
{

    /**
     * 分享统计
     * @return mixed|\think\response\Json
     */
    public function sharingStatistics()
    {
        //按照降序排序，可以看到该分享人分享的次数/注册人数 Lv1PersonCount /充值人数 Lv1FirstDepositPlayers /充值金额DailyDeposit/取款金额/存提差
        //后面有两个功能 一个是增加第三点的附带金额。
        //如果对方有提交领取申请，则有一个领取审核通过按钮用于发放奖金。
        //具体样式参考代理明细页面，包含筛选。
        //充值金额： [OM_GameOC].[dbo].[T_BankWeathChangeLog_所有日表
        // ChangeType = (47)，为这个用户的个人充值金额
        //个人取款金额   [OM_BankDB].[dbo].[UserDrawBack]  ，玩家id       ,[AccountID]
        //     金额  ,[iMoney]
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $startdate = input('startdate', '');
        $enddate = input('enddate', '');
        $page = input('page');
        $limit = input('limit');
        $orderField = input('orderfield', 'DailyDeposit');
        $orderType = input('ordertype', 'desc');
        if ($orderField == 'Lv1PersonCount') {
            $order = "$orderField $orderType";
        } else {
            $order = "depo.$orderField $orderType";
        }

        $where = '1=1';
        if ($roleid) {
            $where .= ' and Parent.AccountID=' . $roleid;
            $offset = "";
        } else {
            $offset = "OFFSET $page ROWS FETCH NEXT $limit ROWS ONLY";
        }

        switch (input('Action')) {
            case 'list':
                $userDB = new UserDB();
                $count = $userDB->getTableObject('View_Accountinfo')->count();
                $sql = "SELECT
                            Parent.AccountID AS AccountID,
                            COUNT(DISTINCT Child.AccountID) AS Lv1PersonCount,
                            depo.DailyDeposit,
                            depo.Lv1FirstDepositPlayers
                        FROM
                            View_Accountinfo Parent
                        LEFT JOIN
                            View_Accountinfo Child ON Parent.AccountID = Child.ParentID
                        LEFT JOIN (
                            SELECT
                                p.ParentID,
                                COALESCE(SUM(Child.TransMoney), 0) AS DailyDeposit,
                                COALESCE(COUNT(*), 0) AS Lv1FirstDepositPlayers
                            FROM
                                View_Accountinfo p
                            LEFT JOIN
                                [CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs] Child ON p.AccountID = Child.RoleID
                            WHERE
                                Child.IfFirstCharge = 1
                            GROUP BY
                                p.ParentID
                        ) AS depo ON depo.ParentID = Parent.AccountID
                        WHERE $where
                        GROUP BY
                            Parent.AccountID,
                            depo.DailyDeposit,
                            depo.Lv1FirstDepositPlayers
                        ORDER BY $order $offset ;";
                $users = $userDB->getTableQuery($sql);
//                dump($users);die();
//
//                $users = $userDB->getTableObject('View_Accountinfo')
//                    ->where(function ($q) use($roleid){
//                        if ($roleid){
//                            $q->where('AccountID',$roleid);
//                        }
//                    })
//                    ->page($page,$limit)
//                    ->select();
                $data = [];
                foreach ($users as $user) {
                    $item = [];


                    $item['Lv1PersonCount'] = $user['Lv1PersonCount'];
                    $item['DailyDeposit'] = $user['DailyDeposit'] ?? 0;
                    $item['Lv1FirstDepositPlayers'] = $user['Lv1FirstDepositPlayers'] ?? 0;
                    $userBankDB = new BankDB();
                    $takeMoney = $userBankDB->getTableObject('UserDrawBack')
                        ->where('AccountID', $user['AccountID'])
                        ->sum('iMoney') ?? 0;
                    $item['takeMoney'] = $takeMoney / bl;
                    if ($item['DailyDeposit'] == 0 && $item['takeMoney'] > 0) {
                        $item['difference'] = '-' . $item['takeMoney'];
                    } else {
                        $item['difference'] = bcsub($item['DailyDeposit'], $item['takeMoney'], 2);
                    }
                    $turntableMoney = $userDB->getTableObject('T_Job_UserInfo')
                        ->where('RoleID', $user['AccountID'])
                        ->where('job_key', 10014)
                        ->sum('value') ?? 0;
                    $item['Money'] = $turntableMoney / bl;
                    $addMoney = $userDB->getTableObject('T_Job_UserInfo')
                        ->where('RoleID', $user['AccountID'])
                        ->where('job_key', 10015)
                        ->sum('value') ?? 0;
                    $item['addMoney'] = FormatMoney($addMoney);
                    $item['ProxyId'] = $user['AccountID'];
                    $data[] = $item;
                }
                $result['count'] = $count;
                $result['list'] = $data;

                return $this->apiJson($result);

        }

        $this->assign('parentid', $parentid);
        $this->assign('roleid', $roleid);
        $this->assign('startdate', $startdate);
        $this->assign('enddate', $enddate);
        return $this->fetch();

    }

    /**
     * 转盘审核记录
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkRecord()
    {
        //$checkName = session('username');//审核人员

        if ($this->request->isAjax()) {
            $roleId = input('roleid');
            $commitStartTime = input('commit_start_time');
            $commitEndTime = input('commit_end_time');
            $passStartTime = input('pass_start_time');
            $passEndTime = input('pass_end_time');
            $checkUser = input('check_user');
            $page = input('page');
            $limit = input('limit');
            $historyList = input('history', 0);
            if (input('Action') == 'list') {
                $userDB = new UserDB();
                $checkRecordData = [];
                if (empty($historyList)) {
                    $count = $userDB->getTableObject('T_PDDCommi')
                        ->where(function ($q) use ($roleId) {
                            if (!empty($roleId)) {
                                $q->where('RoleId', $roleId);
                            }
                        })
                        ->where('GetType', 0)
                        ->count();
                    $checkRecord = $userDB->getTableObject('T_PDDCommi')
                        ->where(function ($q) use ($roleId) {
                            if (!empty($roleId)) {
                                $q->where('RoleId', $roleId);
                            }
                        })
                        ->where('GetType', 0)
                        ->page($page, $limit)
                        ->select();
                } else {
                    $count = $userDB->getTableObject('T_PDDCommi')
                        ->where(function ($q) use ($roleId) {
                            if (!empty($roleId)) {
                                $q->where('RoleId', $roleId);
                            }
                        })
                        ->where(function ($q) use ($checkUser) {
                            if (!empty($checkUser)) {
                                $q->where('Commi', 'like', '%' . $checkUser . '%');
                            }
                        })
                        ->where(function ($q) use ($commitStartTime, $commitEndTime) {
                            if (!empty($commitStartTime)) {
                                $q->where('CommiTime', '>', $commitStartTime);
                            }
                            if (!empty($commitEndTime)) {
                                $q->where('CommiTime', '<', $commitEndTime);
                            }
                            if (!empty($commitStartTime) && !empty($commitEndTime)) {
                                $q->where('CommiTime', 'between time', [$commitStartTime, $commitEndTime]);
                            }
                        })
                        ->whereIn('GetType', [1, 2])
                        ->count();
                    $checkRecord = $userDB->getTableObject('T_PDDCommi')
                        ->where(function ($q) use ($roleId) {
                            if (!empty($roleId)) {
                                $q->where('RoleId', $roleId);
                            }
                        })
                        ->where(function ($q) use ($checkUser) {
                            if (!empty($checkUser)) {
                                $q->where('Commi', 'like', '%' . $checkUser . '%');
                            }
                        })
                        ->where(function ($q) use ($commitStartTime, $commitEndTime) {
                            if (!empty($commitStartTime)) {
                                $q->where('CommiTime', '>', $commitStartTime);
                            }
                            if (!empty($commitEndTime)) {
                                $q->where('CommiTime', '<', $commitEndTime);
                            }
                            if (!empty($commitStartTime) && !empty($commitEndTime)) {
                                $q->where('CommiTime', 'between time', [$commitStartTime, $commitEndTime]);
                            }
                        })
                        ->whereIn('GetType', [1, 2])
                        ->page($page, $limit)
                        ->select();
                }
                foreach ($checkRecord as $record) {
                    $item['id'] = $record['id'];
                    $item['RoleId'] = $record['RoleId'];
                    $item['CommiTime'] = $record['CommiTime'];
                    $item['PassTime'] = $record['PassTime'];
                    $item['Commi'] = $record['Commi'];
                    $item['GetType'] = $this->getTypeCheck($record['GetType']);
                    $item['GetAfter'] = FormatMoney($record['GetAfter']);
                    $turntableMoney = $userDB->getTableObject('T_PDDDrawHistory')
                        ->where('RoleId', $record['RoleId'])
                        ->where('ChangeType', 1)
                        ->whereIn('Item', [1, 2])
                        ->sum('ItemVal') ?? 0;
                    $item['Money'] = $turntableMoney / bl;

                    $addMoney = $userDB->getTableObject('T_Job_UserInfo')
                        ->where('RoleID', $record['RoleId'])
                        ->where('job_key', 10015)
                        ->sum('value') ?? 0;
                    $item['addMoney'] = FormatMoney($addMoney);
                    $checkRecordData[] = $item;
                }
//                var_dump($checkRecord);die();
                $data['count'] = $count;
                $data['list'] = $checkRecordData;
                return $this->apiJson($data);
            }
        }

        return $this->fetch();
    }

    /**
     * 审核历史记录
     * @return mixed
     */
    public function historyCheckRecord()
    {
        //操作人员
        $db = new UserDB();
        $checkUser = $db->getTableObject('View_UserDrawBack')
            ->group('checkUser')
            ->column('checkUser');
        $checkUser = array_keys($checkUser);
        if (!in_array(session('username'), $checkUser)) {
            $checkUser[] = session('username');
        }
        $this->assign('checkUser', $checkUser);
        $this->assign('adminuser', session('username'));
        return $this->fetch();
    }

    /**
     * 奖励增加详情
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function detailsOfRewardIncrease()
    {
        //11.	后台有一个用户每笔奖励增加的明细表，对应第一点的功能，每增加一个金额 都有一个明细，以及时间。
        //可筛选用户ID 增加类型 时间区间。T_PDDDrawHistory

        if ($this->request->isAjax()) {
            $roleId = input('roleid');
            $tranType = input('tranType');
            $startTime = input('start');
            $endTime = input('end');
            $page = input('page');
            $limit = input('limit');
            if (input('Action') == 'list') {
//                $map['RoleId'] = $roleId ?? '';
//                $map['Item'] = $tranType ?? '';
                $start = strtotime($startTime) ?? '';
                $end = strtotime($endTime) ?? '';

                $userDB = new UserDB();
                $count = $userDB->getTableObject('T_PDDDrawHistory')
                    ->where(function ($q) use ($roleId) {
                        if (!empty($roleId)) {
                            $q->where('RoleId', $roleId);
                        }
                    })
                    ->where(function ($q) use ($tranType) {
                        if (!empty($tranType)) {
                            $q->where('Item', $tranType);
                        }
                    })
                    ->where(function ($q) use ($startTime, $endTime) {
                        if (!empty($startTime)) {
                            $q->where('ChangeTime', '>', strtotime($startTime));
                        }
                        if (!empty($endTime)) {
                            $q->where('ChangeTime', '<', strtotime($endTime));
                        }
                        if (!empty($startTime) && !empty($endTime)) {
                            $q->where('ChangeTime', 'between time', [strtotime($startTime), strtotime($endTime)]);
                        }
                    })
                    ->where('ChangeType', 1)
                    ->whereIn('Item', [1, 2, 6])
                    ->count();
                $checkRecord = $userDB->getTableObject('T_PDDDrawHistory')
                    ->where(function ($q) use ($roleId) {
                        if (!empty($roleId)) {
                            $q->where('RoleId', $roleId);
                        }
                    })
                    ->where(function ($q) use ($tranType) {
                        if (!empty($tranType)) {
                            $q->where('Item', $tranType);
                        }
                    })
                    ->where(function ($q) use ($startTime, $endTime) {
                        if (!empty($startTime)) {
                            $q->where('ChangeTime', '>', strtotime($startTime));
                        }
                        if (!empty($endTime)) {
                            $q->where('ChangeTime', '<', strtotime($endTime));
                        }
                        if (!empty($startTime) && !empty($endTime)) {
                            $q->where('ChangeTime', 'between time', [strtotime($startTime), strtotime($endTime)]);
                        }
                    })
                    ->where('ChangeType', 1)
                    ->whereIn('Item', [1, 2, 6])
//                    ->whereTime('ChangeTime', 'between', [$start, $end])
                    ->limit($limit)
                    ->page($page)
                    ->select();
                $temp = [];
                foreach ($checkRecord as $record) {
                    $item = [];
                    $item['RoleId'] = $record['RoleId'];
                    $item['ChangeTime'] = date('Y-m-d H:i:s', $record['ChangeTime']);
                    $recordItem = '';
                    $recordItemVal = '';
                    switch ($record['Item']) {
                        case 1:
                            $recordItem = '大随机金额';
                            $recordItemVal = $record['ItemVal'] / bl;
                            break;
                        case 2:
                            $recordItem = '小随机金额';
                            $recordItemVal = $record['ItemVal'] / bl;
                            break;
                        case 6:
                            $recordItem = '获得随机次数';
                            $recordItemVal = $record['ItemVal'];
                            break;
                    }
                    $item['Item'] = $recordItem;
                    $item['ItemVal'] = $recordItemVal;
                    $temp[] = $item;
                }
                $data['count'] = $count;
                $data['list'] = $temp;
                return $this->apiJson($data);
            }
        }

        return $this->fetch();

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

        $send_dm = $amount * bl;
        $data = $this->sendGameMessage('CMD_MD_GM_PDD_ADD_MONEY', [$roleid, $type, $send_dm], "DC", 'returnComm');
        if ($data['iResult'] == 1) {
            if ($type == 1) {
                $comment = '增加玩家转盘奖励金：' . $amount;
            } else {
                $comment = '扣减玩家转盘奖励金:' . $amount;
            }
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 2,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);

            GameLog::logData(__METHOD__, [$roleid, $amount, $type], 1, $comment);
            return $this->apiReturn(0, '', '操作成功');
        } else {
            GameLog::logData(__METHOD__, [$roleid, $amount, $type], 0, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    /**
     * 审核
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function check()
    {
        $id = input('id');
        $roleId = input('role_id');
        $isPass = input('is_pass');
        $checkName = session('username');//审核人员
        $userDB = new UserDB();
        $isCheck = $userDB->getTableObject('T_PDDCommi')
            ->where('RoleId', $roleId)->find();
        $checkMoney = $userDB->getTableObject('T_Job_UserInfo')
            ->where('RoleID', $roleId)
            ->whereIn('job_key', [10014, 10015])
            ->sum('value') ?? 0;
        if ($isPass == 1) {
            $data = $this->sendGameMessage('CMD_MD_GM_PDD_COMMI_SUC', [$roleId, 1], "DC", 'returnComm');
            if ($data['iResult'] == 1) {

                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleId,
                    'adminid' => session('userid'),
                    'type' => 2,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => '玩家转盘审核通过'
                ]);

                $update = $userDB->getTableObject('T_PDDCommi')
                    ->where('id', $id)
                    ->where('RoleId', $roleId)
                    ->data([
                        'GetType' => $isPass,
                        'PassTime' => date('Y-m-d H:i:s'),
                        'Commi' => $checkName,
                        'GetAfter' => $checkMoney
                    ])
                    ->update();
                if ($update) {
                    GameLog::logData(__METHOD__, [$roleId], 1, '玩家转盘审核成功');
                    return $this->apiReturn(0, '', '操作成功');

                } else {
                    return $this->apiReturn(1, '', '更新操作失败');
                }

            } else {
                GameLog::logData(__METHOD__, [$roleId], 0, '操作失败');
                return $this->apiReturn(1, '', '操作失败');
            }
        } else {
            $data = $this->sendGameMessage('CMD_MD_GM_PDD_COMMI_SUC', [$roleId, 0], "DC", 'returnComm');
            if ($data['iResult'] == 1) {
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleId,
                    'adminid' => session('userid'),
                    'type' => 2,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => '玩家转盘审核拒绝'
                ]);

                $update = $userDB->getTableObject('T_PDDCommi')
                    ->where('id', $id)
                    ->where('RoleId', $roleId)
                    ->data([
                        'GetType' => $isPass,
                        'PassTime' => date('Y-m-d H:i:s'),
                        'Commi' => $checkName,
                        'GetAfter' => $checkMoney
                    ])
                    ->update();
                if ($update) {
                    GameLog::logData(__METHOD__, [$roleId], 1, '玩家转盘审核拒绝成功');
                    return $this->apiReturn(0, '', '操作成功');

                } else {
                    return $this->apiReturn(1, '', '更新操作失败');
                }
            } else {
                GameLog::logData(__METHOD__, [$roleId], 0, '操作失败');
                return $this->apiReturn(1, '', '操作失败');
            }

        }


    }

    /**
     * 转盘开关
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function turntableSwitch()
    {
        $switch = input('switch', '');
        $masterBD = new MasterDB();
        $updateSwitch = $masterBD->getTableObject('T_GameConfig')
            ->where('CfgType', 10140)
            ->find();
        if ($updateSwitch['CfgValue'] == $switch) {
            if ($updateSwitch['CfgValue'] == 10001) {
                return $this->apiReturn(1, '', '转盘已是开启状态');
            } else {
                return $this->apiReturn(1, '', '转盘已是关闭状态');
            }
        }

        $update = $masterBD->getTableObject('T_GameConfig')
            ->where('CfgType', 10140)
            ->update(['CfgValue' => $switch]);
        if ($update) {
            $data = $this->sendGameMessage('CMD_MD_RELOAD_GAME_DATA', [0], "DC", 'returnComm');
            if ($data['iResult'] == 0) {

                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => 0,
                    'adminid' => session('userid'),
                    'type' => 2,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => '玩家转盘审核'
                ]);

                return $this->apiReturn(0, '', '操作成功');
            } else {

                return $this->apiReturn(1, '', '操作失败');
            }
        } else {
            return $this->apiReturn(1, '', '更新操作失败');
        }

    }


    /**
     * 全网一键退款
     * @return mixed
     */
    public function onekeyBack()
    {
        $this->sendGameMessage('CMD_MD_GM_PDD_REFUND', [], "DC", 'returnComm');
        return $this->apiReturn(0, '', '操作成功');
    }

    /**
     * 修改玩家转盘次数
     * @return mixed
     */
    public function editTurntableNumber()
    {
        $roleId = input('roleid');
        $value = input('value');
        $data = $this->sendGameMessage('CMD_MD_GM_ADD_JOB', [$roleId, 10019, $value], "DC", 'returnComm');
        if ($data['iResult'] == 1) {

            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => 0,
                'adminid' => session('userid'),
                'type' => 2,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => '玩家转盘增加次数'
            ]);

            return $this->apiReturn(0, '', '操作成功');
        } else {

            return $this->apiReturn(1, '', '操作失败');
        }
    }

    /**
     * 获取审核类型
     * @param $type
     * @return string
     */
    public function getTypeCheck($type): string
    {
        $str = '';
        switch ($type) {
            case 0:
                $str = '未审核';
                break;
            case 1:
                $str = '已审核领取';
                break;
            case 2:
                $str = '已拒绝';
                break;
        }
        return $str;
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
        } elseif($type == 2) {
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

        }else{

            $masterDB->getTableObject('T_PDDCode')->where('1=1')->delete();
            return $this->apiReturn(0, '', '操作成功');
        }

    }

    /**
     * 周亏损奖励领取列表
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cashLostBack()
    {
        if ($this->request->isAjax()) {
            $page = input('page');
            $limit = input('limit');
            $date = input('begin_time');
//            $endTime = input('end_time');
            $roleId = input('roleid');
            $takeStatus = input('take_status');
            $orderBy = input('orderby');
            $orderType = input('ordertype');
            $masterDB = new UserDB();

            $count = $masterDB->getTableObject('T_UserCashLoseBack')
                ->where(function ($q) use ($roleId) {
                    if ($roleId) {
                        $q->where('RoleId', $roleId);
                    }
                })
                ->where(function ($q) use ($date) {
                    if (!empty($date)) {
                        $beginTime = $date . ' 00:00:00';
                        $endTime = $date . ' 23:59:59';
                        $beginTime = strtotime($beginTime);
                        $endTime = strtotime($endTime);
                        $q->where('BeginTime', 'between', [$beginTime, $endTime]);
                    }
                })
                ->where(function ($q) use ($takeStatus) {
                    if ($takeStatus == 1) {
                        $q->where('GetTime', '>', 0);
                    }
                    if ($takeStatus == 2) {
                        $q->where('GetTime', 0);
                    }
                })
                ->count();
            if (empty($orderBy)) {
                $orderBy = "WeekLoseMoney asc";
            } else {
                $orderBy = "$orderBy $orderType";
            }

            $lists = $masterDB->getTableObject('T_UserCashLoseBack')
                ->order($orderBy)
                ->where(function ($q) use ($roleId) {
                    if ($roleId) {
                        $q->where('RoleId', $roleId);
                    }
                })
                ->where(function ($q) use ($date) {
                    if (!empty($date)) {
                        $beginTime = $date . ' 00:00:00';
                        $endTime = $date . ' 23:59:59';
                        $beginTime = strtotime($beginTime);
                        $endTime = strtotime($endTime);
                        $q->where('BeginTime', 'between', [$beginTime, $endTime]);
                    }
                })
                ->where(function ($q) use ($takeStatus) {
                    if ($takeStatus == 1) {
                        $q->where('GetTime', '>', 0);
                    }
                    if ($takeStatus == 2) {
                        $q->where('GetTime', 0);
                    }
                })
                ->page($page, $limit)
                ->select();

            $temp = [];
            foreach ($lists as &$list) {
                if ($list['GetTime']) {
                    $list['GetTime'] = date('Y-m-d', $list['GetTime']);
                } else {
                    $list['GetTime'] = '未领取';
                }
                $list['cycle'] = date('Y-m-d', $list['BeginTime']) . '--' . date('Y-m-d', $list['EndTime']);
                $list['WeekLoseMoney'] = FormatMoney($list['WeekLoseMoney']);
                $list['CashBackMoney'] = FormatMoney($list['CashBackMoney']);
                $list['CashBackRate'] = bcdiv($list['CashBackRate'], 100) . '%';
                $temp[] = $list;

            }
            $data['count'] = $count;
            $data['list'] = $temp;
            return $this->apiJson($data);
        }
        return $this->fetch();
    }

    public function getTotal()
    {
        $date = input('begin_time');
//        $endTime = input('end_time');
        $takeStatus = input('take_status');
        $userDB = new UserDB();
        $data = $userDB->getTableObject('T_UserCashLoseBack')
            ->where(function ($q) use ($date) {
                if (!empty($date)) {
                    $beginTime = $date . ' 00:00:00';
                    $endTime = $date . ' 23:59:59';
                    $beginTime = strtotime($beginTime);
                    $endTime = strtotime($endTime);
                    $q->where('BeginTime', 'between', [$beginTime, $endTime]);
                }
            })
            ->where(function ($q) use ($takeStatus) {
                if ($takeStatus == 1) {
                    $q->where('GetTime', '>', 0);
                }
                if ($takeStatus == 2) {
                    $q->where('GetTime', 0);
                }
            })
            ->select();

        $resultArray = array_filter($data, function ($item) {
            return $item['GetTime'] > 0;
        });

        $cashBackMoneyTotal = $this->getArraySum($data, 'CashBackMoney');//总金额

        $cashMoneyTotal = $this->getArraySum($resultArray, 'CashBackMoney');

        return $this->apiReturn(200, [
            'cashBackMoneyTotal' => FormatMoney($cashBackMoneyTotal),
            'cashMoneyTotal' => FormatMoney($cashMoneyTotal),
        ]);
    }

    public function getArraySum($data, $key)
    {
        $amounts = array_column($data, $key);
        return array_sum($amounts);
    }


}