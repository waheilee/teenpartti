<?php

namespace app\admin\controller;

use app\admin\controller\traits\getSocketRoom;
use app\common\Api;
use app\model\CommonModel;
use app\model\ThirdGameReport;

class ThirdGame extends Main
{


    public function index()
    {
        switch (input('Action')) {
            case 'list':
                $thridgameM = new ThirdGameReport();
                $result = $thridgameM->GetGameReport();
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $thridgameM = new ThirdGameReport();
                $result = $thridgameM->GetGameReport();
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('ID') => 'integer',
                        lang('玩家ID') => 'integer',
                        lang('游戏类型') => "string",
                        lang('上分') => "0.00",
                        lang('下分') => "0.00",
                        lang('用户盈亏') => '0.00',
                        lang('订单号') => 'string',
                        lang('添加时间') => 'date',
                        lang('更新时间') => 'date',
                    ];
                    $filename = lang('API游戏统计').'-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {

                        $item = [
                            $row['Id'],
                            $row['RoleId'],
                            $row['GameName'],
                            $row['Deposit'],
                            $row['PayOut'],
                            $row['Profit'],
                            $row['OrderId'],
                            $row['AddTime'],
                            $row['UpdateTime'],
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


}