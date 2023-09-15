<?php

namespace socket;

/**
 * Class QuerySocket
 * @package socket
 * Main 控制器
 * sendGameMessage() 调用的是 这里的 callback
 */
class QuerySocket
{
    /**
     * Notes:查询房间
     * @param $roomid
     * @return mixed
     */
    public function getRoomInfo($roomid) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');

        $sendQuery = new sendQuery();

        $sendQuery->CMD_MD_QUERY_ROOM_CTRL_DATA($socket, $roomid);
        $out_data = $socket->response();

        $change = new ChangeData();
        $out_array = $change->ProcessDMQueryRoomRate($out_data);
        return $out_array;
    }

//    public function getRoomNum($roomid) {
//
//        $comm = new Comm();
//        $socket = $comm->getSocketInstance('DC');
//        $sendQuery = new sendQuery();
//        $sendQuery->SendMDQueryNum($socket, $roomid);
//        $out_data = $socket->response();
//        $change = new ChangeData();
//        $out_array = $change->ProcessDMQueryRoomNum($out_data);
//        return $out_array;
//    }

//    public function callback($funcName, $parameter, $conSrv = "DC", $changeFunc = null) {
//        $comm = new Comm();
//        $socket = $comm->getSocketInstance($conSrv);
//        array_unshift($parameter, $socket);//往参数的头部插入 socket
//        $sendQuery = new sendQuery();
//        $change = new ChangeData();
//        call_user_func_array([$sendQuery, $funcName], $parameter);
//        $out_data = $socket->response();
//        if (!empty($changeFunc)) {
//            $out_data= call_user_func([$change, $changeFunc], $out_data);
//        }
//        return $out_data;
//    }

    //设置房间
    public function setRoom($roomid, $ratio, $initStorage, $currentStorage, $szStorageRatio) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_ROOM_CTRL_DATA($socket, $roomid, $ratio, $initStorage, $currentStorage, $szStorageRatio);

        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }

    //设置机器人数量
    public function setNum($roomid, $szStorageRatio) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_ROOM_ROBOT_CTRL_DATA($socket, $roomid, $szStorageRatio);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }


    //账号充值
    public function addRoleMoney($roleId, $money, $ntype, $status = 0, $ipaddr) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_ADD_ROLE_MONERY($socket, $roleId, $money, $ntype, $status, $ipaddr);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }

    //激活房间机器人
    public function DCActiveRoomRobot($iRoomID) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_ACTIVE_ROOM_ROBOT($socket, $iRoomID);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }

    public function addRoleMoneyYuer($roleId, $money) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_ADD_ROLE_SCORE($socket, $roleId, 5000, 0, $money);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }

    public function DCQueryRoomRobotInfo($curPage, $pageSize) {

        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_QUERY_ROOM_ROBOT_INFO($socket, $curPage, $pageSize);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQueryRoomRobotInfoRes($out_data);
        return $out_array;
    }

    public function DCQueryRoomRobotNum($nRoomId) {

        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
//        $sendQuery->SendMDQueryRoomRobotInfo($socket, $curPage,$pageSize);

        $sendQuery->CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA($socket, $nRoomId);
        $out_data = $socket->response();
        print_r($out_data);
        die;

        $out_array = $change->ProcessDMQueryRoomRobotInfoRes($out_data);
//        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);

        return $out_array;
    }

    public function getRoomRobotInfo($curPage, $pageSize) {
        $arrResult = $this->DCQueryRoomRobotInfo($curPage, $pageSize);
        $keyMap = array("iRoomID" => "RoomID", "iOnLineCount" => "OnLineCount", "iRobotCount" => "RobotCount"
        , "iUpdateTime" => "UpdateTime", "iRobotWinMoney" => "RobotWinMoney");
        if (is_array($arrResult) && $arrResult['iRoomCount'] > 0)
            $this->arrListReplaceKey($arrResult['RoomRobotInfoList'], $keyMap);
        return $arrResult;
    }

    public function getRoomRobotNum($nRoomId) {
//        $arrResult = $this->DCQueryRoomRobotInfo($curPage,$pageSize);

        $arrResult = $this->DCQueryRoomRobotNum($nRoomId);

        $keyMap = array("iRoomID" => "RoomID", "iOnLineCount" => "OnLineCount", "iRobotCount" => "RobotCount"
        , "iUpdateTime" => "UpdateTime", "iRobotWinMoney" => "RobotWinMoney");
        if (is_array($arrResult) && $arrResult['iRoomCount'] > 0)
            $this->arrListReplaceKey($arrResult['RoomRobotInfoList'], $keyMap);
        return $arrResult;
    }

    public function arrListReplaceKey(&$arr, $keyMap) {
        if (empty($arr)) {
            return null;
        }

        foreach ($arr as $key => $val) {
            $arr[$key] = self::arrReplaceKey($val, $keyMap);
        }
    }


    //玩家胜率
    public function setRoleRate($roleId, $ratio, $timelong, $timeinterval,$InitialPersonMoney=0) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_WD_SET_USER_CTRL_DATA($socket, $roleId, $ratio, $timelong, $timeinterval,$InitialPersonMoney);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }


    ///老虎机概率设置
    public function setTigerRoleRate($roleId, $ratio) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_TIGER_CTRL_VALUE($socket, $roleId, $ratio);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }


    //添加ip黑名单
    public function setBlackList($ip, $type) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->AddBlackIp($socket, $ip, $type);
        $out_data = $socket->response();
        $out_array = $change->returnAddBlack($out_data);
        return $out_array;
    }

    //同步数据
    public function sychron() {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_RELOAD_GAME_DATA($socket, 0);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }


    //玩家胜率
    public function setRoleRate2($roleId, $ratio, $timelong, $timeinterval) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_WD_SET_USER_CTRL_DATA($socket, $roleId, $ratio, $timelong, $timeinterval);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }

    //更新银行卡信息
    public function updateBank($roleid, $username, $bankcardno, $bankname,$ifsccode,$mail,$payway) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDCtrolBank($socket, $roleid, $username, $bankcardno, $bankname,$ifsccode,$mail,$payway);
        $out_data = $socket->response();
        $out_array = $change->returnAddBank($out_data);
        return $out_array;
    }

    //解绑银行卡信息
    public function unbindBank($roleid,$payway) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        // $change = new ChangeData();
        $sendQuery->SendMDCtrolUnbindBank($socket, $roleid,$payway);
        $out_data = $socket->response();
        // $out_array = $change->returnAddBank($out_data);
        return $out_data;
    }

    //查询角色状态
    public function searchRoleStatus($roleid) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('AS');
        $sendQuery = new omToAsSocket();
        $change = new ChangeData();

        $sendQuery->SendMAGetRoleBaseInfo($socket, $roleid);
        $out_data = $socket->response();
