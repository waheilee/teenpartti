<?php


namespace app\merchant\controller;

use app\model\GameOCDB;
use app\model\UserDB;

/**
 * Class UserPackageManage
 * @package app\admin\controller
 */
class UserPackageManage extends Main
{
    /**
     * 多包用户管理
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        if (1) {
            $startTime = input("startTime") ? input("startTime") : null;
            $endTime = input("endTime") ? input("endTime") : null;
            $roleId = input('roleid', 0);
            $nickname = trim(input('nickname'));
            $isdisable = intval(input('isdisable', -1));
            $orderby = input('orderfield', 'RegisterTime');
            $ordertype = input('ordertype', 'desc');
            $mobile = trim(input('mobile')) ? trim(input('mobile')) : '';
            $ipaddr = trim(input('ipaddr')) ? trim(input('ipaddr')) : '';
            $usertype = intval(input('usertype', -1));
            $online = intval(input('isonline', -1));
            $packID = input('PackID', -1);
            $where = "";
            if ($roleId > 0) $where .= " and  AccountID =" . $roleId;
            if (!empty($nickname)) $where .= " AND nickname like '%$nickname%' ";
            if (!empty($startTime) && !empty($endTime)) $where .= " AND RegisterTime BETWEEN '$startTime' AND '$endTime' ";
            if ($isdisable >= 0) $where .= " AND  locked=$isdisable";
            if (!empty($mobile)) $where .= " and  Mobile like '%$mobile%'";
            if (!empty($ipaddr)) $where .= " and  lastloginip like '%$ipaddr%'";
            if ($usertype >= 0) $where .= " and  gmtype =$usertype";
            if ($packID >= 0) $where .= " AND OperatorId in ($packID)";
            //返回在线列表给前端
            if ($online == 0) {
                /**获取在在线列表*/
                $online = $this->GetOnlineUserlist();
                if ($online) {
                    $online = implode(',', $online);
                    $where .= "And AccountID in($online)";
                } else {
                    $where .= "And 1=2";
                }
            }
        }
        $field = "PackageName,AccountID ID,MachineCode,countryCode,AccountName,Locked,LoginName,GmType,RegisterTime,LastLoginIP,TotalDeposit,TotalRollOut,Money";
        $db = new UserDB('View_Accountinfo');
        switch (input('Action')) {
            case 'list':
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
                return $this->apiJson($result);
                break;
            case 'onlinelist':
                return $this->apiJson(['list' => $this->GetOnlineUserlist()]);
                break;
            case 'exec'://导出表格
                $field = "PackageName,AccountID ID,MachineCode,countryCode,AccountName,LoginName,RegisterTime,LastLoginIP,TotalDeposit,TotalRollOut,Money";
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
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
                        lang('包名') => 'string',//PackageName
                        'ID' => 'integer',//ID
                        lang('机器码') => 'string',//MachineCode
                        lang('国家代码') => "string",//countryCode
                        lang('账号') => 'string',//AccountName
//                        '是否禁用' => "string",//Locked
                        lang('昵称') => 'string',//LoginName
//                        '登陆类型' => "string",//GmType
                        lang('注册时间') => "datetime",//RegisterTime
                        lang('最后登录IP') => "string",//LastLoginIP
                        lang('总充值') => "0.00",//TotalDeposit
                        lang('总转出') => '0.00',//TotalRollOut
                        lang('剩余金币') => '0.00'//Money
                    ];
                    $filename = lang('多包用户列表').'-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }


        $usertype = config('usertype');
        $this->assign('PackIDS', session('PackID'));
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('usertype', $usertype);
        $this->assign('selectData', $this->getRoomList());
        return $this->fetch();
    }

    /**
     * 用户详情
     * @return mixed
     */
    public function UserInfo()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        if ($roleId > 0) {
            //账号表数据
            $db = new UserDB();
            $user = $db->TViewAccount()->GetRow(["AccountID" => $roleId]);
            if (!empty($user)) {
                $user['PackName'] = $this->GetPackageName()[$user['OperatorId']]["PackageName"];
                $this->assign('usreid', $roleId);
                $this->assign("user", $user);
            }
        }

        $bankname = config('site.bank');
        $this->assign('bankcardno', '');
        $this->assign('bankname3', '');
        $this->assign('mytip', '22');
        $this->assign('bankname', $bankname);
        return $this->fetch();
    }

    //游戏记录
    public function GameRecord()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d");
            $enddate = input('enddate') ? input('enddate') : date("Y-m-d");
            $winlost = intval(input('winlost')) >= 0 ? intval(input('winlost')) : -1;
            $Account = input('mobile') ? input('mobile') : ''; //账号
            $packID = input('PackID', -1);
            $where = "";
            if (!empty($Account)) $roleId = $this->GetUserIDByAccount($Account);
            if ($roleId > 0) $where .= " AND RoleID=$roleId";
            if ($winlost >= 0) $where .= " AND ChangeType=$winlost";
            if ($packID >= 0) $where .= " AND EXISTS (SELECT * FROM CD_Account.dbo.T_Accounts WHERE OperatorId IN ($packID)) ";
            /**  SQL
             * SELECT * FROM ( SELECT LoginName,AccountName,RoleID,SerialNumber,ChangeType,GameRoundRunning,AddTime,Tax,PackageName,OperatorId,
             * RoomID/10*10 KindID,RoomID,RoomName,A.Money,LastMoney,LastMoney+Tax-A.Money PreMoney FROM (
             * SELECT * FROM dbo.T_UserGameChangeLogs_20210428 (NOLOCK)
             * WHERE 1=1  AND EXISTS (SELECT * FROM CD_Account.dbo.T_Accounts WHERE OperatorId IN ( 0 ) )   ) A
             * LEFT JOIN OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.ServerID=B.ServerID
             * LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON C.AccountID=RoleID)A
             * ORDER BY AddTime DESC OFFSET 0 ROWS FETCH NEXT 15 ROWS ONLY */
            $db = new GameOCDB();
            $result = $db->GetPackGameRecord($page, $limit, $where, $strartdate, $enddate, $roomid, true);
            return $this->apiJson($result, true);
        }
        $selectData = $this->getRoomList();
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    /**
     * Notes: 金币日志
     * @return mixed
     */
    public function GoldRecord()
    {
        $changeType = config('site.bank_change_type');
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $res = $db->GetPackGoldRecord();
                return $this->apiJson($res, true);
                break;
            case 'exec'://导出表格
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
                $outAll = input('outall', false);
                $db = new GameOCDB();
                $result = $db->GetPackGoldRecord();
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
                        lang('角色ID') => 'integer',
                        lang('变更类型') => 'string',
                        lang('ServerID') => "string",
                        lang('金币变化') => '0.00',
                        lang('金币变化后') => "0.00",
                        lang('税收') => 'string',
                        lang('描述') => "string",
                        lang('记录时间') => "datetime",
                        lang('账号') => "string",
                        lang('包名') => "string",
                        lang('昵称') => 'string',
                        lang('机器码') => 'string'
                    ];

                    $filename = lang('多包金币日志').'-' . date('YmdHis');
                    $writer = $this->GetExcel($filename, $header_types, $result['list'], true);
                    $rows =& $result['list'];
                    foreach ($rows as $index => &$row) {
                        unset($row['Id'], $row['OperatorId'], $row['PayId'], $row['PayName'], $row['TargetID'], $row['TargetName'], $row['ChargeAward'], $row['ClientIP'], $row['gmtype']);
                        ConVerMoney($row['Money']);
                        ConVerMoney($row['LastMoney']);
                        ConVerMoney($row['Tax']);
                        $writer->writeSheetRow('sheet1', $row, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row);
                    $writer->writeToStdOut();
                    exit();
                }
                break;
        }


        $usertype = config('usertype');
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $this->assign('usertype', $usertype);
        $this->assign('changeType', $changeType);
        $this->assign('RoomList', json_encode($this->GetRoomList()));
        return $this->fetch();
    }

    /**
     * Notes:金币排行
     */
    public function GoldRank()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $orderby = input('orderby', "Money");
            $ordertype = input('ordertype', 'desc');
            $packID = input('PackID', -1);
            $where = "";
            if ($packID >= 0) $where .= " AND OperatorId IN ($packID)";
            $db = new UserDB();
            $res = $db->getTablePage('View_Accountinfo', $page, $limit, $where, $orderby, $ordertype,
                'AccountID,LoginName,Money', true);
            return $this->apiJson($res);
        }
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        return $this->fetch();

    }

    //战绩排行
    public function GoldRecordRank()
    {

        if ($this->request->isAjax()) {
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $orderby = input('orderby', 'total');
            $ordertype = input('ordertype', 'Desc');
            $packID = input('PackID', -1);
            $where = "";
            if ($packID >= 0) $where .= " AND OperatorId IN ($packID)";
            $db = new UserDB();
            $res = $db->getTablePage('View_Accountinfo', $page, $limit, $where, $orderby, $ordertype,
                'AccountID,LoginName,Money,TotalDeposit,TotalRollOut,TotalDeposit-TotalRollOut-Money as total');
            return $this->apiJson($res);

        }
        $selectData = $this->getRoomList();
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $this->assign('selectData', $selectData);
        return $this->fetch();

    }

    //转换牌型
    public function PokeToImage(&$res, $roomID, $userID)
    {
        $search = ["[[", '[', ']', '黑桃', '梅花', '方块', '红桃', '大王', '小王'];
        $poke = ["[", "[,", ",],", 'T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'];
        $list = [];
        $other = "[";
        if (!empty($res['list'])) {
            if (in_array($roomID, [2500, 2700, 2800, 2900, 3000, 3500])) { //已修改

                $gamedata =$res['list'];
                $data = json_decode(str_replace($search, $poke, $gamedata[0]["GameDetail"]), true);
                unset($gamedata[0]);

                if(count($gamedata)>0) {
                    foreach ($gamedata as $k => &$v) {
                        $userdetail = json_decode($v['GameDetail'], true);
                        $v['chairid'] = $userdetail['player']['chairid'];
                        $v['bet'] = $userdetail['player']['bet'];
                        $v['winlost'] = isset($userdetail['lost'])?$userdetail['lost']:$userdetail['win'];
                    }
                    unset($v);
                }
                $gamedata =array_values($gamedata);

                $other =[];// json_decode(substr($other, 0, strlen($other) - 1) . "}]", true);
                $result = [];
                for ($i = 0; $i < 5; $i++) {
                    $roledata =[];
                    $found_arr = array_column($gamedata, 'chairid');
                    $found_key = array_search($i, $found_arr);
                    if($found_key!==false)
                        $roledata = $gamedata[$found_key];

                    if (isset($data['card']["player$i"])) {
                        $card = explode(",", $data['card']["player$i"]);
                        if (in_array($roomID, [3500])) {
                            $row["poke"] = array_slice($card, 1, -2);
                            $row["lpoke"] = array_slice(explode(',', $data['initcard']["player$i"]), 1, -2);
                        } else if (in_array($roomID, [2500, 3000])) {
                            $row["poke"] = array_slice($card, 1, -2);
                            $row["lpoke"] = [];
                        } else {
                            $index = array_search('OldCard', $card);//找到"OldCard" 下标
                            $row["poke"] = array_slice($card, 1, $index - 1);
                            $row["lpoke"] = array_slice($card, $index + 1, -2);
                        }


                        $row['roleid']  =lang('机器人');
                        $row['chairid'] =$i;
                        $row['bet'] =0 ;
                        $row['winlost'] =0;

                        if(!empty($roledata)){
                            $row['roleid'] =$roledata['UserId'];
                            $row['bet'] =bcdiv($roledata['bet'],bl,2);
                            $row['winlost'] =bcdiv($roledata['winlost'],bl,2);
                        }
                        $row["banker"] = $data['banker'];
                        array_push($result, $row);
                    }
                }

                $list = $result;


            } //已修改
            if (in_array($roomID, [2600, 3100])) {
                $gamedata =$res['list'];
                $data = json_decode($gamedata[0]["GameDetail"], true);
                unset($gamedata[0]);

                if(count($gamedata)>0) {
                    foreach ($gamedata as $k => &$v) {
                        $userdetail = json_decode($v['GameDetail'], true);
                        $v['chairid'] = $userdetail['player']['chairid'];
                        $v['bet'] = $userdetail['player']['bet'];
                        $v['winlost'] = isset($userdetail['lost'])?$userdetail['lost']:$userdetail['win'];
                    }
                    unset($v);
                }
                $gamedata =array_values($gamedata);

                $other = [];
                if (isset($data['card']['clown'])) { //小丑牌
                    $clown = str_replace($search, $poke, $data['card']['clown']);
                    $clown = explode(",", $clown);
                    $row["card"] =  $clown;
                    $row["itemname"] =  lang('小丑牌');
                    array_push($other,$row);
                }
                for ($i = 0; $i < 5; $i++) {
                    $roledata =[];
                    $found_arr = array_column($gamedata, 'chairid');
                    $found_key = array_search($i, $found_arr);
                    if($found_key!==false)
                        $roledata = $gamedata[$found_key];
                    // =
                    //初始牌
                    if (isset($data['card']['player' . $i])) {
                        $card = str_replace($search, $poke, $data['card']['player' . $i]);
                        $card = substr($card, 0, strlen($card) - 1);
                        $card = explode(",", $card);
                        $card["player"] =  lang("初始");
                        $card["chareid"] = $i;
                        $card['roleid']  =lang('机器人');
                        $card['bet'] =0 ;
                        $card['winlost'] =0;
                        if(!empty($roledata)){
                            $card['roleid'] =$roledata['UserId'];
//                            $card['bet'] =bcdiv($roledata['bet'],bl,2);
//                            $card['winlost'] =bcdiv($roledata['winlost'],bl,2);
                        }
                        array_push($list, $card);
                    }
//                    if (isset($data['bet']['player' . $i])) {
//                        $bet = $data['bet']['player' . $i];
//                        array_push($list, $bet);
//                    }
                    //摆牌
                    if (isset($data['arrange']['player' . $i])) {
                        $arrange = str_replace($search, $poke, $data['arrange']['player' . $i]);
                        $arrange = substr($arrange, 0, strlen($arrange) - 1);
                        $arrange = explode(",", $arrange);
                        $arrange["player"] =lang("摆牌");
                        $arrange["chareid"] = $i;
                        $arrange['roleid']  =lang('机器人');
                        $arrange['bet'] =0 ;
                        $arrange['winlost'] =0;
                        if(!empty($roledata)){
                            $arrange['roleid'] =$roledata['UserId'];
                            $arrange['bet'] =bcdiv($roledata['bet'],bl,2);
                            $arrange['winlost'] =bcdiv($roledata['winlost'],bl,2);
                        }
                        array_push($list, $arrange);
                    }
                }
//                if (($res['list'][0]['UserId']) == 0) {
//                    $other = json_decode("[" . $res['list'][1]["GameDetail"] . "]", true);
//                    $res['list'] = json_decode("[" . $res['list'][0]["GameDetail"] . "]", true);
//                } else {
//                    $other = json_decode("[" . $res['list'][0]["GameDetail"] . "]", true);
//                    $res['list'] = json_decode("[" . $res['list'][1]["GameDetail"] . "]", true);
//                }

            } //已修改
            if (in_array($roomID, [3200])) {
                $data = $res['list'];
                for ($i = 0; $i < count($data); $i++) {
                    if ($data[$i]["UserId"] == "0") {   //统计数据
                        $list = json_decode("[" . $data[$i]['GameDetail'] . "]", true);
                    } else if ($data[$i]["UserId"] == $userID) {                        //用户数据
                        $other = json_decode($data[$i]['GameDetail'], true);

                        $other = [[
                            'Score' => FormatMoney(isset($other['lost']) ? $other['lost'] : $other['win']),
                            'lostpoints' => $other['lostpoints'],
                            'bet' => FormatMoney($other['player']['bet']),
                            'chairid' => $other['player']['chairid'],
                            'fetched' => $other['player']['fetched'],
                            'gold' => FormatMoney($other['player']['gold']),
                            'initgold' => $other['player']['initgold'],
                            'status' => $other['player']['status'],
                        ]];
                    }
                }
                $result = [];
                for ($i = 0; $i < 5; $i++) {
                    if (isset($list[0]['user']["player$i"])) {
                        $row["detail"] = $list[0]['pointsdetail']['detail']["player$i"];
                        $row["total"] = $list[0]['pointsdetail']["total"]["player$i"];
                        $row["endpoints"] = $list[0]["endpoints"];
                        $row["win"] = bcdiv($list[0]["win"]["player$i"],bl,2);
                        $row["UserID"] = $list[0]["user"]["player$i"];
                        $row["bet"] = bcdiv($list[0]["bet"]["player$i"],bl,2);
                        $row["player"] = "$i";
                        array_push($result, $row);
                    }
                }
                $list = $result;
            } //已修改
            if (in_array($roomID,[3300,3600])) {
                /**7u7d**/
                $data = $res['list'];
                $strother = "[";

                $winarea = '';
                $opendata='';
                for ($i = 0; $i < count($data); $i++) {
                    if ($data[$i]["UserId"] == "0") {   //统计数据
                        $result = json_decode($data[$i]['GameDetail'], true);
                        //统计转换
//                        $list[0]["name"] = "区域点数";
//                        $list[1]["name"] = "区域豹子";
//                        for ($j = 1; $j <= 11; $j++) {
//                            if ($j > 2 && isset($result['bet']["点数$j"])) {
//                                $list[0]["card"][$j] = FormatMoney(isset($result['bet']["点数$j"]) ? $result['bet']["点数$j"] : 0);
//                                unset($result['bet']["点数$j"]);
//                            }
//                            if (isset($result['bet']["豹子$j"])) {
//                                $list[1]["card"][$j] = FormatMoney(isset($result['bet']["豹子$j"]) ? $result['bet']["豹子$j"] : 0);
//                                unset($result['bet']["豹子$j"]);
//                            }
//                        }
//                        $list[] = ["name" => "区域:DOWN", "card" => FormatMoney(isset($result['bet']['DOWN']) ? $result['bet']['DOWN'] : 0)];
//                        $list[] = ["name" => "区域:UP", "card" => FormatMoney(isset($result['bet']['UP']) ? $result['bet']['UP'] : 0)];
//                        $list[] = ["name" => "区域:任意豹子", "card" => FormatMoney(isset($result['bet']['任意豹子']) ? $result['bet']['任意豹子'] : 0)];
                        $list[] = ["name" => lang("开奖结果"), "card" => explode(',', $result['card'])];
                        $list[] = ["name" => lang("中奖区域"), 'card' => $result['gameresult']];
                        // $list[] = ["name" => '总下注', "card" => FormatMoney($result['usertotalbet'])];
                        unset($result);
                    } else if ($data[$i]["UserId"] >0) {
                        $temp = str_replace(array("\r\n", "\r", "\n"), "", $data[$i]['GameDetail']);
                        $temp =rtrim($temp, "}");
                        $temp .= ',"UserId":"' . $data[$i]["UserId"] . '"}';
                        $strother .= $temp . ",";
                    }
                }
                $other=[];
                $strother = str_replace(array("\r\n", "\r", "\n"), "", $strother);
                $strother= substr($strother, 0, strlen($strother) - 1) . "]";
                $otherdata = json_decode($strother, true);
                $other=$otherdata;
                if ($otherdata) {
                    $i=0;
                    foreach ($otherdata as $key => &$result) {
                        $result['LostWin'] =FormatMoney(isset($result['win']) ? $result['win'] : $result['lost']);
                        $baozi =[];
                        $strdianshu='-';
                        $strbaozi='-';
                        for ($j = 1; $j <= 11; $j++) {
                            if ($j > 2 && isset($result['bet']["点数$j"])) {
                                if($result['bet']["点数$j"]>0){
                                    $strdianshu.= lang('点数').$j.':'.FormatMoney(isset($result['bet']["点数$j"]) ? $result['bet']["点数$j"] : '');
                                    $strdianshu.='<br/>';
                                }
                                unset($result['bet']["点数$j"]);
                            }
                            if (isset($result['bet']["豹子$j"])) {
                                $strbaozi.='<image src="/public/static/saizi/' . $j . '.png" widht="25" height="50">:'. FormatMoney(isset($result['bet']["豹子$j"]) ? $result['bet']["豹子$j"] : 0);
                                $strbaozi.='<br/>';
                                unset($result['bet']["豹子$j"]);
                            }
                        }

                        $result['baozi']=$strbaozi;
                        $result['dianshu']=$strdianshu;
                        $areadown =FormatMoney(isset($result['bet']['DOWN']) ? $result['bet']['DOWN'] : 0);
                        $result['areadown']=$areadown;
                        $area_amt = isset($result['bet']['UP']) ? $result['bet']['UP'] : 0;
                        $areaup= bcdiv($area_amt,bl,2);
                        $result['areaup']=$areaup;
                        $rybz =isset($result['bet']['任意豹子']) ? $result['bet']['任意豹子'] : 0;
                        $rybaozi = bcdiv($rybz,bl,2);
                        $result['renyibaozi']=$rybaozi;
//                        $result['winarea'] = $winarea;
//                        $result['opendata'] = $opendata;
                    }
                }
                $temp = $list;
                $list=$otherdata;
                $other= $temp;
            }
            if ($roomID == 3400) {
                $data = $res['list'];
                $list = "[";
                $other = "[";
                for ($i = 0; $i < count($data); $i++) {
                    if ($data[$i]["UserId"] == "0") {   //所有数据
                        $arrange = str_replace($search, $poke, $data[$i]['GameDetail']);
                        $list .= $arrange . ",";
                    } else if ($data[$i]["UserId"] > 0) {
                        $str_userid = str_replace('}', '', $data[$i]['GameDetail']);
                        $str_userid .= ',"UserId":"' . $data[$i]["UserId"] . '"}';
                        $other .= $str_userid . ",";
                    }
                }
                //["UserId"] == "0"
                $other = json_decode(substr($other, 0, strlen($other) - 1) . "]", true);
                $list = json_decode(substr($list, 0, strlen($list) - 1) . "]", true);
                if ($list) {
                    foreach ($list as $key => &$item) {
                        $item["card"] = substr($item["card"], 0, strlen($item["card"]) - 1);
                        $item["card"] = explode(",", $item["card"]);
                        if(isset($item['aAndar']) || isset($item['Bahar'])){
                            if(count($item["card"])/2==0){
                                $item['result'] =lang('A面');
                            }
                            else{
                                $item['result'] =lang('B面');
                            }
                        }
                        else
                        {
                            $item['result'] = $item["card"][1];
                        }

                    }
                    //比例转换
                    if (bl != 1) {
                        for ($i = 0; $i < count($list); $i++) {
                            foreach ($list[$i] as $key => &$item){
                                if (gettype($item) == "integer")
                                    ConVerMoney($item);
                            }

                        }
                    }
                }
                if ($other) {
                    //比例转换
                    if (bl != 1) {
                        for ($i = 0; $i < count($other); $i++) {
                            foreach ($other[$i] as $key => &$item) {
                                $gamestatus = $item;
                                if (gettype($item) == "integer")
                                    ConVerMoney($item);
                                if($key=='GameStatus')
                                    $item = $gamestatus;
                            }
//                            $other[$i]['result'] =$list[0]['result'];
//                            $other[$i]['card'] =$list[0]['card'];
                        }
                    }
                }
                $temp =$list;
                $list= $other;
                $other= $temp;
            }
            if(in_array($roomID,[20200,20300])){
                $gamedata =$res['list'];
                $data = json_decode($gamedata[0]["GameDetail"], true);
                unset($gamedata[0]);

                if(count($gamedata)>0) {
                    foreach ($gamedata as $k => &$v) {
                        $userdetail = json_decode($v['GameDetail'], true);
                        unset($v['GameDetail']);
                        $v['chairid'] = $userdetail['player']['chairid'];
                        $v['bet'] =bcdiv($userdetail['player']['bet'],bl,2);
                        $v['winlost'] = isset($userdetail['lost'])?$userdetail['lost']:$userdetail['win'];
                        $v['winlost'] =bcdiv( $v['winlost'],bl,2);
                    }
                    unset($v);
                }
                $list =array_values($gamedata);
            } //已ok

            if($roomID==3800){
                $gamedata =$res['list'];
                $data = json_decode($gamedata[0]["GameDetail"], true);
                unset($gamedata[0]);

                $opendata= $data['card'];

                if(count($gamedata)>0) {
                    foreach ($gamedata as $k => &$v) {
                        $userdetail = json_decode($v['GameDetail'], true);
                        unset($v['GameDetail']);
                        $v['bet'] =bcdiv($userdetail['bet'][''],bl,2);
                        $v['winlost'] = isset($userdetail['lost'])?$userdetail['lost']:$userdetail['win'];
                        $v['winlost'] =bcdiv( $v['winlost'],bl,2);
                        $v['opendata'] =$opendata;
                    }
                    unset($v);
                }
                $list =array_values($gamedata);
            }  //已ok

            $res["list"] = $list;
            $res["other"] = $other;
        }
    }

    //同桌记录
    public function DiskRecord()
    {
        $UniqueId = input("UniqueId");
        $date = input("date");
        $UserID = input('UserID');
        $RoomID = intval(input("RoomID") / 100) * 100;
        $this->assign('data', ["UniqueId" => $UniqueId, "date" => $date, "UserID" => $UserID, "KindID" => $RoomID]);
        if ($this->request->isAjax()) {
            $table = "T_GameDetail_$date";
            $db = new GameOCDB();
            $result = $db->getTablePage($table, 1, 20, "And UniqueId=$UniqueId", "UserId", "ASC", "*", true);
            $this->PokeToImage($result, $RoomID, strval($UserID));
            return $this->apiJson($result);
        }
        $this->assign("bl", bl);
        return $this->fetch();
    }

    //统计
    public function TotalPackage()
    {
        if ($this->request->isAjax()) {
            $db = new GameOCDB();
            $start = input('start') ? input('start') : date('Y-m-d');//date('Y-m-d');
            $end = input('end') ? input('end') : date('Y-m-d');
            $packID = input('packID', 0);
            $where = "AND MyDate BETWEEN '$start' AND '$end' AND  PackID IN ($packID)";
            $field = "NReg,NIP,Bind,RegBindRate,WinLose,Pay,PayCount,NPay,NCount,IOS,IOSCount,Android,AndroidCount,Score,Orders,[Count],Rate";
            //加入sum合并计算
            $field = explode(',', $field);
            foreach ($field as &$value) $value = "sum($value)$value";
            unset($value);
            $sfieid = "ISNULL(sum(WinScore),0)WinScore,ISNULL(sum(DailyRunning),0)Water,ISNULL(sum(DailyEggTax),0)Tax";
            $tax = $db->getTablePage("View_PackageRoomTax", 1, 20, "AND AddTime BETWEEN '$start' AND '$end' AND  OperatorId IN ($packID)", "", "", $sfieid);
//            halt($tax);
            $result = $db->getTablePage('View_PackageInfoTotal', 1, 1000, $where, 'NReg', 'desc', implode(",", $field))['list'][0];
            $result['other'] = $tax['list'][0];
            return json($result);
        }
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        return $this->fetch();
    }

    //房间记录
    public function TotalPackageRoomRecord()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $account = input('account');
            $orderby = input('orderby');
            $orderType = input('orderType');
            $packID = input('PackID', -1);
            $date = trim(input('date')) ? trim(input('date')) : date('Y-m-d');

            //日期不能比今天大 表都没
            if (strtotime($date) > strtotime(date('Y-m-d'))) return $this->apiJson([]);

            $order = "";
            if (!empty($orderby)) $order .= "$orderby $orderType";
            else $order = "ServerID  ASC";
            $mobile = input('mobile');
            $Table = "T_UserGameChangeLogs_" . str_replace('-', '', $date);

            $where = " addDate='$date' ";
            if ($packID >= 0) $where .= " AND OperatorId IN ($packID)";
            if ($roomid > 0) $where .= " AND ServerID=$roomid";
            if ($roleid > 0) $where .= " AND A.RoleID=$roleid";
            if (!empty($account)) $where .= " AND AccountName='$account'";
            $sqlQuery = "SELECT A.*,RoomId,B.TotalTax,TotalBat,addDate,C.AccountName,C.LoginName,C.PackageName FROM(" .
                " SELECT RoleID, (ServerID/10*10)KindID, ServerID, count(1)draw,sum(money)WinMoney FROM $Table" .
                " GROUP BY  ServerID,ServerID/10*10,RoleID )A " .
                "LEFT JOIN T_UserTotalWater as B WITH (NOLOCK) on A.ServerID=B.RoomID and A.RoleID=B.RoleID " .
                "LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON  A.RoleID=C.AccountID " .
                "WHERE $where order by  $order  OFFSET " . ($page - 1) * $limit . " ROWS FETCH NEXT $limit ROWS ONLY ";
