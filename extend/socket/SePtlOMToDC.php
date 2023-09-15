<?php
// OM TO DC
//define('CMD_MD_QUERY_ROLE_LIST', 1);								//查询玩家列表
//define('CMD_MD_GET_ROLE_BASE_INFO', 2);								//获取玩家信息
//define('CMD_MD_QUERY_ROLE_GAME_INFO', 3);							//查询玩家房间数据
//define('CMD_MD_QUERY_ROLE_BANK_INFO', 4);							//查询玩家银行数据
//define('CMD_MD_RESET_ROLE_BANK_PWD', 5);								//重置银行密码
//define('CMD_MD_ADD_ROLE_MONERY', 6);									//返回金币,补发金币
//define('CMD_MD_ADD_ROLE_SCORE', 7);									//补发积分
//define('CMD_MD_BUY_ROLE_VIP', 8);									//补发黄钻
//define('CMD_MD_LOCK_ROLE_MONERY', 9);								//冻结财富
//define('CMD_MD_SAVING_ROLE_MONERY', 10);								//给玩家存款
//define('CMD_MD_QUERY_ONLINE_PLAYER', 11);								//
//define('CMD_MD_SET_SUPER_PLAYER', 12);								//
//define('CMD_MD_RELOAD_GAME_DATA', 13);								//
//define('CMD_MD_ACTIVE_ROOM_ROBOT', 14);								//
//define('CMD_MD_QUERY_ROOM_ROBOT_INFO', 15);							//查询房间机器人数据
//define('CMD_MD_CLEAR_CUR_ROOM_INFO', 16);								//清除当前房间信息
//define('CMD_MD_QUERY_ROLE_TOTAL_MONEY', 17);							//查询玩家总财富数据
//define('CMD_MD_ADD_SYS_BANK_MONEY', 18);								//增加系统银行余额
//define('CMD_MD_SYS_BANK_DEAL', 19);									//系统银行转账
//define('CMD_MD_QUERY_ROLE_RIGHT', 20);								//查询玩家权限
//define('CMD_MD_SET_ROLE_RIGHT', 21);									//设置玩家权限
//define('CMD_MD_QUERY_ROLE_ID', 22);									//查询玩家ID
//define('CMD_MD_SET_LUCKY_EGG_TAX', 23);								//设置彩蛋抽税
//define('CMD_MD_QUERY_ROOM_ONLINE_PLAYERS', 24);						//查询房间在线玩家
//define('CMD_MD_QUERY_SYSTEM_BANK_DATA', 25);								//查询系统银行数据
//define('CMD_MD_SET_BANK_WECHAT_CHECK', 26);								//设置银行微信验证
//define('CMD_MD_UNLOCK_USER_BANK', 27);								//解锁玩家银行
//define('CMD_MD_LOCK_ROLE_VIP', 28);									//锁定黄钻
//define('CMD_MD_CLEAR_ROLE_DATA', 29);									//清理玩家数据
//define('CMD_MD_QUERY_SUPER_USER_LIST', 30);
//define('CMD_MD_QUERY_ALL_ONLINE_PLAYER', 32);
//
//define('CMD_WD_SET_USER_CTRL_DATA', 33);
//define('CMD_MD_SET_ROOM_CTRL_DATA', 34);
//define('CMD_MD_QUERY_ROOM_CTRL_DATA', 35);


//查询玩家列表
//UINT32		iRoleCount;				//用户数量
//UINT32		aiRoleID[1];			//用户ID数组
function SendMDQueryRoleList(&$socket, $iRoleCount, $aiRoleID) {
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleCount);
	
	for ($x=0; $x<$iRoleCount; $x++)
	{
		$in_stream->WeireULong($aiRoleID[$x]);
	}

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_LIST, $in_stream->len, 0, REQ_OM, REQ_DC); 
	//$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	//$in = $in_head . $in_stream->data;
    $in = $in_stream->data;
	//socket_write($socket, $in, $in_len);
    $socket->request($in_head,$in);
}

