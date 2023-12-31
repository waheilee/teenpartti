<?php

namespace app\merchant\controller;


use app\model\AccountDB;
use app\model\UserDB;
use socket\QuerySocket;
use think\response\Json;


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
                $data = $db->GetGoldRanklist();
                $socket = new QuerySocket();
                foreach ($data['list'] as $k => &$v) {
                    $userbanlance = $socket->DSQueryRoleBalance($v['AccountID']);
                    $v['CashAble'] = 0;
                    if (!empty($userbanlance)) {
                        $v['iGameWealth'] = $userbanlance['iGameWealth'];
                        $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                        $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                        $v['iCurWaged'] = $userbanlance['iCurWaged'];
                        $v['CashAble'] = bcsub($v['iGameWealth'], $v['iFreezonMoney'], 2);
                    } else {
                        $v['iGameWealth'] = 0;
                        $v['iFreezonMoney'] = 0;
                        $v['iNeedWaged'] = 0;
                        $v['iCurWaged'] = 0;
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
//                return $this->apiJson($db->GetGoldRanklist());
            case 'exec':
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
                        lang('携带金币') => '0.00',
                    ];
                    $filename = '金币排行-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['AccountID'],
                            $row['Money'],
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
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
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
