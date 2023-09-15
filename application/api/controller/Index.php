<?php
namespace app\api\controller;

use app\common\Api;
use app\model\CommonModel;
use socket\QuerySocket;
use think\Controller;

class Index extends Controller
{
    //推广提现
    public function addSpreadUserMoney() {

        $orderId = $_POST["orderid"];
        $roleid = $_POST["roleid"];
        $time = $_POST["time"];
        $token = $_POST["token"];
        $amount = $_POST["amount"];
        $check = md5(config("skey") . $roleid . $amount . $orderId . $time);
//        $orderId = '201909101234567';
//        $amount = 1005;
//        $roleid = 61644520;
        save_log('api/addSpreadUserMoney', $orderId.'||'.$amount."||".$roleid);
        if (!$orderId || !$amount || !$roleid || $amount<=0 || $amount>10000) {
            save_log('api/addSpreadUserMoneyFail', $orderId.'||'.$amount."||".$roleid);
            echo "FAIL1";
        } elseif ($check != $token) {
            save_log('api/addSpreadUserMoneyFail', $check.'<==>'.$token);
            echo "FAIL3";
        } else {
            $amount *= 1000;
//            $arrResult = $socket->addRoleMoney($roleid,$amount*1000,0,0);
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleid,
                'orderid' => (string)$orderId,
                'imoney'  => $amount,
                'states'  => 0
            ], 'payment', 'spreadcharge');
            save_log('api/addSpreadUserMoneyStatus', $orderId.'||'.$amount."||".$roleid.json_encode($res, JSON_UNESCAPED_UNICODE));
            if ($res['data'] == true) {
                echo 'SUCCESS';
            } else {
                echo 'FAIL2';
            }
        }
    }

    //在线
    public function getOnlineUser(){
        $socket = new QuerySocket();
        $arrResult = null;
        $arrResult = $socket->DCQueryAllOnlinePlayer();
//        var_dump($arrResult);
//        die;
        $arrOnlineList = isset($arrResult["onlinelist"]) ? $arrResult["onlinelist"] : [];
//        var_dump($arrOnlineList);
//        die;
        if($arrOnlineList)
        {
            foreach ($arrOnlineList as &$val) {
                $loginset = '';
                if ($val['nClientType'] == 0) {
                    $loginset = '电脑';
                } else if ($val['nClientType'] == 1) {
                    $loginset = '安卓';
                } else if ($val['nClientType'] == 2) {
                    $loginset = 'IOS';
                }
                $val['roomid'] = $val['iRoomId'];;
                $val['device'] = $loginset;
                $val['iUserID'] = $val['iUserId'];
                $val['szUsername'] = trim($val['szAccount']);
                $val['kindname'] = trim($val['szRoomName']);
                $val['gamemoney'] = FormatMoney($val['iGameMoney']);
                $val['bankmoney'] = FormatMoney($val['iBankMoney']);
                $val['totalwin'] = FormatMoney($val['iTotalDespoit']);
                unset($val['szAccount'], $val['szRoomName']);
            }
            unset($val);
        }
        echo json_encode($arrOnlineList, JSON_UNESCAPED_UNICODE);
    }


    //获取优惠数量
    public function  querycoupon(){
        $roleid =input('roleid',0);
        if($roleid>0){
            $socket = new QuerySocket();
            $ret =$socket->getPlayerCoupon($roleid);
            $coupon = 0;
            if(!empty($ret)){
                $coupon = $ret['iResult'];
            }
            echo json_encode(['code'=>0,'data'=>$coupon,'message'=>'success']);
        }
        else
        {
            echo json_encode(['code'=>100,'data'=>0,'message'=>'roleid is need']);
        }

    }



    ///玩家使用优惠券
    public function  usercoupon(){
        $roleid =input('roleid',0);
        $coupon = input('coupon',0);

        if($roleid==0 || $coupon==0){
            echo json_encode(['code'=>100,'message'=>'parameter error']);
        }

        $socket = new QuerySocket();
        $ret =$socket->UsePlayerCoupon($roleid,$coupon);
        if($ret){
            echo json_encode(['code'=>0,'data'=>$ret['iResult'],'message'=>'success']);
        }
        else
        {
            echo json_encode(['code'=>101,'data'=>0,'message'=>'faild,system error']);
        }

    }


    /**
     * Notes: 黑名单ID判断
     * @param $roleid 参数
     * @return int
     */
    public function getCouponBlackList()
    {
        $roleid = $_GET['RoleID'];
        $strFields = " count(*) as count ";//
        $tableName = " [CD_UserDB].[dbo].[T_RoleExpand] ";
        $where = " where CouponExchangeDisable=1 and RoleID=".$roleid;
        $orderBy = "";
        $comm = new CommonModel;
        $res = $comm->getsql($tableName,$strFields,$where,$orderBy);
        $result = $res['count'];
        $res = json_encode(['code'=>1,'data'=>$result]);
        return $result;
    }

    /**
     * Notes: 用户消息转发
     * @param int    nAccountId;
     * char  szDeviceId[PASS_LEN];  //设置唯一标识码
     * int   nActionType;
     * int   nRoomId;
     * bool  bFirstLoad;
     * bool  bNeedUpdate;    //是否需要更新
     * bool  bSucc;        //该操作是否成功
     * 消息头：CMD_WD_SAVE_USER_ACTION = 122,  //记录玩家行为数据
     * @return int
     */
    public function getUserAction()
    {
        $params = (array)json_decode(file_get_contents('php://input'), true);
        $useraction = $params['CMD_WD_SAVE_USER_ACTION'];
        $url = '';
        if ($useraction == 122) {
            $data = array(
                'nAccountId' => $params['nAccountId'],
                'szDeviceId' => $params['szDeviceId'],
                'nActionType' => $params['nActionType'],
                'nRoomId' => $params['nRoomId'],
                'bFirstLoad' => $params['bFirstLoad'],
                'bNeedUpdate' => $params['bNeedUpdate'],
                'bSucc' => $params['bSucc'],
            );

            print_r($data);
            exit();
//			$curl = new Api();
//			$curl->curlPost($url,$data);
        }
    }

    public function AgentWaterSum(){
        $white_list = ['54.233.122.115','54.254.138.198','106.75.239.173'];
        if (!in_array(request()->ip(), $white_list)) {
            exit();
        }
        $num = input('num')?:0;
        $field = 'A.AccountID,A.AccountName,A.Mobile,ISNULL(B.ReceivedIncome,0) As ReceivedIncome,ISNULL(B.TotalDeposit,0) AS TotalDeposit,ISNULL(B.TotalTax,0) AS TotalTax,ISNULL(B.TotalRunning,0) AS TotalRunning,ISNULL(B.Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(B.Lv1Deposit,0) AS Lv1Deposit,ISNULL(B.Lv1DepositPlayers,0) AS Lv1DepositPlayers,ISNULL(B.Lv1Tax,0) AS Lv1Tax,ISNULL(B.Lv1Running,0) AS Lv1Running,ISNULL(B.Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(B.Lv2Deposit,0) AS Lv2Deposit,ISNULL(B.Lv2DepositPlayers,0) AS Lv2DepositPlayers,ISNULL(B.Lv2Tax,0) AS Lv2Tax,ISNULL(B.Lv2Running,0) AS Lv2Running,ISNULL(B.Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(B.Lv3Deposit,0) AS Lv3Deposit,ISNULL(B.Lv3DepositPlayers,0) AS Lv3DepositPlayers,ISNULL(B.Lv3Tax,0) AS Lv3Tax,ISNULL(B.Lv3Running,0) AS Lv3Running,ISNULL(B.Lv1WithdrawCount,0) AS Lv1WithdrawCount,ISNULL(B.Lv2WithdrawCount,0) AS Lv2WithdrawCount,ISNULL(B.Lv3WithdrawCount,0) AS Lv3WithdrawCount,ISNULL(B.Lv1WithdrawAmount,0) AS Lv1WithdrawAmount,ISNULL(B.Lv2WithdrawAmount,0) AS Lv2WithdrawAmount,ISNULL(B.Lv3WithdrawAmount,0) AS Lv3WithdrawAmount';

        $data =  (new \app\model\AccountDB())->getTableObject('T_Accounts')
            ->alias('A')
            ->join('[CD_UserDB].[dbo].[T_ProxyCollectData](NOLOCK) B', 'B.ProxyId=A.AccountID', 'LEFT')
            ->where('Lv1PersonCount','>=',$num)
            ->where('Mobile','<>','')
            ->field($field)
            // ->fetchSql(true)
            ->select();
        $roleid_ids = array_column($data, 'AccountID')?:[0];

        $sql1 = "select RoleId,addtime,Amount from T_ProxyMsgLog where id in(select min(id) from T_ProxyMsgLog where RoleId in(".implode(',',$roleid_ids).") and RecordType=8 and Amount>0 and VerifyState=1 group by RoleId)";

        $sql2 = "select RoleId,sum(Amount) Amount from T_ProxyMsgLog where RoleId in(".implode(',',$roleid_ids).") and RecordType=8 and Amount>0  and VerifyState=1 group by RoleId";

        $data1 = (new \app\model\DataChangelogsDB())->DBOriginQuery($sql1) ?? [];
        $data2 = (new \app\model\DataChangelogsDB())->DBOriginQuery($sql2) ?? [];

        $data = [
            'data'=>$data,
            'data1'=>$data1,
            'data2'=>$data2
        ];
        // $v = &$data;
        // $v['TotalTax'] = FormatMoney($v['TotalTax']);
        // $v['TotalRunning'] = FormatMoney($v['TotalRunning']);
        // //$v['Lv1Deposit'] = FormatMoney($v['Lv1Deposit']);
        // $v['Lv1Tax'] = FormatMoney($v['Lv1Tax']);
        // $v['Lv1Running'] = FormatMoney($v['Lv1Running']);

        // // $v['Lv2Deposit'] = FormatMoney($v['Lv2Deposit']);
        // $v['Lv2Tax'] = FormatMoney($v['Lv2Tax']);
        // $v['Lv2Running'] = FormatMoney($v['Lv2Running']);

        // // $v['Lv3Deposit'] = FormatMoney($v['Lv3Deposit']);
        // $v['Lv3Tax'] = FormatMoney($v['Lv3Tax']);
        // $v['Lv3Running'] = FormatMoney($v['Lv3Running']);

        // $lv1rate = bcdiv(10, 1000, 4);
        // $lv2rate = bcdiv(5, 1000, 4);
        // $lv3rate = bcdiv(2.5, 1000, 4);
        // $Lv1Reward = bcmul($v['Lv1Running'], $lv1rate, 4);
        // $Lv2Reward = bcmul($v['Lv2Running'], $lv2rate, 4);
        // $Lv3Reward = bcmul($v['Lv3Running'], $lv3rate, 4);
        // $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
        // $rewar_amount = bcadd($rewar_amount, $Lv3Reward,2);
        // $v['ReceivedIncome'] =  $rewar_amount;
        return json(['code'=>0,'data'=>$data]);
    }

}