<?php

namespace socket;
// OM TO DC
//define('CMD_MD_QUERY_ROLE_LIST', 1);								//查询玩家列表
//define('CMD_MD_GET_ROLE_BASE_INFO', 2);								//获取玩家信息
//define('CMD_MD_QUERY_ROLE_GAME_INFO', 3);							//查询玩家房间数据
use app\admin\controller\traits\FileLog;

define('CMD_MD_QUERY_ROLE_BANK_INFO', 4);                            //查询玩家银行数据
define('CMD_MD_RESET_ROLE_BANK_PWD', 5);                                //重置银行密码
define('SBWCT_SYS_REDUCE_MONEY', 21);                                //重置银行密码
define('CMD_MD_ADD_ROLE_MONERY', 6);                                    //返回金币,补发金币
define('CMD_MD_ADD_ROLE_SCORE', 7);                                    //补发积分
define('CMD_MD_BUY_ROLE_VIP', 8);                                    //补发黄钻
define('CMD_MD_LOCK_ROLE_MONERY', 9);                                //冻结财富
define('CMD_MD_SAVING_ROLE_MONERY', 10);                                //给玩家存款
define('CMD_MD_QUERY_ONLINE_PLAYER', 11);                                //
define('CMD_MD_SET_SUPER_PLAYER', 12);                                //
define('CMD_MD_RELOAD_GAME_DATA', 13);                                //
define('CMD_MD_ACTIVE_ROOM_ROBOT', 14);                                //
define('CMD_MD_QUERY_ROOM_ROBOT_INFO', 15);                            //查询房间机器人数据
define('CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA', 43);                            //查询房间机器人数据
define('CMD_MD_CLEAR_CUR_ROOM_INFO', 16);                                //清除当前房间信息
define('CMD_MD_QUERY_ROLE_TOTAL_MONEY', 17);                            //查询玩家总财富数据
define('CMD_MD_ADD_SYS_BANK_MONEY', 18);                                //增加系统银行余额
define('CMD_MD_SYS_BANK_DEAL', 19);                                    //系统银行转账
define('CMD_MD_QUERY_ROLE_RIGHT', 20);                                //查询玩家权限
define('CMD_MD_SET_ROLE_RIGHT', 21);                                    //设置玩家权限
define('CMD_MD_QUERY_ROLE_ID', 22);                                    //查询玩家ID
define('CMD_MD_SET_LUCKY_EGG_TAX', 23);                                //设置彩蛋抽税
define('CMD_MD_QUERY_ROOM_ONLINE_PLAYERS', 24);                        //查询房间在线玩家
define('CMD_MD_QUERY_SYSTEM_BANK_DATA', 25);                                //查询系统银行数据
define('CMD_MD_SET_BANK_WECHAT_CHECK', 26);                                //设置银行微信验证
define('CMD_MD_UNLOCK_USER_BANK', 27);                                //解锁玩家银行
define('CMD_MD_LOCK_ROLE_VIP', 28);                                    //锁定黄钻
define('CMD_MD_CLEAR_ROLE_DATA', 29);                                    //清理玩家数据
define('CMD_MD_QUERY_SUPER_USER_LIST', 30);
//define('CMD_MD_QUERY_ALL_ONLINE_PLAYER', 32);

define('CMD_WD_SET_USER_CTRL_DATA', 33);
define('CMD_MD_SET_ROOM_CTRL_DATA', 34);
define('CMD_MD_SET_ROOM_ROBOT_CTRL_DATA', 42);
define('CMD_MD_QUERY_ROOM_CTRL_DATA', 35);
define('CMD_MD_SET_IP_BLACK_LIST', 36);
define('CMD_MD_SET_PAY_WAY', 37);

//盈利百分比
define('CMD_MD_PROFIT_PERCENT', 38);
define('CMD_MD_QUERY_RUNNING_CTRL_DATA', 39);
define('CMD_MD_FORCE_QUIT_ROL', 41);
define('CMD_MD_SET_FISHROOM_RATIO', 46);
define('CMD_MD_SET_TIGER_CTRL_VALUE', 47);
define('CMD_MD_SYSTEM_MAIL', 50);
define('CMD_WD_USE_COUPON', 119);
define('CMD_WD_GET_COUPON_COUNT', 120);
//define('CMD_MD_USER_CHANNEL_RECHARGE', 51); //三方充值
define('CMD_MD_THIRD_PARTY_TRANSFER_RES', 53);
define('CMD_MD_THIRD_PARTY_WRITE_GAME_MONEY', 54);
define('CMD_MD_GET_ROLE_GAME_MONEY', 55);
define('CMD_MD_UNLOCK_MONEY', 57);
define('CMD_MD_SYSTEM_CTRL_WATER', 58);//    盘控设置

define('CMD_MD_DEL_PAY_WAY', 59);
define('CMD_MD_QUERY_WAGED', 60);
define('CMD_MD_SET_WAGED', 61);//    盘控设置
define('CMD_MD_SET_PROXY_INVITE', 62);//    设置上级邀请码
define('CMD_MD_SET_PROXY_SWITCH', 63);//设置代理开关
define('CMD_MD_CHANGE_PROXY', 64);//设置代理开关
define('CMD_MD_DELETE_ACCOUNT', 67);//删除账号
define('CMD_WD_ACCOUNT_BIND_PHONE', 125);//手动绑定手机号
define('CMD_WD_ACCOUNT_UNBIND_PHONE', 127);//手动解除绑定手机号

define('CMD_MD_THIRD_PARTY_TRANSFER_ADD', 65);//三方请求增加金币
define('CMD_MD_THIRD_PARTY_TRANSFER_SUB', 66);//三方请求减少金币