//获取玩家信息
//UINT32		iRoleID;				//角色ID
function SendMDGetRoleBaseInfo(&$socket, $iRoleID)
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_GET_ROLE_BASE_INFO, $in_stream->len, 0, REQ_OM, REQ_DC); 
	$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	//$in = $in_head . $in_stream->data;
    $in = $in_stream->data;
	$socket->request($in_head,$in);
}

//查询玩家房间数据
//UINT32		iRoleID;				//角色ID
//UINT32		iCurPage;				//当前页码
//UINT32		iPageSize;				//每页数量
function SendMDQueryRoleGameInfo($socket, $iRoleID, $iCurPage, $iPageSize) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireULong($iCurPage);
	$in_stream->WeireULong($iPageSize);
	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_GAME_INFO, $in_stream->len, 0, REQ_OM, REQ_DC); 
	//$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	//$in = $in_head . $in_stream->data;
	$in = $in_stream->data;
	//socket_write($socket, $in, $in_len);
    $socket->request($in_head,$in);
}

//查询玩家银行数据
//UINT32		iRoleID;				//角色ID
function SendMDQueryRoleBankInfo($socket, $iRoleID) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_BANK_INFO, $in_stream->len, 0, REQ_OM, REQ_DC); 
	//$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	$in = $in_stream->data;
	//socket_write($socket, $in, $in_len);
    $socket->request($in_head,$in);
}

//重置银行密码
//UINT32		iRoleID;				//角色ID
//char		szNewPwd[33];			//新的银行密码
function SendMDResetRoleBankPwd($socket, $iRoleID, $szNewPwd) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireString($szNewPwd, 33);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_RESET_ROLE_BANK_PWD, $in_stream->len, 0, REQ_OM, REQ_DC); 
	//$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	//$in = $in_head . $in_stream->data;
	$in = $in_stream->data;
	//socket_write($socket, $in, $in_len);
    $socket->request($in_head,$in);
}

//返回金币,补发金币
//UINT32		iRoleID;				//角色ID
//UINT32		iMonery;				//金币数量
function SendMDAddRoleMonery($socket, $iRoleID, $iMonery,$ntype)
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireINT64($iMonery);
    $in_stream->WeireLong($ntype);
	//echo $in_stream->len;
	//echo $in_stream->data;
        
	$in_head =  MakeSendHead(CMD_MD_ADD_ROLE_MONERY, $in_stream->len, 0, REQ_OM, REQ_DC); 
//	$in_head_len = COMM_HEAD_LEN;
//
//	$in_len = $in_head_len + $in_stream->len;
//	$in = $in_head . $in_stream->data;
//	socket_write($socket, $in, $in_len);
    $in = $in_stream->data;
    $socket->request($in_head,$in);
}

//补发积分
//UINT32		iRoleID;				//角色ID
//UINT32		iKindID; 				//游戏类型 int 1000
//UINT32		iStatus;				//0冻结 1 返回
//UINT32		iScore;					//积分数量
function SendMDAddRoleScore($socket, $iRoleID, $iKindID, $iStatus, $iScore) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireULong($iKindID);
	$in_stream->WeireULong($iStatus);
	$in_stream->WeireULong($iScore);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_ADD_ROLE_SCORE, $in_stream->len, 0, REQ_OM, REQ_DC); 
//	$in_head_len = COMM_HEAD_LEN;
//
//	$in_len = $in_head_len + $in_stream->len;
//	$in = $in_head . $in_stream->data;
//	socket_write($socket, $in, $in_len);
    $in = $in_stream->data;
    $socket->request($in_head,$in);
}

//补发黄钻
//UINT32		iRoleID;				//角色ID
//UINT32		iDays;					//会员天数
function SendMDBuyRoleVip($socket, $iRoleID, $iDays) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireULong($iDays);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_BUY_ROLE_VIP, $in_stream->len, 0, REQ_OM, REQ_DC); 
//	$in_head_len = COMM_HEAD_LEN;
//
//	$in_len = $in_head_len + $in_stream->len;
//	$in = $in_head . $in_stream->data;
//	socket_write($socket, $in, $in_len);
    $in = $in_stream->data;
    $socket->request($in_head,$in);

}