//        $out_array = $change->ProcessAMRoleOperateAckRes($out_data);
        $out_array = $this->ProcessAMGetRoleBaseInfoRes($out_data);

        //1 锁定
        if ($out_array["iLocked"] === 1) {
            return 4;
        } else {
            return 3;
        }


    }

    function ProcessAMGetRoleBaseInfoRes($out_data) {
        //echo "ProcessAMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('a64szLoginName/LiLoginID/a16szMobilePhone/a24IdCard/a12szQQ/LiMoorMachine/a33szMachineSerial/LiLockStartTime/LiTitleID/LiLockEndTime/LiLocked/LiLoginCount/a17szLastLoginIP/LiLastLoginTime/a17szRegIP/LiAddTime/LiBlockStartTime/LiBlockEndTime/LiBlocked/a10szPlayerName/a50szWeChat/', $out_data);
        //print_r($out_data_array);
        //print_r($out_data_array);
        //echo "<br />";
        $this->fitStr($out_data_array['szLoginName']);
        $this->fitStr($out_data_array['szMobilePhone']);
        $this->fitStr($out_data_array['IdCard']);
        $this->fitStr($out_data_array['szQQ']);
        $this->fitStr($out_data_array['szMachineSerial']);
        $this->fitStr($out_data_array['szLastLoginIP']);
        $this->fitStr($out_data_array['szRegIP']);
        $this->fitStr($out_data_array['szPlayerName']);
        $this->fitStr($out_data_array['szWeChat']);
        $out_array = $out_data_array;

        return $out_array;
    }

    function fitStr(&$str) {
        //$str = substr($str,0,strpos($str,0));//取到0结束
        $str = trim($str);
        $str = iconv('GBK', 'UTF-8', $str);
    }

    public function arrReplaceKey($arr, $keyMap) {
        $brr = array();
        foreach ($arr as $key => $val) {
            if (isset($keyMap[$key])) {
                $brr[$keyMap[$key]] = $val;
            } else {
                $brr[$key] = $val;
            }
        }
        return $brr;
    }

    //更新角色状态--锁定
    public function lockRoleStatus($roleid, $day) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('AS');
        $sendQuery = new omToAsSocket();
        $change = new ChangeData();
