<?php

namespace app\admin\controller;


use app\model\AccountDB;
use app\model\UserDB;
use app\model\GameOCDB;
use think\response\Json;
use socket\QuerySocket;
use socket\sendQuery;

/**
 * 支付通道
 */
class Ranke extends Main
{ 
    /**
     *  金币排行
     * @return View |    Json
     */
    public function coin()
    {
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $data =$db->GetGoldRanklist();
                $socket = new QuerySocket();
                foreach ($data['list'] as $k=>&$v){
                    $userbanlance = $socket->DSQueryRoleBalance($v['AccountID']);
                    $v['CashAble'] =0;
                    if(!empty($userbanlance)){
                        $v['iGameWealth'] = $userbanlance['iGameWealth'];
                        $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                        $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                        $v['iCurWaged'] = $userbanlance['iCurWaged'];
                        $v['CashAble'] = bcsub($v['iGameWealth'],$v['iFreezonMoney'],2);
                    }
                    else{
                        $v['iGameWealth'] =0;
                        $v['iFreezonMoney']=0;
                        $v['iNeedWaged'] =0;
                        $v['iCurWaged']=0;
                    }
                    ConVerMoney($v['CashAble']);
                    ConVerMoney($v['TotalWeath']);
                    ConVerMoney($v['iGameWealth']);
                    ConVerMoney($v['iFreezonMoney']);
                    ConVerMoney($v['iNeedWaged']);
                    ConVerMoney($v['iCurWaged']);
                    unset($v);
                }
                return $this->apiJson($data);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $db = new UserDB();
                $result = $db->GetGoldRanklist();

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
                        lang('玩家ID') => 'integer',
                        lang('渠道ID') => 'string',
                        lang('注册时间') => 'string',
                        lang('携带金币') => 'string',
                        lang('总和') => 'string',
                        lang('总充值') => 'string',
                        lang('总提现') => 'string',
                        lang('冻结金额') => 'string',
                        lang('可提金额') => 'string',
                        lang('当前完成打码') => 'string',
                        lang('打码任务') => 'string',
                    ];
                    $filename = '金币排行-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    $socket = new QuerySocket();
                    foreach ($rows as $index => &$row) {
                        $v = &$row;
                        $userbanlance = $socket->DSQueryRoleBalance($v['AccountID']);
                        $v['CashAble'] =0;
                        if(!empty($userbanlance)){
                            $v['iGameWealth'] = $userbanlance['iGameWealth'];
                            $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                            $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                            $v['iCurWaged'] = $userbanlance['iCurWaged'];
                            $v['CashAble'] = bcsub($v['iGameWealth'],$v['iFreezonMoney'],2);
                        }
                        else{
                            $v['iGameWealth'] =0;
                            $v['iFreezonMoney']=0;
                            $v['iNeedWaged'] =0;
                            $v['iCurWaged'];
                        }
                        ConVerMoney($v['CashAble']);
                        ConVerMoney($v['TotalWeath']);
                        ConVerMoney($v['iGameWealth']);
                        ConVerMoney($v['iFreezonMoney']);
                        ConVerMoney($v['iNeedWaged']);
                        ConVerMoney($v['iCurWaged']);
                        $item = [
                            $row['AccountID'],
                            $row['OperatorId'],
                            $row['RegisterTime'],
                            $row['iGameWealth'],
                            $row['iGameWealth'],
                            $row['TotalDeposit'],
                            $row['TotalRollOut'],
                            $row['iFreezonMoney'],
                            $row['CashAble'],
                            $row['iCurWaged'],
                            $row['iNeedWaged'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }

        return $this->fetch();
    }


    ///打码任务排行
    public function wageTaskList()
    {
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();

                $isonline = (int)input('isonline', 1);
                if ($isonline  == 1 && config('is_dm_online')==1) {
                    $data = $this->sendGameMessage('CMD_MD_ONLINE_WAGED_MANAGE', [], "DC",'QueryOnlineWaged');
                    $data =$db->GetGoldRanklist_new($data);
                } else {
                    $data =$db->GetGoldRanklist();
                    $socket = new QuerySocket();
                }
                

                $pggameid = [
                    60,65,74,87,89,48,53,54,71,75
                ];
                foreach ($data['list'] as $k=>&$v){
                    if ($isonline  ==  2 || config('is_dm_online')!=1) {
                        $userbanlance =$socket->DSQueryRoleBalance($v['AccountID']);
                        $v['CashAble'] =0;
                        if(!empty($userbanlance)){
                            $v['iGameWealth'] = $userbanlance['iGameWealth'];
                            $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                            $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                            $v['iCurWaged'] = $userbanlance['iCurWaged'];
                            $v['CashAble'] = bcsub($v['iGameWealth'],$v['iFreezonMoney'],2);
                        }
                        else{
                            $v['iGameWealth'] =0;
                            $v['iFreezonMoney']=0;
                            $v['iNeedWaged'] =0;
                            $v['iCurWaged']=0;
                        }
                        ConVerMoney($v['CashAble']);
                        ConVerMoney($v['TotalWeath']);
                        ConVerMoney($v['iGameWealth']);
                        ConVerMoney($v['iFreezonMoney']);
                        ConVerMoney($v['iNeedWaged']);
                        ConVerMoney($v['iCurWaged']);

                        $v['percentage'] = $v['percentage']*100 .'%';
                        if ($v['iCurWaged'] == 0 || $v['iNeedWaged'] == 0){
                            $v['percentage'] = '0%';
                        }else{
                            $v['percentage'] = bcmul(bcdiv( $v['iCurWaged'] , $v['iNeedWaged'],6),100,2).'%';
                        }
                    }
                    //获取pg链接
                    $gameidkey = rand(0,9);
                    $gameid = $pggameid[$gameidkey];
                    $v['pg_link'] = config('pggame.GAME_URL').'/'.$gameid.'/index.html?btt=1&ot='.config('pggame.Operator_Token').'&l=en&ops='.$this->encry(config('platform_name').'_'.$v['AccountID']);
                    unset($v);
                }
                return $this->apiJson($data);
            case 'exec':
                //权限验证
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $db = new UserDB();
                $result = $db->GetGoldRanklist();

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
                        lang('玩家ID') => 'integer',
                        lang('渠道ID') => 'string',
                        lang('注册时间') => 'string',
                        lang('携带金币') => 'string',
                        lang('总和') => 'string',
                        lang('总充值') => 'string',
                        lang('总提现') => 'string',
                        lang('冻结金额') => 'string',
                        lang('可提金额') => 'string',
                        lang('当前完成打码') => 'string',
                        lang('打码任务') => 'string',
                    ];
                    $filename = '金币排行-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    $socket = new QuerySocket();
                    foreach ($rows as $index => &$row) {
                        $v = &$row;
                        $userbanlance = $socket->DSQueryRoleBalance($v['AccountID']);
                        $v['CashAble'] =0;
                        if(!empty($userbanlance)){
                            $v['iGameWealth'] = $userbanlance['iGameWealth'];
                            $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                            $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                            $v['iCurWaged'] = $userbanlance['iCurWaged'];
                            $v['CashAble'] = bcsub($v['iGameWealth'],$v['iFreezonMoney'],2);
                        }
                        else{
                            $v['iGameWealth'] =0;
                            $v['iFreezonMoney']=0;
                            $v['iNeedWaged'] =0;
                            $v['iCurWaged'];
                        }
                        ConVerMoney($v['CashAble']);
                        ConVerMoney($v['TotalWeath']);
                        ConVerMoney($v['iGameWealth']);
                        ConVerMoney($v['iFreezonMoney']);
                        ConVerMoney($v['iNeedWaged']);
                        ConVerMoney($v['iCurWaged']);
                        if ($v['iNeedWaged'] <= $v['iCurWaged']) {
                            $v['iCurWaged'] = $v['iNeedWaged'] = '0.00';
                        }
                        $item = [
                            $row['AccountID'],
                            $row['OperatorId'],
                            $row['RegisterTime'],
                            $row['iGameWealth'],
                            $row['iGameWealth'],
                            $row['TotalDeposit'],
                            $row['TotalRollOut'],
                            $row['iFreezonMoney'],
                            $row['CashAble'],
                            $row['iCurWaged'],
                            $row['iNeedWaged'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }

        return $this->fetch();
    }


    public function wageClear(){
        $accountId = $this->request->param('AccountID/a');
        $auth_ids = $this->getAuthIds();
        if (!in_array(10004, $auth_ids)) {
            return $this->apiReturn(2, [], '没有权限');
        }
        $success_num =0;
        $faild_num =0;
        if(empty($accountId)){
            return $this->apiReturn(2, [], '参数有误');
        }
        foreach ($accountId as $k => $v) {
            $data = $this->sendGameMessage('CMD_MD_SET_WAGED', [$v, 3, 0], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                $comment = '打码清零';
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $v,
                    'adminid' => session('userid'),
                    'type' => 2,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
                $success_num++;
            } else {
                $faild_num++;
            }
        }
        $str_msg ='处理成功'.$success_num.',失败：'.$faild_num;
        return $this->successJSON($str_msg);
    }

    /**
     * 战绩排行
     * @return View|Json
     */
    public function winrank()
    {
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $result = $db->GetWinScoreRankList();
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $db = new UserDB();
                $result = $db->GetWinScoreRankList();
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
                        lang('玩家ID') => 'integer',
                        lang('携带金币') => '0.00',
                        lang('总充值') => '0.00',
                        lang('总转出') => '0.00',
                        lang('战绩') => '0.00',
                    ];
                    $filename = lang('战绩排行').'-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['AccountID'],
                            $row['Money'],
                            $row['TotalDeposit'],
                            $row['TotalRollOut'],
                            $row['total'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }

        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }


    //加密
    private function encry($str,$key='pggme'){
        $str = trim($str);
        return think_encrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }

}
