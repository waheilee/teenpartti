<?php

namespace socket;
// DC TO OM

//查询玩家列表
//UINT32        iRoleCount;             //用户数量
//SeRoleInfoDCToOM  akRoleInfoToOM[1];  //用户信息
//UINT32 iRoleID;                   //角色ID
//char szLoginName[64];         //角色名
//char szSignature[128];            //个性签名
//UINT32 iGender;                   //性别 0男1女
//UINT32 iVipID;                    //会员等级
//UINT32 iVipExpireTime;            //会员到期时间
//UINT32 iVipOpeningTime;           //会员开始时间
//UINT64 iHappyBeanMoney;           //金币总额
class ChangeData
{

    private $comm;

    public function __construct()
    {
        $this->comm = new Comm();
    }

    public function ProcessDMQueryRoleListRes($out_data)
    {
        //echo "ProcessDMQueryRoleListRes: <br />";
        $out_data_array = unpack('LiRoleCount/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        for ($x = 0; $x < $out_data_array['iRoleCount']; $x++) {
            //echo "RoleInfo".($x + 1).":<br />";
            $out_data_Role_array = unpack('x4/x' . ($x * 220) . '/LiRoleID/a64szLoginName/a128szSignature/LiGender/LiVipID/LiVipExpireTime/LiVipOpeningTime/LiHappyBeanMoneyL32/LiHappyBeanMoneyH32/', $out_data);
            $out_data_Role_array['iHappyBeanMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iHappyBeanMoneyH32'], $out_data_Role_array['iHappyBeanMoneyL32']);
            $date1 = date('Y-m-d H:i:s', $out_data_Role_array['iVipExpireTime'] + 8 * 3600);
            //echo "会员到期：" . $date1 . "<br />";
            //print_r($out_data_Role_array);
            //echo "<br />";
            $this->comm->fitStr($out_data_Role_array['szLoginName']);
            $this->comm->fitStr($out_data_Role_array['szSignature']);
            $out_array["RoleInfoList"][$x] = $out_data_Role_array;
        }

        return $out_array;
    }