//        $sendQuery->SendMAResetLoginPwd($socket,$roleid, $day);
        $sendQuery->SendMDCtrolRole($socket, $roleid, $day);
        $out_data = $socket->response();
//        $out_array = $change->ProcessAMRoleOperateAckRes($out_data);
        $out_array = $change->ProcessAMRoleOperateAckRes($out_data);
        return $out_array;
    }

    //更新角色状态--解锁
    public function unlockRoleStatus($roleid) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('AS');
        $sendQuery = new omToAsSocket();
        $change = new ChangeData();

        $sendQuery->SendMDCtrolUnlockRole($socket, $roleid);

        $out_data = $socket->response();

        $out_array = $change->ProcessAMRoleOperateAckRes($out_data);

        return $out_array;
    }

    public function addAuthProcess($iRoleID, $iLoginID, $strLoginName, $iOperationType, $ExtendID, $iNumber, $strPayment, $strReason, $strRemarks, $iRequirement, $ShowType, $SysUserName, $iFID) {
        /*if(!$iLoginID && !$strLoginName){
            $objUserDAL = new UserDAL($iRoleID);
            $arrRoleInfo = $objUserDAL->getRoleInfo($iRoleID);
            $iLoginID = $arrRoleInfo['LoginID'];
            $strLoginName = $arrRoleInfo['LoginName'];
        }*/
        $iLoginID = $iRoleID;
        $params = array(array($iRoleID, SQLSRV_PARAM_IN),
            array($iLoginID, SQLSRV_PARAM_IN),
            array($this->utf8ToGb2312($strLoginName), SQLSRV_PARAM_IN),
            array($iOperationType, SQLSRV_PARAM_IN),
            array($ExtendID, SQLSRV_PARAM_IN),
            array($iNumber, SQLSRV_PARAM_IN),
            array($this->utf8ToGb2312($strPayment), SQLSRV_PARAM_IN),
            array($this->utf8ToGb2312($strReason), SQLSRV_PARAM_IN),
            array($this->utf8ToGb2312($strRemarks), SQLSRV_PARAM_IN),
            array($this->utf8ToGb2312($iRequirement), SQLSRV_PARAM_IN),
            array($ShowType, SQLSRV_PARAM_IN),
            array($SysUserName, SQLSRV_PARAM_IN),
            array($iFID, SQLSRV_PARAM_IN));
        $arrReturns = $this->objSystemDB->fetchAssoc("Proc_AuthProcess_Insert", $params);
        return $arrReturns;
    }

    public function utf8ToGb2312($str) {
        if (!empty($str))
            return iconv('utf-8', 'gb2312//IGNORE', $str);
        else
            return $str;
    }

    function getCaseOperateUser($iRoleID, $OperationType) {
        $params = array(array($iRoleID, SQLSRV_PARAM_IN),
            array($OperationType, SQLSRV_PARAM_IN));

        $arrReturns = $this->fetchAllAssoc("Proc_CaseOperateUser_Select", $params);
        if ($arrReturns && count($arrReturns) > 0) {
            $i = 0;
            foreach ($arrReturns as $v) {
                $arrReturns[$i]['LoginName'] = Utility::gb2312ToUtf8($v['LoginName']);
                $arrReturns[$i]['Requirement'] = Utility::gb2312ToUtf8($v['Requirement']);
                $arrReturns[$i]['Reason'] = Utility::gb2312ToUtf8($v['Reason']);
                $arrReturns[$i]['Remarks'] = Utility::gb2312ToUtf8($v['Remarks']);
                $i++;
            }
        }
        return $arrReturns;
    }

    function getUserLoginName($RoleID) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('AS');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDCtrolUnlockRole($socket, $RoleID);
        $out_data = $socket->response();
        $out_array = $change->returnRolestatusUnlock($out_data);
        return $out_array;
    }


    //超级玩家增加
    public function setSuperPlayer($roleid, $level) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_SUPER_PLAYER($socket, $roleid, $level);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }


    //修改玩家密码
    public function setPlayerPwd($roleid, $pwd) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('AS');
        $sendQuery = new omToAsSocket();
        $change = new ChangeData();
        $sendQuery->CMD_MA_RESET_LOGIN_PWD($socket, $roleid, md5($pwd));
        $out_data = $socket->response();
        $out_array = $change->ProcessAMRoleOperateAckRes($out_data);
        return $out_array;
    }


    //获取银行账户
    public function getSystembankdata() {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_QUERY_SYSTEM_BANK_DATA($socket, 0);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQuerySystemBankDataRes($out_data);
        return $out_array;
    }

    //获取玩家银行
    public function getRoleTotalMoney() {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDQueryRoleTotalMoney($socket, 0);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQueryRoleTotalMoneyRes($out_data);
        $keyMap = array("iRoleBankTotalMoney" => "TotalBankMoney", "iRoleGameTotalMoney" => "TotalGameMoney");
        $brr = array();
        foreach ($out_array as $key => $val) {
            if (isset($keyMap[$key])) {
                $brr[$keyMap[$key]] = $val;
            } else {
                $brr[$key] = $val;
            }
        }
        return $brr;
    }

    //转账
    public function sysBankDeal($fromacc, $toacc, $money) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDSysBankDeal($socket, $fromacc, $toacc, $money);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSysBankDealAckRes($out_data);
        return $out_array;
    }


    //设置盈利千分比、偏离调整值
    public function setProfitPercent($nType, $nSetRange, $nId, $nProfilePercent, $nAdjustValue,$nRoomCtrl, $lCurStorage, $lMinStorage, $lMaxStorage) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_PROFIT_PERCENT($socket, $nType, $nSetRange, $nId, $nProfilePercent, $nAdjustValue,$nRoomCtrl, $lCurStorage, $lMinStorage, $lMaxStorage);
        $out_data = $socket->response();
        $out_array = $change->returnSetProfit($out_data);
        return $out_array;
    }

    //获取盈利千分比
    public function getProfitPercent($roomId) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_QUERY_RUNNING_CTRL_DATA($socket, $roomId);
        $out_data = $socket->response();
        $out_array = $change->ProcessQueryRunningCtrlData($out_data);
        return $out_array;
    }

    //获取
    public function getRobotNum($roomId) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDQueryRunningCtrlNum($socket, $roomId);
        $out_data = $socket->response();
        $out_array = $change->ProcessQueryRunningCtrlData($out_data);
        return $out_array;
    }

    public function getRoomInfo2($roomid) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');

        $sendQuery = new sendQuery();

        $sendQuery->CMD_MD_QUERY_ROOM_CTRL_DATA($socket, $roomid);
        $out_data = $socket->response();
        $change = new ChangeData();
        $out_array = $change->ProcessDMQueryRoomRate($out_data);
        return $out_array;
    }

    //玩家强退
    public function setForceQuit($roleId) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_FORCE_QUIT_ROL($socket, $roleId);
        $out_data = $socket->response();
        $out_array = $change->returnForceQuit($out_data);
        return $out_array;
    }

    //在线
    public function DCQueryAllOnlinePlayer() {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDQueryOnlinePlayers($socket);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQueryAllOnlinePlayerRes($out_data);
        return $out_array;
    }

    //设置捕鱼比例
    public function setFishrate($roomId, $nSysTaxRatio, $nCaiJinRatio, $nExplicitTaxRatio) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDSetFishRoomRatio($socket, $roomId, $nSysTaxRatio, $nCaiJinRatio, $nExplicitTaxRatio);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;
    }


    public function sentMailBox($roleId, $iRecordType, $iExtraType, $iAmount, $iDelaySecs, $mailtxt, $title) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDSendMailBox($socket, $roleId, $iRecordType, $iExtraType, $iAmount, $iDelaySecs, $mailtxt, $title);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }

    public function sentPlayerMail($sendid, $roleId, $iRecordType, $iExtraType, $iAmount, $iDelaySecs, $mailtxt, $title) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDSendPlayerMail($socket, $sendid, $roleId, $iRecordType, $iExtraType, $iAmount, $iDelaySecs, $mailtxt, $title);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMSetRoomRate($out_data);
        return $out_array;
    }

    //玩家使用优惠券
    public function UsePlayerCoupon($nAccountId, $nUseCouponCount) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDPlayerUseConpon($socket, $nAccountId, $nUseCouponCount);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;
    }

    public function getPlayerCoupon($nAccountId) {
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWGetPlayerConpon($socket, $nAccountId);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;
    }


    ///捕鱼玩家上分
    public function UpFishScore($bTransfer,$nAccountId,$lGameMoney,$orderid){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDSendUserBalanceNotice($socket,$bTransfer,$nAccountId,25000,$lGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }


    ///捕鱼玩家下分
    public function downFishScore($nAccountId,$iGameMoney,$orderid){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDUserDownScore($socket, $nAccountId,25000,$iGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }



    ///体育上分
    public function downLotteryScore($nAccountId,$iGameMoney,$orderid,$KindId=40000){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDUserDownScore($socket, $nAccountId,$KindId,$iGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }



    ///体育上分
    public function UpLotteryScore($bTransfer,$nAccountId,$lGameMoney,$orderid,$KindId=40000){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDSendUserBalanceNotice($socket,$bTransfer,$nAccountId,$KindId,$lGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }

    //加钱
    public function UpScore($nAccountId,$lGameMoney,$orderid,$KindId=26000){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_THIRD_PARTY_TRANSFER_ADD($socket,$nAccountId,$KindId,$lGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }

    public function UpScore2($nAccountId,$lGameMoney,$orderid,$KindId=26000,$llGameBets=''){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_THIRD_PARTY_TRANSFER_ADD2($socket,$nAccountId,$KindId,$lGameMoney,$orderid,$llGameBets);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }

    public function ClearLablel($nAccountId,$KindId){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_THIRD_PARTY_CLEAR_INGAME_LABEL($socket,$nAccountId,$KindId);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;

    }

    //扣钱
    public function downScore($nAccountId,$lGameMoney,$orderid,$KindId=26000){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_THIRD_PARTY_TRANSFER_SUB($socket,$nAccountId,$KindId,$lGameMoney,$orderid);
        $out_data = $socket->response();
        $out_array = $change->returnComm($out_data);
        return $out_array;
    }


    /*
 * 查询玩家账号余额并扣除
 */
    public function getPlayerBalance($nAccountId){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SeWDQueryUserBalance($socket, $nAccountId);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQueryUserBalance($out_data);
        return $out_array;
    }


    //冻结解冻财富
    public function DSLockRoleMonery($RoleID,$Monery){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDLockRoleMonery($socket,$RoleID,$Monery);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }


    ///查询冻结金额
    public function DSQueryRoleBankInfo($RoleID){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDQueryRoleBankInfo($socket,$RoleID);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMQueryRoleBankInfoRes($out_data);
        return $out_array;
    }

    ///查询玩家余额
    public function DSQueryRoleBalance($RoleID){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_GET_ROLE_BASE_INFO($socket,$RoleID);
        $out_data = $socket->response();
        if (config('is_portrait') == 1) {
            $out_array = $change->ProcessDMQueryRolebalance($out_data);
        } else {
            $out_array = $change->ProcessDMQueryRoleBankInfoRes($out_data);
        }
        return $out_array;
    }

    public function unLockMoney($RoleID){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDUnLockMoney($socket,$RoleID);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;

    }



    public function SendSystemMailV2($iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $PayOrder,$WageMul,$iDelaySecs, $mailType, $title, $mailtxt, $Note,$Country,$operator){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SYSTEM_MAILv2($socket,$iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $PayOrder,$WageMul,$iDelaySecs, $mailType, $title, $mailtxt, $Note,$Country,$operator);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;

    }


    public function SetRoleRight($iRoleID, $iUserRight, $iMasterRight, $iSystemRight){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->SendMDSetRoleRight($socket,$iRoleID, $iUserRight, $iMasterRight, $iSystemRight);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }


    public function SetRoleVipLevel($iRoleID,  $iVipLevel){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_WD_SET_VIP($socket,$iRoleID, $iVipLevel);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }


    //设置房间吃水、放水
    public function SeWDSetRoomWater($nSetRange,$nId,$CurWaterIn,$CurWaterOut){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_ROOM_WATER_DATA($socket,$nSetRange,$nId,$CurWaterIn,$CurWaterOut);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }



    //预设指定期数游戏结果
    public function SeMDSetRoomCtrl($operator_id,$round_id,$result){
        $comm = new Comm();
        $socket = $comm->getSocketInstance('DC');
        $sendQuery = new sendQuery();
        $change = new ChangeData();
        $sendQuery->CMD_MD_SET_SPACE_INFO($socket,$operator_id,$round_id,$result);
        $out_data = $socket->response();
        $out_array = $change->ProcessDMRoleOperateAckRes($out_data);
        return $out_array;
    }





}



