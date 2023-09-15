<?php

namespace app\business\controller;

use app\common\Api;
use app\common\GameLog;
use app\model;
use app\model\CommonModel;
use app\model\GameOCDB;
use app\model\MasterDB;
use app\model\UserDB;
use app\model\PayNotifyLog;
use redis\Redis;
use socket\QuerySocket;
use socket\sendQuery;
use think\Cache;
use think\Exception;
use function Sodium\add;
use app\model\User as userModel;

class Charge extends Main
{
    //通道订单管理
    public function channelPayOrder()
    {
        switch (input('Action')) {
            case 'list':
                $db = new  UserDB();
                $result = $db->GetChannelPayOrderList();
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $db = new UserDB();
                $result = $db->GetChannelPayOrderList();
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
                        lang('序号') => 'integer',
                        lang('订单状态') => 'string',
                        lang('平台订单号') => 'string',
                        lang('订单时间') => 'datetime',
                        lang('支付时间') => 'datetime',
                        lang('玩家ID') => 'integer',
                        lang('充值金额') => '0.00',
                        lang('到账金币') => '0.00',
                        lang('通道名称') => 'string',
                        lang('充值类型') => 'string',
                        lang('玩家设备ID') => 'string',
                    ];
                    $filename = lang('订单管理').'-'.date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['Id'],
                            $row['Status'],
                            $row['OrderId'],
                            $row['AddTime'],
                            $row['PayTime'],
                            $row['AccountID'],
                            $row['RealMoney'],
                            $row['BaseGoodsValue'],
                            $row['ChannelName'],
                            $row['PayType'],
                            $row['MachineCode'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }
        $MasterDB = new \app\model\MasterDB();
        $channel = $MasterDB->DBQuery($MasterDB::TABLE_GAME_PAY_CHANNEL, '', ' where Type=0 ');
        $this->assign('channeInfo', $channel);
        return $this->fetch();
    }
}
