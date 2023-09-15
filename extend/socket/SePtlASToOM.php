<?php
namespace socket;
// AS TO OM

//查询玩家列表
//UINT32		iCurPage;				//当前页码
//UINT32		iTotalPage;				//总页数
//UINT32		iRoleCount;				//用户数量
//SeRoleInfoASToOM	akRoleInfoToOM[1];	//用户信息
	//char szLoginCode[32];				//玩家账号
	//UINT32 iLoginID;				//玩家编号
	//UINT32 iMoorMachine;			//是否主机绑定
	//char szMachineSerial[33];		//绑定机器码
	//UINT32 iLockStartTime;			//锁定开始时间
	//UINT32 iLockEndTime;			//锁定结束时间
	//UINT32 iLocked;					//是否锁定
	//UINT32 iLoginCount;				//登录次数
	//char szLastLoginIP[17];			//最后登录IP
	//UINT32 iLastLoginTime;			//最后登录时间
	//char szRegIP[17];				//注册IP
	//UINT32 iAddTime;				//注册时间
function ProcessAMQueryRoleListRes($out_data)
{
	//echo "ProcessAMQueryRoleListRes: <br />";
	$out_data_array = unpack('LiCurPage/LiTotalPage/LiRoleCount/', $out_data);
	//print_r($out_data_array);
	//echo "<br />";

	$out_array = $out_data_array;
	for ($x=0; $x<$out_data_array['iRoleCount']; $x++)
	{
		//echo "RoleInfo".($x + 1).":<br />";
		$out_data_Role_array = unpack('x12/x'.($x*131).'/a32szLoginCode/LiLoginID/LiMoorMachine/a33szMachineSerial/LiLockStartTime/LiLockEndTime/LiLocked/LiLoginCount/a17szLastLoginIP/LiLastLoginTime/a17szRegIP/LiAddTime', $out_data);

        //$out_data_Role_array['iLockStartTime']=date('Y-m-d H:i:s', $out_data_Role_array['iLockStartTime'] + 8 * 3600);
		//echo "锁定开始：" . $date1 . "<br />";
        //$out_data_Role_array['iLockEndTime']=date('Y-m-d H:i:s', $out_data_Role_array['iLockEndTime'] + 8 * 3600);
		//echo "锁定结束：" . $date2 . "<br />";
        //$out_data_Role_array['iLastLoginTime']=date('Y-m-d H:i:s', $out_data_Role_array['iLastLoginTime'] + 8 * 3600);
		//echo "最后登录：" . $date3 . "<br />";
        //$out_data_Role_array['iAddTime']=date('Y-m-d H:i:s', $out_data_Role_array['iAddTime'] + 8 * 3600);
		//echo "注册时间：" . $date4 . "<br />";

		//print_r($out_data_Role_array);
		//echo "<br />";
        fitStr($out_data_Role_array['szLoginCode']);
        fitStr($out_data_Role_array['szMachineSerial']);
        fitStr($out_data_Role_array['szLastLoginIP']);
        fitStr($out_data_Role_array['szRegIP']);

		$out_array["RoleInfoList"][$x] = $out_data_Role_array;
	}

	return $out_array;
}

//获取玩家信息返回
//char szLoginName[64]; 			// 角色名string 'cp8' (length=3)
//UINT32 iLoginID; 				//帐号ID int 609372
//char szMobilePhone[12]; 		//手机号码 string '0' (length=1)
//char IdCard[24]; 				//身份证 string '' (length=0)
//char QQ[12]; 					//QQ号 string '' (length=0)
//UINT32 iMoorMachine; 			//是否主机绑定 int 0
//char szMachineSerial[33];		//绑定机器码 string 'c7be5217d0bce458cb1cb026c7353dec' (length=32)
//UINT32 iLockStartTime; 			//开始时间 string '2015-08-03 11:01:48' (length=19)
//UINT32 iTitleID; 				//是否设置IP段锁定 int 1 
//UINT32 iLockEndTime; 			//结束时间 string '2018-08-02 11:01:48' (length=19)
//UINT32 iLocked; 				//是否锁定 int 1
//UINT32 iLoginCount; 			//登录次数 int 31
//char szLastLoginIP[17]; 		//最后登录IP string '192.168.1.213' (length=13)
//UINT32 iLastLoginTime; 			//最后登录时间 string '2014-06-25 16:45:11' (length=19)
//char szRegIP[17]; 				//注册IP string '192.168.1.6' (length=11)
//UINT32 iAddTime; 				//注册时间 string '2013-10-16' (length=10)
//UINT32 iBlockStartTime;			//封号开始时间
//UINT32 iBlockEndTime;			//封号结束时间
//UINT32 iBlocked;				//是否封号
//char szPlayerName[10];			//真实姓名
//char szWeChat[50];				//微信ID oSBgrwHT-fVpLczITBPo3mAYCgb8
function ProcessAMGetRoleBaseInfoRes($out_data)
{
	//echo "ProcessAMGetRoleBaseInfoRes: <br />";
	$out_data_array = unpack('a64szLoginName/LiLoginID/a16szMobilePhone/a24IdCard/a12szQQ/LiMoorMachine/a33szMachineSerial/LiLockStartTime/LiTitleID/LiLockEndTime/LiLocked/LiLoginCount/a17szLastLoginIP/LiLastLoginTime/a17szRegIP/LiAddTime/LiBlockStartTime/LiBlockEndTime/LiBlocked/a10szPlayerName/a50szWeChat/', $out_data);
    //print_r($out_data_array);
	//print_r($out_data_array);
	//echo "<br />";
    fitStr($out_data_array['szLoginName']);
    fitStr($out_data_array['szMobilePhone']);
    fitStr($out_data_array['IdCard']);
    fitStr($out_data_array['szQQ']);
    fitStr($out_data_array['szMachineSerial']);
    fitStr($out_data_array['szLastLoginIP']);
    fitStr($out_data_array['szRegIP']);
    fitStr($out_data_array['szPlayerName']);
    fitStr($out_data_array['szWeChat']);
	$out_array = $out_data_array;
	
	return $out_array;
}

//玩家操作返回
//主机解绑，解除锁定，解除处罚，锁定玩家，惩罚玩家，修改玩家帐号信息，设置IP段锁定控制
//UINT32 iResult; 					//操作结果，0成功，1失败
function ProcessAMRoleOperateAckRes($out_data)
{
	//echo "ProcessAMGetRoleBaseInfoRes: <br />";
	$out_data_array = unpack('LiResult/', $out_data);
	//print_r($out_data_array);
	//echo "<br />";

	$out_array = $out_data_array;
	
	return $out_array;
}