//冻结财富
//UINT32		iRoleID;				//角色ID
//UINT32		iMonery;				//冻结财富
function SendMDLockRoleMonery($socket, $iRoleID, $iMonery) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireINT64($iMonery);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_LOCK_ROLE_MONERY, $in_stream->len, 0, REQ_OM, REQ_DC); 
//	$in_head_len = COMM_HEAD_LEN;
//
//	$in_len = $in_head_len + $in_stream->len;
//	$in = $in_head . $in_stream->data;
//	socket_write($socket, $in, $in_len);
    $in = $in_stream->data;
    $socket->request($in_head,$in);
}

//给玩家存款
//UINT32		iRoleID;				//角色ID
//UINT32		iGameSize;				//数量
//SeGameMoneryToSaving aGameMoney[1]; //需要存款的游戏数据
	//UINT32		iKindID;				//游戏类型
	//UINT32		iMonery;				//存款数量
function SendMDSavingRoleMonery(&$socket, $iRoleID, $iGameSize, $aGameMoney)
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);
	$in_stream->WeireULong($iGameSize);

//	for ($x=0; $x<$iGameSize; $x++)
//	{
//		$in_stream->WeireULong($aGameMoney[$x]["iKindID"]);
//		$in_stream->WeireULong($aGameMoney[$x]["iMonery"]);
//	}
    //$in_stream->WeireULong($aGameMoney[$x]["iMonery"])
	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_SAVING_ROLE_MONERY, $in_stream->len, 0, REQ_OM, REQ_DC); 
	//$in_head_len = COMM_HEAD_LEN;

	//$in_len = $in_head_len + $in_stream->len;
	//$in = $in_head . $in_stream->data;
	$in = $in_stream->data;
	//socket_write($socket, $in, $in_len);
   // $socket->request($in_head,$in);

}
//在线人数查询接口
function SendMDQueryOnlinePlayer($socket)
{
    $in_stream = new PHPStream();

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_QUERY_ONLINE_PLAYER, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//设置商人
//UINT32		iRoleID;				//角色ID
//UINT32		iSuperLevel;			//超级会员等级，转账反馈比例 万分之
function SendMDSetSuperPlayer(&$socket, $iRoleID, $iSuperLevel)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);
    $in_stream->WeireULong($iSuperLevel);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_SET_SUPER_PLAYER, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//同步配置数据
//UINT32		iLoadType;				//加载类型 0全部
function SendMDReloadGameData($socket, $iLoadType)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iLoadType);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_RELOAD_GAME_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//激活房间机器人
//UINT32		iRoomID;				//房间ID
function SendMDActiveRoomRobot($socket, $iRoomID)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoomID);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_ACTIVE_ROOM_ROBOT, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//查询房间机器人数据
//UINT32		iCurPage;				//当前页码
//UINT32		iPageSize;				//每页数量
function SendMDQueryRoomRobotInfo($socket, $iCurPage, $iPageSize)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iCurPage);
    $in_stream->WeireULong($iPageSize);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_QUERY_ROOM_ROBOT_INFO, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//清除当前房间信息
//UINT32		iRoleID;				//角色ID
function SendMDClearCurRoomInfo($socket, $iRoleID)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_CLEAR_CUR_ROOM_INFO, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}


//查询玩家总财富数据
//UINT32		iRoleID;				//角色ID
function SendMDQueryRoleTotalMoney($socket, $iRoleID) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iRoleID);

	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_TOTAL_MONEY, $in_stream->len, 0, REQ_OM, REQ_DC); 
	/* $in_head_len = COMM_HEAD_LEN;

	$in_len = $in_head_len + $in_stream->len;
	$in = $in_head . $in_stream->data;
	socket_write($socket, $in, $in_len); */
	$socket->request($in_head,$in_stream->data);
}