//            echo $sqlQuery;
            $coutQuery = "SELECT COUNT(1)count FROM(SELECT RoleID, (ServerID/10*10)KindID, ServerID, count(1)draw,sum(money)WinMoney FROM $Table 
            GROUP BY ServerID,ServerID/10*10,RoleID )A 
            LEFT JOIN T_UserTotalWater as B WITH (NOLOCK) on A.ServerID=B.RoomID and A.RoleID=B.RoleID
            LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON A.RoleID=C.AccountID
            WHERE $where ";
            $db = new GameOCDB($Table);
            //判断表是否存在
            if ($db->IsExistTable($Table) == 1) {
                $result['list'] = $db->getTableQuery($sqlQuery);
                $result['count'] = $db->getTableQuery($coutQuery)[0]['count'];
                $result['sql'] = $sqlQuery;
                $roomlist = $this->getRoomList();
                if (isset($result['list']) && $result['list']) {
                    foreach ($result['list'] as &$v) {
                        foreach ($roomlist as $kk => $vv) {
                            if ($vv['RoomID'] == $v['ServerID']) {
                                $v['RoomName'] = $vv['RoomName'];
                                break;
                            }
                        }
                        //盈利
                        $v['WinMoney'] = round($v['WinMoney'] / bl, 2);
                        $v['TotalTax'] = round($v['TotalTax'] / bl, 2);
                        //活跃度
                        $v['totalwater'] = round($v['TotalBat'] / bl, 2);
                        $v['date'] = $date ? $date : date("Y-m-d");

                    }
                    unset($v);
                }
            }


            $result['sql'] = $sqlQuery;
            return $this->apiJson($result, true);


        }
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    //玩家游戏每日输赢
    public function TotalPackageWinlose()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = input('orderby');
            $orderType = input('orderType');

            $date = trim(input('strartdate')) ? trim(input('strartdate')) : date('Y-m-d');
            //日期不能比今天大 表都没
            if (strtotime($date) > strtotime(date('Y-m-d'))) return $this->apiJson([]);
