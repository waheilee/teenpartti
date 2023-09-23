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
                $data =$db->GetGoldRanklist();
                $socket = new QuerySocket();
                $orderby = input('orderby', "Money");
                $ordertype = input('ordertype', 'desc');
                foreach ($data['list'] as $k=>&$v){

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
                    if ($v['iCurWaged'] == 0 || $v['iNeedWaged'] == 0){
                        $percentage = '0%';
                    }else{
                        $percentage = bcmul(bcdiv( $v['iCurWaged'] , $v['iNeedWaged'],2),100,2).'%';
                    }
                    $v['CompletionProgress'] = $percentage;
//                    $v['CtrlRatio'] = '';
//
//                    if ($v['win_dmrateset'] && $v['win_dmrateset'] != 100){
//                        $v['CtrlRatio'] = "赢：".$v['win_dmrateset'].'%';
//                    }
//                    if ($v['lose_dmrateset'] && $v['lose_dmrateset'] != 100){
//                        $v['CtrlRatio'] = $v['CtrlRatio'].PHP_EOL.'输：'. $v['lose_dmrateset'].'%';
//                    }
                    $v['CtrlRatio'] = "赢：".$v['win_dmrateset'] ?? 0 .",输：". $v['lose_dmrateset'] ?? 0;
                    unset($v);
                }
                $sortType = $ordertype == 'desc' ? SORT_DESC : SORT_ASC;
                if ($orderby == 'CtrlRatio'){
                    $CompletionProgress = array_column($data['list'],'win_dmrateset');
                    array_multisort($CompletionProgress,$sortType,$data['list']);
                }
                if ($orderby == 'CompletionProgress'){

                    $CompletionProgress = array_column($data['list'],'CompletionProgress');
                    array_multisort($CompletionProgress,$sortType,$data['list']);
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

}