//系统银行转账
//UINT32		iFromBankAccID;			//转出银行ID
//UINT32		iToBankAccID;			//转入银行ID
//INT64		iDealMoney;				//转账金币数量
function SendMDSysBankDeal($socket, $iBankAccID, $iToBankAccID, $iDealMoney)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iBankAccID);
    $in_stream->WeireULong($iToBankAccID);
    $in_stream->WeireINT64($iDealMoney);
    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_SYS_BANK_DEAL, $in_stream->len, 0, REQ_OM, REQ_DC);
/*     $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data; */
    $socket->request($in_head,$in_stream->data);
}

//设置系统银行余额
//UINT32		iBankAccID;				//银行ID
//INT64		iBankMoney;				//金币数量
function SendMDAddSysBankMoney($socket, $iBankAccID, $iBankMoney) 
{
	$in_stream = new PHPStream();
	$in_stream->WeireULong($iBankAccID);
	$in_stream->WeireINT64($iBankMoney);
	//echo $in_stream->len;
	//echo $in_stream->data;

	$in_head =  MakeSendHead(CMD_MD_ADD_SYS_BANK_MONEY, $in_stream->len, 0, REQ_OM, REQ_DC); 
	/* $in_head_len = COMM_HEAD_LEN;

	$in_len = $in_head_len + $in_stream->len;
	$in = $in_head . $in_stream->data; */
	$socket->request($in_head,$in_stream->data);
}
//查询玩家权限
function SendMDQueryRoleRight($socket, $iRoleID)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_RIGHT, $in_stream->len, 0, REQ_OM, REQ_DC);
    //$in_head_len = COMM_HEAD_LEN;

    //$in_len = $in_head_len + $in_stream->len;
    //$in = $in_head . $in_stream->data;
    $socket->request($in_head,$in_stream->data);
}

//设置玩家权限
//UINT32		iRoleID;				//角色ID
//UINT32		iUserRight;				//用户权限
//UINT32		iMasterRight;			//管理权限
function SendMDSetRoleRight($socket, $iRoleID, $iUserRight, $iMasterRight, $iSystemRight)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);
    $in_stream->WeireULong($iUserRight);
    $in_stream->WeireULong($iMasterRight);
	$in_stream->WeireULong($iSystemRight);
    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_SET_ROLE_RIGHT, $in_stream->len, 0, REQ_OM, REQ_DC);
   /*  $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data; */
    $socket->request($in_head,$in_stream->data);
}
//查询玩家id
//char		szRoleName[32];			//角色昵称
function SendMDQueryRoleID($socket, $szRoleName)
{
    $in_stream = new PHPStream();
    $in_stream->WeireString($szRoleName, 32);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_QUERY_ROLE_ID, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//设置彩蛋抽税
//UINT32		iRoomID;				//房间ID
//UINT32		iLuckyEggTax;				//彩蛋抽税
function SendMDSetLuckyEggTax($socket, $iRoomID, $iLuckyEggTax)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoomID);
    $in_stream->WeireULong($iLuckyEggTax);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_SET_LUCKY_EGG_TAX, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}

//查询房间在线玩家

function SendMDQueryRoomOnlinePlayers($socket, $iRoomID, $iCurpage, $iPagenum)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoomID);
    $in_stream->WeireULong($iCurpage);
    $in_stream->WeireULong($iPagenum);

    $in_head =  MakeSendHead(CMD_MD_QUERY_ROOM_ONLINE_PLAYERS, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}


/*
 *  获取在线用户列表
 */
function SendMDQueryOnlinePlayers($socket)
{
    $in_stream = new PHPStream();

    //$in_stream->WeireULong($iCurpage);
   // $in_stream->WeireULong($iPagenum);

    $in_head =  MakeSendHead(CMD_MD_QUERY_ALL_ONLINE_PLAYER, $in_stream->len, 0, REQ_OM, REQ_DC);
    /*$in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len);*/
    $socket->request($in_head,$in_stream->data);
}