//            $date='2021-05-08';
            $where = " AND AddTime='$date' ";
            $packID = input('PackID', -1);
            if ($packID >= 0) $where .= " AND OperatorId IN ($packID)";
            if ($roomid > 0) $where .= " AND RoomId=$roomid";

            $db = new GameOCDB();
            //判断表是否存在
            $result = $db->getTablePage("View_PackageRoomTax", $page, $limit, $where, $orderby, $orderType, "*", true);
            $roomlist = $this->getRoomList();
            $total_Water = 0;
            $total_Win = 0;
            $total_Tax = 0;
            $total_WinRate = 0;
            if (isset($result['list']) && $result['list']) {
                foreach ($result['list'] as &$v) {
                    foreach ($roomlist as $kk => $vv) {
                        if ($vv['RoomID'] == $v['RoomId']) {
                            $v['RoomName'] = $vv['RoomName'];
                            break;
                        }
                    }
                    //回报率
                    $v['GameRate'] = sprintf("%.2f", (($v['DailyRunning'] - $v['WinScore']) / $v['DailyRunning']) * 100);
                    $v['WinRate'] = sprintf("%.2f", ($v['WinNum'] / $v['TotalNum']) * 100);
                    $total_Water += $v['DailyRunning'];
                    $total_Win += $v['WinScore'];
                    $total_Tax += $v['DailyEggTax'];
                    $total_WinRate = sprintf("%.2f", (($total_Water - $total_Win) / $total_Water) * 100);
                }
                foreach ($result['list'] as &$v) {
                    $v["percent"] = ($v['WinScore'] + $v['DailyEggTax']) * 100 / $total_Win;
                }
                unset($v);
            }