define('CMD_MD_ADD_WITHDRAW_REMAIN', 70);//增加渠道余额
define('CMD_MD_SET_SPACE_INFO', 72);
define('CMD_MD_SET_WITHDRAW_VIPLIMIT_SWITCH', 73);//设置代理开关
define('CMD_MD_SET_PROXY_WITHDRAW_EXTRA_AMOUNT', 74);//设置代理佣金额外提现额度
define('CMD_MD_THIRD_PARTY_CLEAR_INGAME_LABEL', 75);//三方清除在游戏中的标志

define('CMD_MD_CHANNEL_CHILD_ONLINE_COUNTS', 76);//查询渠道对应的在线人数
define('CMD_MD_USER_STATE', 77);//查询玩家在线状态
define('CMD_MD_USER_WAGED_RATE', 78);//查询打码百分比
define('CMD_MD_GM_ADD_PROXY_COMMISSION', 79);//佣金上下分
define('CMD_MD_GM_UPDATE_USER_TYPE', 10001);//玩家身上的各种开关  (为了防止以后同步代码发生冲突，从10000开始
define('CMD_MD_GM_PDD_ADD_MONEY', 10003);// 转盘加减奖励金
define('CMD_MD_GM_PDD_COMMI_SUC', 10004);// 审核通过
define('CMD_MD_GM_ADD_JOB', 10005);// 审核通过
define('CMD_MD_GM_PDD_REFUND', 10002);//pdd gm  操作 退款



class sendQuery
{

    private $comm;
    private $in_stream;

    public function __construct()
    {
        $this->comm = new Comm();
        $this->in_stream = new PHPStream();
    }


    /**
     * 自定义回调函数
     * @param string $funcName 本类的函数名称
     * @param array $parameter 参数数组
     * @param string $SocketInstance 连接的socket对线 AS ,DC
     * @param null $changeFunc
     * @param null $changeDate
     * @return string
     */
    public function callback($funcName, $parameter, $SocketInstance = 'DC', $changeFunc = null)
    {
        $socket = $this->comm->getSocketInstance($SocketInstance);
        array_unshift($parameter, $socket);//往参数的头部插入 socket
        call_user_func_array([$this, $funcName], $parameter);
        $out_data = $socket->response();
        if (!empty($changeFunc)) {
            $change = new ChangeData();
            $out_data = call_user_func([$change, $changeFunc], $out_data);
        }
        return $out_data;
    }