//查询系统银行数据
//UINT32		iCurTime;				//当前时间
function SendMDQuerySystemBankData($socket, $iCurTime)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iCurTime);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_QUERY_SYSTEM_BANK_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
    /* $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len); */
    $socket->request($in_head,$in_stream->data);
}

//设置银行微信验证
//UINT32		iRoleID;				//角色ID
//UINT32		iCheck;					//是否验证 1验证 0不验证
function SendMDSetBankWeChatCheck($socket, $iRoleID, $iCheck)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);
    $in_stream->WeireULong($iCheck);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_SET_BANK_WECHAT_CHECK, $in_stream->len, 0, REQ_OM, REQ_DC);
/*     $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len); */
    $socket->request($in_head,$in_stream->data);
}
//解锁玩家银行
//UINT32		iRoleID;				//角色ID
function SendMDUnLockUserBank($socket, $iRoleID)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_UNLOCK_USER_BANK, $in_stream->len, 0, REQ_OM, REQ_DC);
/*     $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len); */
    $socket->request($in_head,$in_stream->data);
}

//锁定黄钻
//UINT32		iRoleID;				//角色ID
//UINT32		iDays;					//会员天数
function SendMDLockRoleVip($socket, $iRoleID, $iDays)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);
    $in_stream->WeireULong($iDays);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_LOCK_ROLE_VIP, $in_stream->len, 0, REQ_OM, REQ_DC);
/*     $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len); */
    $socket->request($in_head,$in_stream->data);
}
//清理玩家数据
//UINT32		iRoleID;				//角色ID
function SendMDClearRoleData($socket, $iRoleID)
{
    $in_stream = new PHPStream();
    $in_stream->WeireULong($iRoleID);

    //echo $in_stream->len;
    //echo $in_stream->data;

    $in_head =  MakeSendHead(CMD_MD_CLEAR_ROLE_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
/*     $in_head_len = COMM_HEAD_LEN;

    $in_len = $in_head_len + $in_stream->len;
    $in = $in_head . $in_stream->data;
    socket_write($socket, $in, $in_len); */
    $socket->request($in_head,$in_stream->data);
}


function SendMDQureySuperUserList($socket, $condition)
{
    $len = strlen($condition);
    assert($len < 2000);
    $in_stream = new PHPStream();
    $in_stream->WeireString($condition, $len);
    $in_head =  MakeSendHead(CMD_MD_QUERY_SUPER_USER_LIST, $in_stream->len, 0, REQ_OM, REQ_DC);
    $socket->request($in_head,$in_stream->data);
}


//控制程序
//
function SendMDCtrolPerson($socket,$AccountId,$Ratio,$ControlTimeLong,$ControlTimeInterval){

    $in_stream = new PHPStream();
    $in_stream->WeireULong($AccountId);
    $in_stream->WeireULong($Ratio);
    $in_stream->WeireULong($ControlTimeLong);
    $in_stream->WeireULong($ControlTimeInterval);

    $in_head =  MakeSendHead(CMD_WD_SET_USER_CTRL_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
    $socket->request($in_head,$in_stream->data);

}


//发送个人控制
function SendMDCtrolRoom($socket,$RoomId,$Ratio,$InitStorage,$CurrentStorage,$szStorageRatio){
    $in_stream = new PHPStream();
    $in_stream->WeireULong($RoomId);
    $in_stream->WeireULong($Ratio);
    $in_stream->WeireULong($InitStorage);
    $in_stream->WeireULong($CurrentStorage);
    $in_stream->WeireString($szStorageRatio,512);

    $in_head =  MakeSendHead(CMD_MD_SET_ROOM_CTRL_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
    $socket->request($in_head,$in_stream->data);
}


function SendMDQueryRoom($socket,$RoomId){
    $in_stream = new PHPStream();
    $in_stream->WeireULong($RoomId);

    $in_head =  MakeSendHead(CMD_MD_QUERY_ROOM_CTRL_DATA, $in_stream->len, 0, REQ_OM, REQ_DC);
    $socket->request($in_head,$in_stream->data);
}