//获取玩家信息返回
//UINT32 iRoleID;               //角色ID string '67' (length=2)
//char szRealName[64];          //角色名 string 'abc' (length=3)
//UINT32 iGender;               //性别 0男1女 int 1
//UINT32 iVipID;                    //会员等级 int 0
//char szSignature[128];            //个性签名 string '' (length=0)
//UINT32 iVipExpireTime;            //会员到期时间 string '2013-10-16 10:49:55' (length=19)
//UINT32 iVipOpeningTime;       //会员开始时间 string '2013-10-16 10:49:55' (length=19)
//UINT32 iRoomName;                 // 当前登录状态 string '' (length=0)]
//UINT32 iGameLock;                 // 财富锁定状态
//UINT32 iClientType;           // 客户端类型 0:pc 1:android 2:ios 3:wp
    public function ProcessDMGetRoleBaseInfoRes($out_data)
    {
        //echo "ProcessDMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('LiRoleID/a64szRealName/LiGender/LiVipID/a128szSignature/LiVipExpireTime/LiVipOpeningTime/LiRoomID/LiGameLock/LiClientType/LiLoginBindWeChat/LiBankBindWeChat/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $this->comm->fitStr($out_data_array['szRealName']);
        $this->comm->fitStr($out_data_array['szSignature']);
        $out_array = $out_data_array;

        return $out_array;
    }

    /**
     * 查询玩家房间数据返回
     * UINT32        iCurPage;                //当前页码
     * UINT32        iTotalPage;                //总页数
     * UINT32        iGameCount;                //游戏数量
     * SeRoleGameInfoToOM    kRoleGameInfoToOM[1];    //游戏信息
     * UINT32 iKindID;                //游戏类型 int 1000
     * UINT32 iRoleID;                //角色ID int 67
     * UINT32 iRoomType;                //房间类型 int 1
     * UINT32 iWinCount;                //胜利次数 int 3
     * UINT32 iLostCount;                //失败次数 int 5
     * UINT32 iDrawCount;                // 平局int 0
     * UINT32 iFleeCount;                //逃跑次数 int 0
     * INT64 iTotalMoney;            // 汇总金币string '-4430000' (length=8)
     * INT64 iTotalScore;            // 汇总积分int 0
     * INT64 iScore;                    // 积分int 0
     * INT64 iMoney;                    // 金币string '8650000' (length=7)
     * UINT32 iLastSignTime          //上次签到时间
     * UINT32 iContinuousSign          //连签次数
     * UINT32 iPlayTimeLastDay          //昨天游戏时间
     * UINT32 iPlayTimeDay          //今天游戏时间
     * UINT32 iPlayCountLastDay          //昨天游戏次数
     * UINT32 iPlayCountDay          //今天游戏次数
     */
    public function ProcessDMQueryRoleGameInfoRes($out_data)
    {
        //echo "ProcessDMQueryRoleGameInfoRes: <br />";
        $out_data_array = unpack('LiCurPage/LiTotalPage/LiGameCount/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;
        for ($x = 0; $x < $out_data_array['iGameCount']; $x++) {
            //echo "RoomInfo".($x + 1).":<br />";
            $out_data_Role_array = unpack('x12/x' . ($x * 84) . '/LiKindID/LiRoleID/LiRoomType/LiWinCount/LiLostCount/LiDrawCount/LiFleeCount/LiTotalMoneyL32/LiTotalMoneyH32/LiTotalScoreL32/LiTotalScoreH32/LiScoreL32/LiScoreH32/LiMoneyL32/LiMoneyH32/LiLastSignTime/LiContinuousSign/LiPlayTimeLastDay/LiPlayTimeDay/LiPlayCountLastDay/LiPlayCountDay', $out_data);

            $out_data_Role_array['iTotalMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalMoneyH32'], $out_data_Role_array['iTotalMoneyL32']);
            $out_data_Role_array['iTotalScore'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalScoreH32'], $out_data_Role_array['iTotalScoreL32']);
            $out_data_Role_array['iScore'] = $this->comm->MakeINT64Value($out_data_Role_array['iScoreH32'], $out_data_Role_array['iScoreL32']);
            $out_data_Role_array['iMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iMoneyH32'], $out_data_Role_array['iMoneyL32']);
            //print_r($out_data_Role_array);
            //echo "<br />";

            $out_array["RoomInfoList"][$x] = $out_data_Role_array;
        }

        return $out_array;
    }

    /**
     * 查询玩家银行数据返回
     * INT64 iGameWealth;                //游戏财富['TotalMoney'],
     * INT64 iMoney;                        //银行金币 string '1422' (length=4)
     * UINT32 iFreeze;                    //状态是否冻结 int 1
     * UINT32 iAddTime;                    //开户时间 string '2015-08-05 20:45:15' (length=19)
     * UINT32 iFirstRechargeTime;            //首次充值 string '2014-02-19 10:30:15' (length=19)
     * UINT32 iTotalTimes;                //登录次数 int 1
     * UINT32 iSuperPlayerLevel;            //超级会员等级
     * UINT32 iChargeCount;                //充值次数
     * INT64 iTotalChargeMoney;            //充值总数
     * INT64 iTotalLockMoney;            //冻结总数
     * UINT32 iBankDealBackCanGetCount;  //能量瓶数量
     * INT64 iBankDealBackMoney;         //能量瓶余额
     * INT64 iBankTotalGetBackMoney;     //领取过的能量瓶总数        */
    public function ProcessDMQueryRoleBankInfoRes($out_data)
    {
        //echo "ProcessDMQueryRoleBankInfoRes: <br />";
        $out_data_array = unpack('LiGameWealthL32/LiGameWealthH32/LiMoneyL32/LiMoneyH32/LiFreeze/LiAddTime/LiFirstRechargeTime/LiTotalTimes/LiSuperPlayerLevel/LiChargeCount/LiTotalChargeMoneyL32/LiTotalChargeMoneyH32/LiTotalLockMoneyL32/LiTotalLockMoneyH32/LiBankDealBackCanGetCount/LiBankDealBackMoneyL32/LiBankDealBackMoneyH32/LiBankTotalGetBackMoneyL32/LiBankTotalGetBackMoneyH32/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";
        $out_data_array['iGameWealth'] = $this->comm->MakeINT64Value($out_data_array['iGameWealthH32'], $out_data_array['iGameWealthL32']);
        $out_data_array['iMoney'] = $this->comm->MakeINT64Value($out_data_array['iMoneyH32'], $out_data_array['iMoneyL32']);
        $out_data_array['iTotalChargeMoney'] = $this->comm->MakeINT64Value($out_data_array['iTotalChargeMoneyH32'], $out_data_array['iTotalChargeMoneyL32']);
        $out_data_array['iTotalLockMoney'] = $this->comm->MakeINT64Value($out_data_array['iTotalLockMoneyH32'], $out_data_array['iTotalLockMoneyL32']);
        $out_data_array['iBankDealBackMoney'] = $this->comm->MakeINT64Value($out_data_array['iBankDealBackMoneyH32'], $out_data_array['iBankDealBackMoneyL32']);
        $out_data_array['iBankTotalGetBackMoney'] = $this->comm->MakeINT64Value($out_data_array['iBankTotalGetBackMoneyH32'], $out_data_array['iBankTotalGetBackMoneyL32']);
        $out_array = $out_data_array;

        return $out_array;
    }

    //查询玩家余额
    public function ProcessDMQueryRolebalance($out_data)
    {
        //echo "ProcessDMQueryRoleBankInfoRes: <br />";
        $out_data_array = unpack('LiRoleID/LiGameWealthL32/LiGameWealthH32/LiFreezonMoneyL32/LiFreezonMoneyH32/LiNeedWagedL32/LiNeedWagedH32/LiCurWagedL32/LiCurWagedH32', $out_data);
        // print_r($out_data_array);
        //echo "<br />";
        $out_data_array['iGameWealth'] = $this->comm->MakeINT64Value($out_data_array['iGameWealthH32'], $out_data_array['iGameWealthL32']);
        $out_data_array['iFreezonMoney'] = $this->comm->MakeINT64Value($out_data_array['iFreezonMoneyH32'], $out_data_array['iFreezonMoneyL32']);
        $out_data_array['iNeedWaged'] = $this->comm->MakeINT64Value($out_data_array['iNeedWagedH32'], $out_data_array['iNeedWagedL32']);
        $out_data_array['iCurWaged'] = $this->comm->MakeINT64Value($out_data_array['iCurWagedH32'], $out_data_array['iCurWagedL32']);
        $out_array = $out_data_array;

        return $out_array;
    }
//玩家操作返回 重置银行密码，返回金币,补发金币，补发积分，补发黄钻，冻结财富，给玩家存款
//UINT32 iResult;                   //操作结果，0成功，1失败
    public function ProcessDMRoleOperateAckRes($out_data)
    {
        //echo "ProcessDMRoleOperateAckRes: <br />";
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

//查询在线玩家返回
//UINT32 iTotalCount;               //总在线人数
    public function ProcessDMQueryOnlinePlayerRes($out_data)
    {
        //echo "ProcessDMQueryOnlinePlayerRes: <br />";
        $out_data_array = unpack('LiTotalCount/LiRoomCount/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";
        $out_array = $out_data_array;

        for ($x = 0; $x < $out_data_array['iRoomCount']; $x++) {
            //echo "RoomOnlineInfo".($x + 1).":<br />";
            $out_data_Room_array = unpack('x8/x' . ($x * 28) . '/LiRoomID/LiOnLineCount/LiRobotCount/LiUpdateTime/LiMobileCount/LiIOSCount/LiAndroidCount/', $out_data);
            //print_r($out_data_Room_array);

            $out_array["RoomOnlineInfoList"][$x] = $out_data_Room_array;
        }
        return $out_array;
    }

///处理返回在线玩家
    public function ProcessDMQueryAllOnlinePlayerRes($out_data)
    {
        //print_r($out_data);
        //print_r($out_data);
        //echo "ProcessDMQueryOnlinePlayerRes: <br />";
        $out_data_array = unpack('LiTotalCount/', $out_data);
        //var_dump(strlen($out_data));
        $out_array = $out_data_array;
        for ($x = 0; $x < $out_data_array['iTotalCount']; $x++) {
            $strformat = '/LiUserId/a33szAccount/LiRoomId/LiKindId/a33szRoomName/LiGameMoneyL32/LiGameMoneyH32/LiBankMoneyL32/LiBankMoneyH32/liTotalDespoitL32/liTotalDespoitH32/LiTotalTransOutL32/LiTotalTransOutH32/LnRatio/LnControlTimeLong/LnControlTimeInterval/LnClientType/LnControlType/LnTigerCtrl';
            $out_data_Role_array = unpack('x4/x' . ($x * 134) . $strformat, $out_data);
            $out_data_Role_array['iGameMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iGameMoneyH32'], $out_data_Role_array['iGameMoneyL32']);
            $out_data_Role_array['iBankMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iBankMoneyH32'], $out_data_Role_array['iBankMoneyL32']);
            $out_data_Role_array['iTotalDespoit'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalDespoitH32'], $out_data_Role_array['iTotalDespoitL32']);
            $out_data_Role_array['iTotalTransOut'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalTransOutH32'], $out_data_Role_array['iTotalTransOutL32']);
            $out_array["onlinelist"][$x] = $out_data_Role_array;
        }
        return $out_array;
    }
    
    public function ProcessDMQueryAllOnlinePlayerRes2($out_data)
    {
        $out_data_array = unpack('LiTotalCount/LiPageCount', $out_data);
        $out_array['iTotalCount'] = $out_data_array['iTotalCount'];
        $out_array['iPageCount'] = $out_data_array['iPageCount'];
        for ($x = 0; $x < $out_array['iPageCount']; $x++) {
            $strformat = '/LiUserId/SiRoomId/SnClientType';
            $out_data_Role_array = unpack('x8/x' . ($x * 8) . $strformat, $out_data);
            $out_array["onlinelist"][$x] = $out_data_Role_array;
        }
        return $out_array;
    }

    public function ProcessDMQueryAllOnline2($out_data)
    {
        //print_r($out_data);
        //print_r($out_data);
        //echo "ProcessDMQueryOnlinePlayerRes: <br />";
        $out_data_array = unpack('LiTotalCount/', $out_data);
        //var_dump(strlen($out_data));
        $out_array = $out_data_array;
        for ($x = 0; $x < $out_data_array['iTotalCount']; $x++) {
            $strformat = '/LiUserId';
            $out_data_Role_array = unpack('x4/x' . ($x * 4) . $strformat, $out_data);
//            $out_data_Role_array['iGameMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iGameMoneyH32'], $out_data_Role_array['iGameMoneyL32']);
//            $out_data_Role_array['iBankMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iBankMoneyH32'], $out_data_Role_array['iBankMoneyL32']);
//            $out_data_Role_array['iTotalDespoit'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalDespoitH32'], $out_data_Role_array['iTotalDespoitL32']);
//            $out_data_Role_array['iTotalTransOut'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalTransOutH32'], $out_data_Role_array['iTotalTransOutL32']);
            $out_array["onlinelist"][$x] = $out_data_Role_array;
        }
        return $out_array;
    }

    //在总线人数
    public function getRoomUserOnlineInfo($out_data)
    {
        $out_data_array = unpack('LTotalCount/LRoomCount', $out_data);
        for ($x = 0; $x < $out_data_array['RoomCount']; $x++) {
            $out_data_Room_array = unpack('x8/x' . ($x * 32) . '/LRoomID/LOnLineCount/LRobotCount/LUpdateTime/LMobileCount/LIOSCount/LAndroidCount/LBrowserOnlineCount/', $out_data);
            $out_array["RoomOnlineInfoList"][$x] = $out_data_Room_array;
        }
        $roomct = 0;
        $Mobilect = 0;
        $IOSct = 0;
        $Act = 0;
        $bsct = 0;
        $robot = 0;
        if(!empty($out_array['RoomOnlineInfoList'])) {
            foreach ($out_array['RoomOnlineInfoList'] as &$row) {
                if ($row['RoomID'] >= 25000) continue;
                $roomct += $row['OnLineCount'];
                $Mobilect += $row['MobileCount'];
                $IOSct += $row['IOSCount'];
                $Act += $row['AndroidCount'];
                $bsct += $row['BrowserOnlineCount'];
                $robot += $row['RobotCount'];
            }
        }
        $result = [
            'Hall' => $out_data_array['TotalCount'],
            'RoomCount' => $roomct - $robot,
            'MobileCount' => $Mobilect,
            'IOSCount' => $IOSct,
            'AndroidCount' => $Act,
            'BrowserCount' => $bsct,
        ];
        return $result;
    }

    /**
     * 查询房间机器人数据返回
     * UINT32        iCurPage;                //当前页码
     * UINT32        iTotalPage;                //总页数
     * UINT32        iRoomCount;                //房间数量
     * SeRoomOnlineCountToOM    kRoomOnlineCountToOM[1];    //机器人信息
     * UINT32                                iRoomID;                        //房间号码
     * UINT32                                iOnLineCount;                    //在线人数
     * UINT32                                iRobotCount;                    //机器人在线人数。
     * INT64                                    iRobotWinMoney;                    //机器人赢的钱
     * UINT32                                iUpdateTime;*/
    public function ProcessDMQueryRoomRobotInfoRes($out_data)
    {
        //echo "ProcessDMQueryRoomRobotInfoRes: <br />";
        $out_data_array = unpack('LiCurPage/LiTotalPage/LiRoomCount/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        for ($x = 0; $x < $out_data_array['iRoomCount']; $x++) {
            //echo "RoomRobotInfo".($x + 1).":<br />";
            $out_data_Role_array = unpack('x12/x' . ($x * 24) . '/LiRoomID/LiOnLineCount/LiRobotCount/liRobotWinMoneyL32/liRobotWinMoneyH32/LiUpdateTime/', $out_data);

            $out_data_Role_array['iRobotWinMoney'] = $this->comm->MakeINT64Value($out_data_Role_array['iRobotWinMoneyH32'], $out_data_Role_array['iRobotWinMoneyL32']);

            //print_r($out_data_Role_array);
            //echo "<br />";

            $out_array["RoomRobotInfoList"][$x] = $out_data_Role_array;
        }

        return $out_array;
    }

//查询玩家总财富数据返回
//INT64 iRoleBankTotalMoney;            //玩家银行总财富
//INT64 iRoleGameTotalMoney;            //玩家游戏总财富
    public function ProcessDMQueryRoleTotalMoneyRes($out_data)
    {
        //echo "ProcessDMQueryRoleTotalMoneyRes: <br />";
        $out_data_array = unpack('LiRoleBankTotalMoneyL32/LiRoleBankTotalMoneyH32/LiRoleGameTotalMoneyL32/LiRoleGameTotalMoneyH32/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";
        $out_data_array['iRoleBankTotalMoney'] = $this->comm->MakeINT64Value($out_data_array['iRoleBankTotalMoneyH32'], $out_data_array['iRoleBankTotalMoneyL32']);
        $out_data_array['iRoleGameTotalMoney'] = $this->comm->MakeINT64Value($out_data_array['iRoleGameTotalMoneyH32'], $out_data_array['iRoleGameTotalMoneyL32']);
        $out_array = $out_data_array;

        return $out_array;
    }

    /**
     * 系统银行操作返回
     * UINT32 iResult;                    //操作结果，0成功，1失败
     * INT64 iBalance;                        //当前余额
     * INT64 iLastBalance;                    //上次余额        */
    public function ProcessDMSysBankOperateAckRes($out_data)
    {
        //echo "ProcessDMQueryRoleTotalMoneyRes: <br />";
        $out_data_array = unpack('LiResult/LiBalanceL32/LiBalanceH32/LiLastBalanceL32/LiLastBalanceH32/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_data_array['iBalance'] = $this->comm->MakeINT64Value($out_data_array['iBalanceH32'], $out_data_array['iBalanceL32']);
        $out_data_array['iLastBalance'] = $this->comm->MakeINT64Value($out_data_array['iLastBalanceH32'], $out_data_array['iLastBalanceL32']);

        $out_array = $out_data_array;

        return $out_array;
    }

    /**
     * 系统银行转账返回
     * UINT32 iResult;                    //操作结果，0成功，1失败
     * INT64 iBalance;                        //当前余额
     * INT64 iLastBalance;                    //上次余额
     * INT64 iToBalance;                        //目标银行当前余额
     * INT64 iToLastBalance;                    //目标银行上次余额   */
    public function ProcessDMSysBankDealAckRes($out_data)
    {
        //echo "ProcessDMQueryRoleTotalMoneyRes: <br />";
        $out_data_array = unpack('LiResult/LiBalanceL32/LiBalanceH32/LiLastBalanceL32/LiLastBalanceH32/LiToBalanceL32/LiToBalanceH32/LiToLastBalanceL32/LiToLastBalanceH32/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_data_array['iBalance'] = $this->comm->MakeINT64Value($out_data_array['iBalanceH32'], $out_data_array['iBalanceL32']);
        $out_data_array['iLastBalance'] = $this->comm->MakeINT64Value($out_data_array['iLastBalanceH32'], $out_data_array['iLastBalanceL32']);
        $out_data_array['iToBalance'] = $this->comm->MakeINT64Value($out_data_array['iToBalanceH32'], $out_data_array['iToBalanceL32']);
        $out_data_array['iToLastBalance'] = $this->comm->MakeINT64Value($out_data_array['iToLastBalanceH32'], $out_data_array['iToLastBalanceL32']);

        $out_array = $out_data_array;

        return $out_array;
    }

    /**
     * 角色权限返回
     * UINT32        iResult;                //操作结果，0成功，1失败
     * UINT32        iRoleID;                //角色ID
     * UINT32        iUserRight;                //用户权限
     * UINT32        iMasterRight;            //管理权限     */
    public function ProcessDMRoleRightAckRes($out_data)
    {
        //echo "ProcessDMRoleRightAckRes: <br />";
        $out_data_array = unpack('LiResult/LiRoleID/LiUserRight/LiMasterRight/LiSystemRight/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

    /**
     * 查询房间在线玩家列表
     * UINT32            iCurPage;                //当前页码
     * UINT32            iTotalPage;                //总页数
     * UINT32            iRoleCount;                //玩家数量
     * SeRoomRoleInfo    kRoomRoleInfos[1];        //用户信息
     * UINT32            iUserID;                //玩家id               4
     * char            szUsername[17];            //玩家昵称              17
     * char            szIP[17];                //登录ip               17
     * INT64            iCurGold;                //在该游戏中的当前金币     8
     * INT64            iCurScore;                //在该游戏中的当前成绩     8
     * INT64            iTotalGold;                //在该游戏中的总金币      8
     * INT64            iTotalScore;            //在该游戏中的总成绩      8
     * char           szMachineSerial[33]     //机器码 33
     * INT64          iBankGold               //银行金币 8          */
    public function ProcessDMQueryRoomOnlinePlayersRes($out_data)
    {
        //echo "ProcessDMQueryRoleListRes: <br />";
        $out_data_array = unpack('LiCurPage/LiTotalPage/LiRoleCount/', $out_data);
        //echo "<br />";
        $out_array = $out_data_array;
        for ($x = 0; $x < $out_data_array['iRoleCount']; $x++) {
            //echo "RoleInfo".($x + 1).":<br />";
            $out_data_Role_array = unpack('x12/x' . ($x * 164) . '/LiUserID/a17szUsername/a17szIP/LiCurGoldL32/LiCurGoldH32/LiCurScoreL32/LiCurScoreH32/LiTotalGoldL32/LiTotalGoldH32/LiTotalScoreL32/LiTotalScoreH32/a33szMachineSerial/LiBankGoldL32/LiBankGoldH32/a17szRegIP/a12szMobile/a24szIdCard', $out_data);
            $out_data_Role_array['iCurGold'] = $this->comm->MakeINT64Value($out_data_Role_array['iCurGoldH32'], $out_data_Role_array['iCurGoldL32']);
            $out_data_Role_array['iCurScore'] = $this->comm->MakeINT64Value($out_data_Role_array['iCurScoreH32'], $out_data_Role_array['iCurScoreL32']);
            $out_data_Role_array['iTotalGold'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalGoldH32'], $out_data_Role_array['iTotalGoldL32']);
            $out_data_Role_array['iTotalScore'] = $this->comm->MakeINT64Value($out_data_Role_array['iTotalScoreH32'], $out_data_Role_array['iTotalScoreL32']);
            $out_data_Role_array['iBankGold'] = $this->comm->MakeINT64Value($out_data_Role_array['iBankGoldH32'], $out_data_Role_array['iBankGoldL32']);
            $this->comm->fitStr($out_data_Role_array['szMachineSerial']);
            $this->comm->fitStr($out_data_Role_array['szUsername']);
            $this->comm->fitStr($out_data_Role_array['szIP']);
            $this->comm->fitStr($out_data_Role_array['szRegIP']);
            $this->comm->fitStr($out_data_Role_array['szMobile']);
            $this->comm->fitStr($out_data_Role_array['szIdCard']);

            $out_array["RoleInfoList"][$x] = $out_data_Role_array;
        }
        //print_r($out_array);
        return $out_array;
    }

    /**
     * 查询系统银行数据返回
     * UINT32        iBankCount;                //银行数量
     * SeSystemBankInfo    kSystemBankInfos[1];    //银行信息
     * UINT32                            iBankID;                        //银行id
     * INT64                                iBalance;                        //当前余额
     * INT64                                iLastBalance;                    //上次余额       */
    public function ProcessDMQuerySystemBankDataRes($out_data)
    {
        //echo "ProcessDMQuerySystemBankDataRes: <br />";
        $out_data_array = unpack('LiBankCount/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        for ($x = 0; $x < $out_data_array['iBankCount']; $x++) {
            //echo "SystemBankInfo".($x + 1).":<br />";
            $out_data_Bank_array = unpack('x4/x' . ($x * 20) . '/LiBankID/LiBalanceL32/LiBalanceH32/LiLastBalanceL32/LiLastBalanceH32/', $out_data);
            $out_data_Bank_array['iBalance'] = $this->comm->MakeINT64Value($out_data_Bank_array['iBalanceH32'], $out_data_Bank_array['iBalanceL32']);
            $out_data_Bank_array['iLastBalance'] = $this->comm->MakeINT64Value($out_data_Bank_array['iLastBalanceH32'], $out_data_Bank_array['iLastBalanceL32']);

            //print_r($out_data_Bank_array);
            //echo "<br />";

            $out_array["SystemBankInfoList"][$x] = $out_data_Bank_array;
        }

        return $out_array;
    }



// UINT32       iCount;
// UINT32       iRoleIdList[1];
    public function ProcessDMQuerySuperUserListRes($out_data)
    {
        $out_data_array = unpack('LiCount/', $out_data);

        for ($x = 0; $x < $out_data_array['iCount']; $x++) {
            $out_data_Bank_array = unpack('x4/x' . ($x * 4) . '/LiRoleID/', $out_data);
            $iRoleID = $out_data_Bank_array['iRoleID'];
            $out_array[$iRoleID] = 1;
        }
        return $out_array;
    }


//设置房间概率返回
    public function ProcessDMSetRoomRate($out_data)
    {
        //$out_data_array =unpack('x4/x' . (1 * 4) . '/LiResult/', $out_data);
        print_r($out_data);
        //echo "<br />";

        // $out_array = $out_data_array;

        return $out_data;
    }

    //设置黑名单返回
    public function returnAddBlack($out_data)
    {
        $out_data_array = unpack('LiResult/', $out_data);
        return $out_data_array;
    }


    public function ProcessDMQueryRoomRate($out_data)
    {


        $out_data_array = unpack('LiCount/', $out_data);


        $out_array = [];
        for ($x = 0; $x < $out_data_array['iCount']; $x++) {

            $out_data_Room_array = unpack('x4/x' . ($x * 528) . '/LnServerID/LnCtrlRatio/LnInitStorage/LnCurrentStorage/a512szStorageRatio', $out_data);

            //$nServerID = $out_data_Room_array['nServerID'];
            $this->comm->fitStr($out_data_Room_array['szStorageRatio']);
            $out_array[$x] = $out_data_Room_array;
            //$out_array[nServerID] = 1;
        }
        return $out_array;
    }

    public function ProcessDMQueryRoomNum($out_data)
    {


        $out_data_array = unpack('LiCount/', $out_data);


        $out_array = [];
        for ($x = 0; $x < $out_data_array['iCount']; $x++) {

//            $out_data_Room_array = unpack('x4/x' . ($x * 528) . '/LnServerID/LnCtrlRatio/LnInitStorage/LnCurrentStorage/a512szStorageRatio', $out_data);
            $out_data_Room_array = unpack('x4/x' . ($x * 300) . '/LnRoomId/a256szStorageRatio', $out_data);


            //$nServerID = $out_data_Room_array['nServerID'];
            $this->comm->fitStr($out_data_Room_array['szStorageRatio']);
            $out_array[$x] = $out_data_Room_array;
            //$out_array[nServerID] = 1;
        }
        return $out_array;
    }

    //设置银行卡返回
    public function returnAddBank($out_data)
    {
        $out_data_array = unpack('LiResult/', $out_data);
        return $out_data_array;
    }


    //设置角色状态返回
    public function returnRolestatusUnlock($out_data)
    {
        $out_data_array = unpack('LiResult/', $out_data);
//        return $out_data_array;
        $this->comm->fitStr($out_data_array['szLoginName']);
        $this->comm->fitStr($out_data_array['szMobilePhone']);
        $this->comm->fitStr($out_data_array['IdCard']);
        $this->comm->fitStr($out_data_array['szQQ']);
        $this->comm->fitStr($out_data_array['szMachineSerial']);
        $this->comm->fitStr($out_data_array['szLastLoginIP']);
        $this->comm->fitStr($out_data_array['szRegIP']);
        $this->comm->fitStr($out_data_array['szPlayerName']);
        $this->comm->fitStr($out_data_array['szWeChat']);
//        $out_array = $out_data_array;
//        return $out_array;
        $asRoleBaseInfo = $out_data_array;


        $keyMap = array("szLoginName" => "LoginName"
        );
        $asRoleBaseInfo = $this->arrReplaceKey($asRoleBaseInfo, $keyMap);
        if (isset($asRoleBaseInfo['LoginName']))
            return $asRoleBaseInfo['LoginName'];
        return null;


    }

    public function arrReplaceKey($arr, $keyMap)
    {
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

    //玩家操作返回
//主机解绑，解除锁定，解除处罚，锁定玩家，惩罚玩家，修改玩家帐号信息，设置IP段锁定控制
//UINT32 iResult;                   //操作结果，0成功，1失败
    public function ProcessAMRoleOperateAckRes($out_data)
    {
        //echo "ProcessAMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

    //设置角色状态返回
    public function returnRolestatus($out_data)
    {
        //echo "ProcessAMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;


    }

    //设置角色状态返回
    public function returnRolestatus2($out_data)
    {
        $out_data_array = unpack('LiResult/', $out_data);
//        return $out_data_array;
        $this->comm->fitStr($out_data_array['szLoginName']);
        $this->comm->fitStr($out_data_array['szMobilePhone']);
        $this->comm->fitStr($out_data_array['IdCard']);
        $this->comm->fitStr($out_data_array['szQQ']);
        $this->comm->fitStr($out_data_array['szMachineSerial']);
        $this->comm->fitStr($out_data_array['szLastLoginIP']);
        $this->comm->fitStr($out_data_array['szRegIP']);
        $this->comm->fitStr($out_data_array['szPlayerName']);
        $this->comm->fitStr($out_data_array['szWeChat']);
//        $out_array = $out_data_array;
//        return $out_array;
        $asRoleBaseInfo = $out_data_array;


        $keyMap = array(
            "iLocked" => "Locked", "iLoginID" => "LoginID",
        );
        $asRoleBaseInfo = $this->arrReplaceKey($asRoleBaseInfo, $keyMap);
        if (isset($asRoleBaseInfo["Locked"])) {
            return ["Locked" => $asRoleBaseInfo['Locked']];
        } else {
            return null;
        }
    }


    public function ProcessQueryRunningCtrlData($out_data)
    {

        $out_data_array = unpack('LiCount/', $out_data);

        $out_array = [];
        for ($x = 0; $x < $out_data_array['iCount']; $x++) {
            //$out_data_Ctrl_array                       = unpack('x4/x' . ($x * 88) . '/LnRoomId/LnProfitPercent/LlTotalRunningL32/LlTotalRunningH32/llTotalProfitL32/llTotalProfitH32/LlTotalTaxL32/LlTotalTaxH32/llHistorySumProfileL32/llHistorySumProfileH32/LlHistorySumRunningL32/LlHistorySumRunningH32/LlHistorySumTaxL32/LlHistorySumTaxH32/LlMinStorageL32/LlMinStorageH32/LlMaxStorageL32/LlMaxStorageH32/LlTotalBlackTaxL32/LlTotalBlackTaxH32/LnCtrlValue/LnAdjustValue', $out_data);
            $out_data_Ctrl_array = unpack('x4/x' . ($x * 96) . '/lnRoomId/lnProfitPercent/llTotalRunningL32/llTotalRunningH32/llTotalProfitL32/llTotalProfitH32/llTotalTaxL32/llTotalTaxH32/llHistorySumProfileL32/llHistorySumProfileH32/llHistorySumRunningL32/llHistorySumRunningH32/llHistorySumTaxL32/llHistorySumTaxH32/llMinStorageL32/llMinStorageH32/llMaxStorageL32/llMaxStorageH32/llTotalBlackTaxL32/llTotalBlackTaxH32/lnCtrlValue/lnOldRoomCtrlValue/lnAdjustValue', $out_data);

            $out_data_Ctrl_array['lTotalRunning'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lTotalRunningH32'], $out_data_Ctrl_array['lTotalRunningL32']);
            $out_data_Ctrl_array['lTotalProfit'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lTotalProfitH32'], $out_data_Ctrl_array['lTotalProfitL32']);
            $out_data_Ctrl_array['lTotalTax'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lTotalTaxH32'], $out_data_Ctrl_array['lTotalTaxL32']);
            $out_data_Ctrl_array['lHistorySumProfile'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lHistorySumProfileH32'], $out_data_Ctrl_array['lHistorySumProfileL32']);
            $out_data_Ctrl_array['lHistorySumRunning'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lHistorySumRunningH32'], $out_data_Ctrl_array['lHistorySumRunningL32']);
            $out_data_Ctrl_array['lHistorySumTax'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lHistorySumTaxH32'], $out_data_Ctrl_array['lHistorySumTaxL32']);
            $out_data_Ctrl_array['lMinStorage'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lMinStorageH32'], $out_data_Ctrl_array['lMinStorageL32']);
            $out_data_Ctrl_array['lMaxStorage'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lMaxStorageH32'], $out_data_Ctrl_array['lMaxStorageL32']);
            $out_data_Ctrl_array['lTotalBlackTax'] = $this->comm->MakeINT64Value($out_data_Ctrl_array['lTotalBlackTaxH32'], $out_data_Ctrl_array['lTotalBlackTaxL32']);
            // $out_data_Ctrl_array = unpack('x/x' . ($x * 24) . '/LnRoomId/LnProfitPercent/LlTotalRunning/LlTotalProfit/LnCtrlValue', $out_data);


            //$nServerID = $out_data_Room_array['nServerID'];
//            var_dump($out_data_Room_array);
//            die;
            $out_array[$x] = $out_data_Ctrl_array;
            //$out_array[nServerID] = 1;
        }
        return $out_array;
    }


    //设置角色状态返回
    public function returnSetProfit($out_data)
    {
        //echo "ProcessAMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

    //设置角色状态返回
    public function returnForceQuit($out_data)
    {
        //echo "ProcessAMGetRoleBaseInfoRes: <br />";
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

    //通用
    public function returnComm($out_data)
    {
        $out_data_array = unpack('LiResult/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";

        $out_array = $out_data_array;

        return $out_array;
    }

    /*
 * query user balance
 */
    public function ProcessDMQueryUserBalance($out_data)
    {
        //echo "ProcessDMQueryRoleTotalMoneyRes: <br />";
        $out_data_array = unpack('LiResult/LiRoleID/LiGameMoneyL32/LiGameMoneyH32/', $out_data);
        //print_r($out_data_array);
        //echo "<br />";
        $out_data_array['iGameMoney'] = $this->comm->MakeINT64Value($out_data_array['iGameMoneyH32'], $out_data_array['iGameMoneyL32']);
        $out_array = $out_data_array;
        return $out_array;
    }

    //获取打码量
    public function QueryUserWaged($out_data){
        $out_data_array = unpack('LiResult/LiRoleID/llChargeMoneyL32/llChargeMoneyH32/llNeedWagedL32/llNeedWagedH32/llCurWagedL32/llCurWagedH32/', $out_data);
        
        $out_data_array['lChargeMoney'] = $this->comm->MakeINT64Value($out_data_array['lChargeMoneyH32'], $out_data_array['lChargeMoneyL32']);
        $out_data_array['lNeedWaged'] = $this->comm->MakeINT64Value($out_data_array['lNeedWagedH32'], $out_data_array['lNeedWagedL32']);
        $out_data_array['lCurWaged'] = $this->comm->MakeINT64Value($out_data_array['lCurWagedH32'], $out_data_array['lCurWagedL32']);
        return $out_data_array;
    }
}