    function CMD_MD_SET_PAY_WAY($socket, $dwUserId, $szUserName, $szBankCardNo, $szBankName, $szIFSCCode, $szMail, $wPayWayType)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($dwUserId);
        $in_stream->WriteString($szUserName, 64);
        $in_stream->WriteString($szBankCardNo, 64);
        $in_stream->WriteString($szBankName, 33);
        $in_stream->WriteString($szIFSCCode, 33);
        $in_stream->WriteString($szMail, 33);
        $in_stream->WriteLong($wPayWayType);
        $in_head = $this->comm->MakeSendHead(37, $in_stream->len, 0, REQ_OM, REQ_AI);
        $socket->request($in_head, $in_stream->data);
    }


    /**
     * 重置登录密码  回调要指定 AS
     * @param int $iRoleID 本类的函数名称
     * @param string $parameter 密码
     */
    function CMD_MA_RESET_LOGIN_PWD($socket, $iRoleID, $szNewPassword)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($iRoleID);
        $in_stream->WriteString($szNewPassword, 33);
        $in_head = $this->comm->MakeSendHead(10, $in_stream->len, 0, REQ_OM, REQ_AI);
        $socket->request($in_head, $in_stream->data);
    }

    //盘控
    public function SeMDSystemCtrlWater($socket, $llWaterIn, $llWaterOut)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteINT64($llWaterIn);
        $this->in_stream->WriteINT64($llWaterOut);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SYSTEM_CTRL_WATER, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

    /**
     * 第三方充值
     * int    nAccountId;        //只在加载小游戏传值 $iRoleID
     * int    nChannelId;                          $channelID
     * char  szTransactionNo[64];  //三方充值订单号   TransactionNo
     * LONGLONG llRealMoney;      //实际金币
     * LONGLONG llMoney;
     */
    public function CMD_MD_USER_CHANNEL_RECHARGE($socket, $iRoleID, $channelID, $TransactionNo, $RealMoney, $iMonery)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($channelID);
        $this->in_stream->WriteString($TransactionNo, 64);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($iMonery);
        $in_head = $this->comm->MakeSendHead(51, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

    /**
     * 用户退款处理
     * @param $socket
     * @param $iRoleID          int    用户ID;
     * @param $nStatus          int    状  态;
     * @param $TransactionNo    string 订单号
     * @param $RealMoney        int    金额
     * @param $iMonery
     */
    public function CMD_MD_USER_DRAWBACK_MONEY($socket, $iRoleID, $nStatus, $TransactionNo, $RealMoney, $iMonery, $nDrawBackWay)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($nStatus);
        $this->in_stream->WriteString($TransactionNo, 64);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($iMonery);
        $this->in_stream->WriteLong($nDrawBackWay);
        $in_head = $this->comm->MakeSendHead(52, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }


    ///新的提现接口
    public function CMD_MD_USER_DRAWBACK_MONEY_NEW($socket, $iRoleID, $nStatus, $TransactionNo, $RealMoney, $iMonery, $payway, $FreezonMoney, $CurWaged, $NeedWaged)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($nStatus);
        $this->in_stream->WriteString($TransactionNo, 64);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($iMonery);
        $this->in_stream->WriteULong($payway);
        $this->in_stream->WriteINT64($FreezonMoney);
        $this->in_stream->WriteINT64($CurWaged);
        $this->in_stream->WriteINT64($NeedWaged);
        $in_head = $this->comm->MakeSendHead(52, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

    //查询玩家列表
//UINT32		iRoleCount;				//用户数量
//UINT32		aiRoleID[1];			//用户ID数组
    function CMD_MD_QUERY_ROLE_LIST(&$socket, $iRoleCount, $aiRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleCount);
        for ($x = 0; $x < $iRoleCount; $x++) {
            $this->in_stream->WriteULong($aiRoleID[$x]);
        }
        //CMD_MD_QUERY_ROLE_LIST=1
        $in_head = $this->comm->MakeSendHead(1, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        //socket_write($socket, $in, $in_len);
        $socket->request($in_head, $in);
    }

//获取玩家信息
//UINT32		iRoleID;				//角色ID
    function CMD_MD_GET_ROLE_BASE_INFO(&$socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        //CMD_MD_GET_ROLE_BASE_INFO 2
        $in_head = $this->comm->MakeSendHead(2, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        // $in_head_len = COMM_HEAD_LEN;
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

//查询玩家房间数据
//UINT32		iRoleID;				//角色ID
//UINT32		iCurPage;				//当前页码
//UINT32		iPageSize;				//每页数量
    function CMD_MD_QUERY_ROLE_GAME_INFO($socket, $iRoleID, $iCurPage, $iPageSize)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iCurPage);
        $this->in_stream->WriteULong($iPageSize);

        $in_head = $this->comm->MakeSendHead(3, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        //socket_write($socket, $in, $in_len);
        $socket->request($in_head, $in);
    }

//查询玩家银行数据
//UINT32		iRoleID;				//角色ID
    function CMD_MD_QUERY_ROLE_BANK_INFO($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(4, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

//重置银行密码
//UINT32		iRoleID;				//角色ID
//char		szNewPwd[33];			//新的银行密码
    function CMD_MD_RESET_ROLE_BANK_PWD($socket, $iRoleID, $szNewPwd)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteString($szNewPwd, 33);
        $in_head = $this->comm->MakeSendHead(5, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

//返回金币,补发金币
//UINT32		iRoleID;				//角色ID
//UINT32		iMonery;				//金币数量
    function CMD_MD_ADD_ROLE_MONERY($socket, $iRoleID, $iMonery, $ntype, $iStatus, $ipaddr)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteINT64($iMonery);
        $this->in_stream->WriteLong($ntype);
        $this->in_stream->WriteULong($iStatus);
        $this->in_stream->WriteString($ipaddr, 17);
        $in_head = $this->comm->MakeSendHead(6, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

    function CMD_MD_GM_ADD_PROXY_COMMISSION($socket, $iRoleID, $iMonery, $ntype)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($ntype);
        $this->in_stream->WriteINT64($iMonery);
        $in_head = $this->comm->MakeSendHead(79, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }
//补发积分
//UINT32		iRoleID;				//角色ID
//UINT32		iKindID; 				//游戏类型 int 1000
//UINT32		iStatus;				//0冻结 1 返回
//UINT32		iScore;					//积分数量
//$iStatus 0 $iKindID 5000
    function CMD_MD_ADD_ROLE_SCORE($socket, $iRoleID, $iKindID, $iStatus, $iScore)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iKindID);
        $this->in_stream->WriteULong($iStatus);
        $this->in_stream->WriteULong($iScore);
        $in_head = $this->comm->MakeSendHead(7, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

//补发黄钻
//UINT32		iRoleID;				//角色ID
//UINT32		iDays;					//会员天数
    function CMD_MD_BUY_ROLE_VIP($socket, $iRoleID, $iDays)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iDays);
        $in_head = $this->comm->MakeSendHead(8, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);

    }

//冻结财富
//UINT32		iRoleID;				//角色ID
//UINT32		iMonery;				//冻结财富
    function CMD_MD_LOCK_ROLE_MONERY($socket, $iRoleID, $iMonery)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteINT64($iMonery);
        $in_head = $this->comm->MakeSendHead(9, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

//给玩家存款
//UINT32		iRoleID;				//角色ID
//UINT32		iGameSize;				//数量
//SeGameMoneryToSaving aGameMoney[1]; //需要存款的游戏数据
    //UINT32		iKindID;				//游戏类型
    //UINT32		iMonery;				//存款数量
    function CMD_MD_SAVING_ROLE_MONERY(&$socket, $iRoleID, $iGameSize, $aGameMoney)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iGameSize);
        $in_head = $this->comm->MakeSendHead(10, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
    }

//在线人数查询接口
    function CMD_MD_QUERY_ONLINE_PLAYER($socket)
    {
        //$this->in_stream = new PHPStream();
        $in_head = $this->comm->MakeSendHead(11, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//设置商人
//UINT32		iRoleID;				//角色ID
//UINT32		iSuperLevel;			//超级会员等级，转账反馈比例 万分之
    function CMD_MD_SET_SUPER_PLAYER(&$socket, $iRoleID, $iSuperLevel)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iSuperLevel);
        $in_head = $this->comm->MakeSendHead(12, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//同步配置数据
//UINT32		iLoadType;				//加载类型 0全部
    function CMD_MD_RELOAD_GAME_DATA($socket, $iLoadType)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iLoadType);
        $in_head = $this->comm->MakeSendHead(13, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//激活房间机器人
//UINT32		iRoomID;				//房间ID
    function CMD_MD_ACTIVE_ROOM_ROBOT($socket, $iRoomID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoomID);

        $in_head = $this->comm->MakeSendHead(14, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//查询房间机器人数据
//UINT32		iCurPage;				//当前页码
//UINT32		iPageSize;				//每页数量
    function CMD_MD_QUERY_ROOM_ROBOT_INFO($socket, $iCurPage, $iPageSize)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iCurPage);
        $this->in_stream->WriteULong($iPageSize);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROOM_ROBOT_INFO, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    function CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA($socket, $nRoomId)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($nRoomId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);

    }

//清除当前房间信息
//UINT32		iRoleID;				//角色ID
    function SendMDClearCurRoomInfo($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(CMD_MD_CLEAR_CUR_ROOM_INFO, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


//查询玩家总财富数据
//UINT32		iRoleID;				//角色ID
    function SendMDQueryRoleTotalMoney($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROLE_TOTAL_MONEY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//系统银行转账
//UINT32		iFromBankAccID;			//转出银行ID
//UINT32		iToBankAccID;			//转入银行ID
//INT64		iDealMoney;				//转账金币数量
    function SendMDSysBankDeal($socket, $iBankAccID, $iToBankAccID, $iDealMoney)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iBankAccID);
        $this->in_stream->WriteULong($iToBankAccID);
        $this->in_stream->WriteINT64($iDealMoney);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SYS_BANK_DEAL, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//设置系统银行余额
//UINT32		iBankAccID;				//银行ID
//INT64		iBankMoney;				//金币数量
    function SendMDAddSysBankMoney($socket, $iBankAccID, $iBankMoney)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iBankAccID);
        $this->in_stream->WriteINT64($iBankMoney);
        $in_head = $this->comm->MakeSendHead(CMD_MD_ADD_SYS_BANK_MONEY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//查询玩家权限
    function SendMDQueryRoleRight($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROLE_RIGHT, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//设置玩家权限
//UINT32		iRoleID;				//角色ID
//UINT32		iUserRight;				//用户权限
//UINT32		iMasterRight;			//管理权限
    function SendMDSetRoleRight($socket, $iRoleID, $iUserRight, $iMasterRight, $iSystemRight)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iUserRight);
        $this->in_stream->WriteULong($iMasterRight);
        $this->in_stream->WriteULong($iSystemRight);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_ROLE_RIGHT, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }
//查询玩家id
//char		szRoleName[32];			//角色昵称
    function SendMDQueryRoleID($socket, $szRoleName)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteString($szRoleName, 32);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROLE_ID, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//设置彩蛋抽税
//UINT32		iRoomID;				//房间ID
//UINT32		iLuckyEggTax;				//彩蛋抽税
    function SendMDSetLuckyEggTax($socket, $iRoomID, $iLuckyEggTax)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoomID);
        $this->in_stream->WriteULong($iLuckyEggTax);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_LUCKY_EGG_TAX, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//查询房间在线玩家

    function SendMDQueryRoomOnlinePlayers($socket, $iRoomID, $iCurpage, $iPagenum)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoomID);
        $this->in_stream->WriteULong($iCurpage);
        $this->in_stream->WriteULong($iPagenum);

        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROOM_ONLINE_PLAYERS, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    /*
     *  获取在线用户列表
     */
    function CMD_MD_QUERY_ALL_ONLINE_PLAYER($socket, $page = 0, $limit = 15)
    {
        $this->in_stream->WriteULong($page);
        $this->in_stream->WriteULong($limit);
        $in_head = $this->comm->MakeSendHead(32, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


//查询系统银行数据
//UINT32		iCurTime;				//当前时间
    function CMD_MD_QUERY_SYSTEM_BANK_DATA($socket, $iCurTime)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iCurTime);
        //CMD_MD_QUERY_SYSTEM_BANK_DATA =25
        $in_head = $this->comm->MakeSendHead(25, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//设置银行微信验证
//UINT32		iRoleID;				//角色ID
//UINT32		iCheck;					//是否验证 1验证 0不验证
    function CMD_MD_SET_BANK_WECHAT_CHECK($socket, $iRoleID, $iCheck)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iCheck);
        //CMD_MD_SET_BANK_WECHAT_CHECK =26
        $in_head = $this->comm->MakeSendHead(26, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }
//解锁玩家银行
//UINT32		iRoleID;				//角色ID
    function CMD_MD_UNLOCK_USER_BANK($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        //CMD_MD_UNLOCK_USER_BANK=27
        $in_head = $this->comm->MakeSendHead(27, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//锁定黄钻
//UINT32		iRoleID;				//角色ID
//UINT32		iDays;					//会员天数
    function CMD_MD_LOCK_ROLE_VIP($socket, $iRoleID, $iDays)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iDays);
        //CMD_MD_LOCK_ROLE_VIP=28
        $in_head = $this->comm->MakeSendHead(CMD_MD_LOCK_ROLE_VIP, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }
//清理玩家数据
//UINT32		iRoleID;				//角色ID
    function CMD_MD_CLEAR_ROLE_DATA($socket, $iRoleID)
    {  //CMD_MD_CLEAR_ROLE_DATA=29
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(29, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    function CMD_MD_QUERY_SUPER_USER_LIST($socket, $condition)
    { //CMD_MD_QUERY_SUPER_USER_LIST
        $len = strlen($condition);
        assert($len < 2000);
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteString($condition, $len);
        $in_head = $this->comm->MakeSendHead(30, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


//控制程序
//
    function CMD_WD_SET_USER_CTRL_DATA($socket, $AccountId, $Ratio, $ControlTimeLong, $ControlTimeInterval, $InitialPersonMoney)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($AccountId);
        $this->in_stream->WriteULong($Ratio);
        $this->in_stream->WriteULong($ControlTimeLong);
        $this->in_stream->WriteULong($ControlTimeInterval); //CMD_WD_SET_USER_CTRL_DATA=33
        $this->in_stream->WriteINT64($InitialPersonMoney);
        $in_head = $this->comm->MakeSendHead(33, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    ///老虎机概率设置
    function CMD_MD_SET_TIGER_CTRL_VALUE($socket, $AccountId, $TigerCtrlValue)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($AccountId);
        $this->in_stream->WriteULong($TigerCtrlValue);//CMD_MD_SET_TIGER_CTRL_VALUE=47
        $in_head = $this->comm->MakeSendHead(47, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//发送个人控制
    function CMD_MD_SET_ROOM_CTRL_DATA($socket, $RoomId, $Ratio, $InitStorage, $CurrentStorage, $szStorageRatio)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($RoomId);
        $this->in_stream->WriteULong($Ratio);
        $this->in_stream->WriteULong($InitStorage);
        $this->in_stream->WriteULong($CurrentStorage);
        $this->in_stream->WriteString($szStorageRatio, 512); //CMD_MD_SET_ROOM_CTRL_DATA=34
        $in_head = $this->comm->MakeSendHead(34, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }
    //发送个人控制
//    function SendMDCtrolNum($socket,$RoomId,$Ratio,$InitStorage,$CurrentStorage,$szStorageRatio){
    function CMD_MD_SET_ROOM_ROBOT_CTRL_DATA($socket, $RoomId, $szStorageRatio)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($RoomId);
        $this->in_stream->WriteString($szStorageRatio, 512); // CMD_MD_SET_ROOM_ROBOT_CTRL_DATA=42
        $in_head = $this->comm->MakeSendHead(42, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //发送个人控制
    function AddBlackIp($socket, $ip, $type)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteString($ip, 128);
        $this->in_stream->WriteULong($type);//CMD_MD_SET_IP_BLACK_LIST=36
        $in_head = $this->comm->MakeSendHead(36, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    function CMD_MD_QUERY_ROOM_CTRL_DATA($socket, $RoomId)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($RoomId);//CMD_MD_QUERY_ROOM_CTRL_DATA=35
        $in_head = $this->comm->MakeSendHead(35, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

//    function SendMDQueryNum($socket, $RoomId) {
//        //$this->in_stream = new PHPStream();
//        $this->in_stream->WriteULong($RoomId);
//        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA, $this->in_stream->len, 0, REQ_OM, REQ_DC);
//        $socket->request($in_head, $this->in_stream->data);
//    }


//发送个人控制
    function SendMDCtrolBank($socket, $dwUserId, $szUserName, $szBankCardNo, $szBankName, $szIFSCCode, $szMail, $wPayWayType)
    {
//        $wPayWayType = 2;
//        $szProvice = '浙江';
//        $szCity = '杭州';
//        $szUserName = iconv("UTF-8", "GB2312//IGNORE", $szUserName);
//        $szProvice = iconv("UTF-8", "GB2312//IGNORE", $szProvice);
//        $szCity = iconv("UTF-8", "GB2312//IGNORE", $szCity);
//        $szBankName = iconv("UTF-8", "GB2312//IGNORE", $szBankName);
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($dwUserId);
        $this->in_stream->WriteString($szUserName, 64);
        $this->in_stream->WriteString($szBankCardNo, 64);
        $this->in_stream->WriteString($szBankName, 33);
        $this->in_stream->WriteString($szIFSCCode, 33);
        $this->in_stream->WriteString($szMail, 256);
        $this->in_stream->WriteULong($wPayWayType);
        $in_head = $this->comm->MakeSendHead(37, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    function SendMDCtrolUnbindBank($socket, $dwUserId, $wPayWayType)
    {
        $this->in_stream->WriteULong($dwUserId);
        $this->in_stream->WriteULong($wPayWayType);
        $in_head = $this->comm->MakeSendHead(CMD_MD_DEL_PAY_WAY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //发送个人控制
    function SendMDCtrolRole($socket, $iRoleID, $iDays)
    {

        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($iDays);//CMD_MD_SET_PAY_WAY=37
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_PAY_WAY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //发送个人控制
    function SendMDCtrolUnlockRole($socket, $iRoleID)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);//CMD_MD_SET_PAY_WAY=37
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_PAY_WAY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    /**设置盈利千分比*/
//UINT32		nType;				//操作类型 1设置盈利千分比
//UINT32		nSetRange;				//设置范围 1所有服务器 2同一个类型 3具体游戏房间
//UINT32		nId;			//0/room kindid /room id
//UINT32		nProfilePercent;			千分比
//UINT32		$nAdjustValue;			偏离调整值
    function CMD_MD_PROFIT_PERCENT($socket, $nType, $nSetRange, $nId, $nProfilePercent, $nAdjustValue, $nRoomCtrl, $lCurStorage, $lMinStorage, $lMaxStorage)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($nType);
        $this->in_stream->WriteULong($nSetRange);
        $this->in_stream->WriteULong($nId);
        $this->in_stream->WriteULong($nProfilePercent);
        $this->in_stream->WriteULong($nAdjustValue);
        $this->in_stream->WriteULong($nRoomCtrl);
        $this->in_stream->WriteUINT64($lCurStorage);
        $this->in_stream->WriteINT64($lMinStorage);
        $this->in_stream->WriteINT64($lMaxStorage);
        $in_head = $this->comm->MakeSendHead(CMD_MD_PROFIT_PERCENT, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //查询盈利千分比
    function CMD_MD_QUERY_RUNNING_CTRL_DATA($socket, $roomId)
    {

        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($roomId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_RUNNING_CTRL_DATA, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //查询盈利千分比
    function SeWDQueryRunningCtrlNum($socket, $roomId)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($roomId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROOM_ROBOT_CTRL_DATA, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    //玩家强退
    function CMD_MD_FORCE_QUIT_ROL($socket, $roleId)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($roleId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_FORCE_QUIT_ROL, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    /**
     * Notes:
     * @param $socket
     * @param $roomId int
     * @param $nSysTaxRatio int
     * @param $nCaiJinRatio int
     */
    public function SeWDSetFishRoomRatio($socket, $roomId, $nSysTaxRatio, $nCaiJinRatio, $nExplicitTaxRatio)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($roomId);
        $this->in_stream->WriteULong($nSysTaxRatio);
        $this->in_stream->WriteULong($nCaiJinRatio);
        $this->in_stream->WriteULong($nExplicitTaxRatio);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_FISHROOM_RATIO, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    ///发送邮件
    function CMD_MD_SYSTEM_MAIL($socket, $iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $iDelaySecs, $mailType, $title, $mailtxt)
    {
        $this->in_stream->WriteULong($iSendId);
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($RecordType);
        $this->in_stream->WriteLong($ExtraType);
        $this->in_stream->WriteUINT64($iAmount);
        $this->in_stream->WriteLong($iDelaySecs);
        $this->in_stream->WriteLong($mailType); //nVerifyState  //0未审核 1 审核 2 删除
        $this->in_stream->WriteString($title, 64);
        $this->in_stream->WriteString($mailtxt, 512);
        $in_head = $this->comm->MakeSendHead(50, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    function CMD_MD_SYSTEM_MAILv2($socket, $iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $PayOrder, $WageMul, $iDelaySecs, $mailType, $title, $mailtxt, $Note, $Country, $szOperator)
    {
        $this->in_stream->WriteULong($iSendId);
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($RecordType);
        $this->in_stream->WriteLong($ExtraType);
        $this->in_stream->WriteUINT64($iAmount);
        $this->in_stream->WriteLong($PayOrder);
        $this->in_stream->WriteLong($WageMul);
        $this->in_stream->WriteLong($iDelaySecs);
        $this->in_stream->WriteLong($mailType); //nVerifyState  //0未审核 1 审核 2 删除
        $this->in_stream->WriteString($title, 64);
        $this->in_stream->WriteString($mailtxt, 512);
        $this->in_stream->WriteString($Note, 128);
        $this->in_stream->WriteString($Country, 32);
        $this->in_stream->WriteString($szOperator, 32);
        $in_head = $this->comm->MakeSendHead(50, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    function CMD_MD_SYSTEM_MAILv1($socket, $iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $iDelaySecs, $mailType, $title, $mailtxt, $Note)
    {
        $this->in_stream->WriteULong($iSendId);
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($RecordType);
        $this->in_stream->WriteLong($ExtraType);
        $this->in_stream->WriteUINT64($iAmount);
        $this->in_stream->WriteLong($iDelaySecs);
        $this->in_stream->WriteLong($mailType); //nVerifyState  //0未审核 1 审核 2 删除
        $this->in_stream->WriteString($title, 64);
        $this->in_stream->WriteString($mailtxt, 512);
        $this->in_stream->WriteString($Note, 128);
        $in_head = $this->comm->MakeSendHead(50, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    //审核邮件状态
    function SeMDMailStateChange($socket, $ID, $mailType)
    {
        $this->in_stream->WriteLong($ID);
        $this->in_stream->WriteLong($mailType); //nVerifyState  //0未审核 1 审核 2 删除
//        $this->in_stream->WriteString('', 100);
//        $log = FileLog::Init("socket", "socket");
//        $log::INFO("socket Len :" .  $this->in_stream->len);
        $in_head = $this->comm->MakeSendHead(56, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    ///发送邮件 CMD_MD_SYSTEM_MAIL
    function SeWDSendMailBox($socket, $roleId, $iRecordType, $iExtraType, $iAmount, $iDelaySecs, $mailtxt, $title)
    {
        $iSenderId = 0;
        $this->in_stream->WriteULong($iSenderId);
        $this->in_stream->WriteULong($roleId);
        $this->in_stream->WriteLong($iRecordType);
        $this->in_stream->WriteLong($iExtraType);
        $this->in_stream->WriteUINT64($iAmount);
        $this->in_stream->WriteLong($iDelaySecs);
        $this->in_stream->WriteString($title, 64);
        $this->in_stream->WriteString($mailtxt, 512);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SYSTEM_MAIL, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    ///发送邮件
    function SeWDSendPlayerMail($socket, $iSendId, $iRoleID, $iMailRecordType, $iMailExtraType, $llAmount, $nDelaySecs, $szText, $szTitle)
    {
        $this->in_stream->WriteULong($iSendId);
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($iMailRecordType);
        $this->in_stream->WriteLong($iMailExtraType);
        $this->in_stream->WriteUINT64($llAmount);
        $this->in_stream->WriteLong($nDelaySecs);
        $this->in_stream->WriteString($szTitle, 64);
        $this->in_stream->WriteString($szText, 512);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SYSTEM_MAIL, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    /*
 *  使用礼券
 */
    function SeWDPlayerUseConpon($socket, $nAccountId, $nUseCouponCount)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($nAccountId);
        $this->in_stream->WriteULong($nUseCouponCount);
        $in_head = $this->comm->MakeSendHead(CMD_WD_USE_COUPON, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    //获取礼券
    function SeWGetPlayerConpon($socket, $nAccountId)
    {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($nAccountId);
        $in_head = $this->comm->MakeSendHead(CMD_WD_GET_COUPON_COUNT, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    public function SeWDSendUserBalanceNotice($socket, $bTransfer, $nAccountId, $nKindId, $lGameMoney, $szOrderNo)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteChar($bTransfer);
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_stream->WriteUINT64($lGameMoney);
        $in_stream->WriteString($szOrderNo, 33);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_TRANSFER_RES, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }

    //加钱
    public function CMD_MD_THIRD_PARTY_TRANSFER_ADD($socket, $nAccountId, $nKindId, $lGameMoney, $szOrderNo)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_stream->WriteUINT64($lGameMoney);
        $in_stream->WriteString($szOrderNo, 33);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_TRANSFER_ADD, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }

    //加钱  竖版
    public function CMD_MD_THIRD_PARTY_TRANSFER_ADD2($socket, $nAccountId, $nKindId, $lGameMoney, $szOrderNo, $llGameBets)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_stream->WriteUINT64($lGameMoney);
        $in_stream->WriteUINT64($llGameBets);
        $in_stream->WriteString($szOrderNo, 33);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_TRANSFER_ADD, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }

    //扣钱
    public function CMD_MD_THIRD_PARTY_TRANSFER_SUB($socket, $nAccountId, $nKindId, $lGameMoney, $szOrderNo)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_stream->WriteUINT64($lGameMoney);
        $in_stream->WriteString($szOrderNo, 33);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_TRANSFER_SUB, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }


    public function CMD_MD_THIRD_PARTY_CLEAR_INGAME_LABEL($socket, $nAccountId, $nKindId)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_CLEAR_INGAME_LABEL, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }

    /*
     * 玩家下分
     */
    public function SeWDUserDownScore($socket, $nAccountId, $nKindId, $iGameMoney, $szOrderNo)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_stream->WriteULong($nKindId);
        $in_stream->WriteINT64($iGameMoney);
        $in_stream->WriteString($szOrderNo, 33);
        $in_head = $this->comm->MakeSendHead(CMD_MD_THIRD_PARTY_WRITE_GAME_MONEY, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }


    /*
     * query user balance
     * 查询玩家账号余额
     */
    public function SeWDQueryUserBalance($socket, $nAccountId)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($nAccountId);
        $in_head = $this->comm->MakeSendHead(CMD_MD_GET_ROLE_GAME_MONEY, $in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $in_stream->data);
    }



    //冻结财富
//UINT32		iRoleID;				//角色ID
//UINT32		iMonery;				//冻结财富
    public function SendMDLockRoleMonery($socket, $iRoleID, $iMonery)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($iRoleID);
        $in_stream->WriteINT64($iMonery);
        $in_head = $this->comm->MakeSendHead(CMD_MD_LOCK_ROLE_MONERY, $in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $in_stream->data;
        $socket->request($in_head, $in);
    }


    //查询玩家银行数据
//UINT32		iRoleID;				//角色ID
    public function SendMDQueryRoleBankInfo($socket, $iRoleID)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_ROLE_BANK_INFO, $in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $in_stream->data;
        $socket->request($in_head, $in);
    }


    public function SendMDUnLockMoney($socket, $iRoleID)
    {
        $in_stream = new PHPStream();
        $in_stream->WriteULong($iRoleID);
        $in_head = $this->comm->MakeSendHead(CMD_MD_UNLOCK_MONEY, $in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $in_stream->data;
        $socket->request($in_head, $in);
    }

    /**
     * 第三方充值
     * 人工收款审核使用到
     * @param Socket $socket
     * @param int $UserID
     * @param int $ChannelId
     * @param string $Torder char[64]
     * @param LONGLONG $RealMoney
     * @param LONGLONG $Money
     */
    public function CMD_MD_USER_CHANNEL_RECHARGE_N($socket, $UserID, $ChannelId, $Torder, $CurrencyType, $RealMoney, $Money)
    {
        $this->in_stream->WriteULong($UserID);
        $this->in_stream->WriteULong(4);
        $this->in_stream->WriteULong($ChannelId);
        $this->in_stream->WriteString($Torder, 64);
        $this->in_stream->WriteString($CurrencyType, 32);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($Money);
        $in_head = $this->comm->MakeSendHead(51, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    /**
     * 第三方充值
     * @param Socket $socket
     * @param int $UserID
     * @param int $ChannelId
     * @param string $Torder char[64]
     * @param LONGLONG $RealMoney
     * @param LONGLONG $Money
     *  //1 首充礼包  2 充值返利 3商店充值
     */
    public function CMD_MD_USER_CHANNEL_RECHARGE_old($socket, $UserID, $ChannelId, $Torder, $CurrencyType, $RealMoney, $Money, $ChargeType)
    {
        $this->in_stream->WriteULong($UserID);
        $this->in_stream->WriteULong($ChargeType);
        $this->in_stream->WriteULong($ChannelId);
        $this->in_stream->WriteString($Torder, 64);
        $this->in_stream->WriteString($CurrencyType, 32);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($Money);
        $in_head = $this->comm->MakeSendHead(51, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    /**
     * 第三方充值
     * @param Socket $socket
     * @param int $UserID
     * @param int $ChannelId
     * @param string $Torder char[64]
     * @param LONGLONG $RealMoney
     * @param LONGLONG $Money
     *  //1 首充礼包  2 充值返利 3商店充值
     */
    public function CMD_MD_USER_CHANNEL_RECHARGE_NEW($socket, $UserID, $ChannelId, $Torder, $CurrencyType, $RealMoney, $Money, $ChargeType, $AttenChargeAct)
    {
        $this->in_stream->WriteULong($UserID);
        $this->in_stream->WriteULong($ChargeType);
        $this->in_stream->WriteULong($ChannelId);
        $this->in_stream->WriteString($Torder, 64);
        $this->in_stream->WriteString($CurrencyType, 32);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($Money);
        $this->in_stream->WriteULong($AttenChargeAct);
        $in_head = $this->comm->MakeSendHead(51, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //获取打码量
    public function CMD_MD_QUERY_WAGED($socket, $roleid)
    {
        $this->in_stream->WriteULong($roleid);
        $in_head = $this->comm->MakeSendHead(CMD_MD_QUERY_WAGED, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //设置打码量
    public function CMD_MD_SET_WAGED($socket, $roleid, $type, $dm)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($type);
        $this->in_stream->WriteINT64($dm);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_WAGED, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //设置打码百分比
    public function CMD_MD_USER_WAGED_RATE($socket, $roleid, $type, $dm)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($type);
        $this->in_stream->WriteINT64($dm);
        $in_head = $this->comm->MakeSendHead(CMD_MD_USER_WAGED_RATE, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //设置上级邀请码
    public function CMD_MD_SET_PROXY_INVITE($socket, $roleid, $invite_code)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($invite_code);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_PROXY_INVITE, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //设置代理开关
    public function CMD_MD_SET_PROXY_SWITCH($socket, $roleid, $type)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($type);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_PROXY_SWITCH, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    //提现限制开关
    public function CMD_MD_SET_WITHDRAW_VIPLIMIT_SWITCH($socket, $roleid, $type)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($type);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_WITHDRAW_VIPLIMIT_SWITCH, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //手动绑定手机号
    public function CMD_WD_ACCOUNT_BIND_PHONE($socket, $roleid, $moblie, $password)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteString($moblie, 18);
        $this->in_stream->WriteString($password, 33);
        $in_head = $this->comm->MakeSendHead(CMD_WD_ACCOUNT_BIND_PHONE, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //手动绑定手机号
    public function CMD_WD_ACCOUNT_UNBIND_PHONE($socket, $roleid)
    {
        $this->in_stream->WriteULong($roleid);
        $in_head = $this->comm->MakeSendHead(CMD_WD_ACCOUNT_UNBIND_PHONE, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //删除账号
    public function CMD_MD_DELETE_ACCOUNT($socket, $roleid)
    {
        $this->in_stream->WriteULong($roleid);
        $in_head = $this->comm->MakeSendHead(CMD_MD_DELETE_ACCOUNT, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //设置上级邀请码
    public function CMD_MD_CHANGE_PROXY($socket, $roleid, $proxyid)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($proxyid);
        $in_head = $this->comm->MakeSendHead(CMD_MD_CHANGE_PROXY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //审核邮件状态
    function CMD_WD_SET_VIP($socket, $roleid, $rate)
    {
        $this->in_stream->WriteLong($roleid);
        $this->in_stream->WriteLong($rate);
        $in_head = $this->comm->MakeSendHead(132, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    //玩家手动上分
    public function CMD_WD_BUY_HAPPYBEAN($socket, $roleid, $money)
    {
        $this->in_stream->WriteLong($roleid);
        $this->in_stream->WriteLong($money);
        $this->in_stream->WriteLong(0);
        $in_head = $this->comm->MakeSendHead(100, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }


    //设置房间吃水、放水 int nSetRange;    //设置范围 1所有服务器 2同一个类型 3具体游戏房间
    public function CMD_MD_SET_ROOM_WATER_DATA($socket, $nSetRange, $nId, $CurWaterIn, $CurWaterOut)
    {
        $this->in_stream->WriteLong($nSetRange);
        $this->in_stream->WriteLong($nId);
        $this->in_stream->WriteINT64($CurWaterIn);
        $this->in_stream->WriteINT64($CurWaterOut);
        $in_head = $this->comm->MakeSendHead(69, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);

    }

    //增加渠道余额
    public function CMD_MD_ADD_WITHDRAW_REMAIN($socket, $roleid, $amount)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteINT64($amount);
        $in_head = $this->comm->MakeSendHead(CMD_MD_ADD_WITHDRAW_REMAIN, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    //预设游戏结果
    public function CMD_MD_SET_SPACE_INFO($socket, $operator_id, $round_id, $result)
    {
        $this->in_stream->WriteLong($operator_id);
        $this->in_stream->WriteUINT64($round_id);
        $this->in_stream->WriteString($result, 11);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_SPACE_INFO, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    //设置代理佣金额外提现额度
    public function CMD_MD_SET_PROXY_WITHDRAW_EXTRA_AMOUNT($socket, $roleid, $amount)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteINT64($amount);
        $in_head = $this->comm->MakeSendHead(CMD_MD_SET_PROXY_WITHDRAW_EXTRA_AMOUNT, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //查询玩家状态
    public function CMD_MD_USER_STATE($socket, $roleid)
    {
        $this->in_stream->WriteULong($roleid);
        $in_head = $this->comm->MakeSendHead(CMD_MD_USER_STATE, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    //查询玩家状态
    public function CMD_MD_CHANNEL_CHILD_ONLINE_COUNTS($socket, $operator_id)
    {
        $this->in_stream->WriteULong($operator_id);
        $in_head = $this->comm->MakeSendHead(CMD_MD_CHANNEL_CHILD_ONLINE_COUNTS, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }


    public function CMD_MD_GM_UPDATE_USER_TYPE($socket, $roleid, $type, $status)
    {
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteULong($type);
        $this->in_stream->WriteINT64($status);
        $in_head = $this->comm->MakeSendHead(CMD_MD_GM_UPDATE_USER_TYPE, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    public function CMD_MD_GM_PDD_ADD_MONEY($socket, $roleid, $type, $amount)
    {
        $this->in_stream->WriteULong($type);
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteINT64($amount);
        $in_head = $this->comm->MakeSendHead(CMD_MD_GM_PDD_ADD_MONEY, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    public function CMD_MD_GM_PDD_COMMI_SUC($socket, $roleid)
    {
        $this->in_stream->WriteULong($roleid);
        $in_head = $this->comm->MakeSendHead(CMD_MD_GM_PDD_COMMI_SUC, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    public function CMD_MD_GM_PDD_REFUND($socket)
    {

        $in_head = $this->comm->MakeSendHead(CMD_MD_GM_PDD_REFUND, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }

    public function CMD_MD_GM_ADD_JOB($socket, $roleid, $nKey, $nAddVal)
    {
        $this->in_stream->WriteULong($nKey);
        $this->in_stream->WriteULong($roleid);
        $this->in_stream->WriteINT64($nAddVal);
        $in_head = $this->comm->MakeSendHead(CMD_MD_GM_ADD_JOB, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res = $socket->request($in_head, $in);
    }
}