//             halt($result);
            $result['other'] = ["total_Water" => $total_Water,
                "total_Win" => $total_Win,
                "total_Tax" => $total_Tax,
                "total_WinRate" => $total_WinRate
            ];

            return $this->apiJson($result);
        }
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function TotalPackageWlc()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $orderType = input('orderType');
            $date = trim(input('strartdate')) ? trim(input('strartdate')) : '';
            $kindid = trim(input('kindid')) ? trim(input('kindid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $packID = input('PackID', -1);
            $where = " AND datediff(d,AddTime,'$date')=0 ";
            if ($kindid > 0) $where .= " AND KindID=$kindid";
            if ($roomid > 0) $where .= " AND RoomID=$roomid";
            if ($packID >= 0) $where .= " AND OperatorId IN ($packID)";
            $db = new GameOCDB();
            $result = $db->getTablePage("View_PackageRoomTax", $page, $limit, $where, $orderby, $orderType, "*,(winscore+DailyEggTax) as killpoint");

            if (isset($result['list']) && $result['list']) {
                foreach ($result['list'] as &$v) {
                    //盈利 sprintf("%.2f",$num);
                    $v['totalwater'] = round($v['DailyRunning'], 2);
                    $v['totalwin'] = round($v['WinScore'], 2);
                    $v['blacktax'] = round($v['DailyEggTax'], 2);
                    $v['AddTime'] = date('Y-m-d', strtotime($v['AddTime']));
                    $v['percent2'] = $v['TotalNum'] > 0 ? round($v['WinNum'] / $v['TotalNum'] * 100, 2) : 0;
                    $v['percent2'] .= '%';
                    //活跃度
                }
                unset($v);
            }
            return $this->apiJson($result);

        }
        $this->assign('PackID', $this->GetPackageList());
        $this->assign('PackIDS', session('PackID'));
        $this->assign('kindlist', $this->GetKindList());
        $this->assign('selectData', $this->getRoomList());
        return $this->fetch();
    }


}