<?php

namespace app\admin\controller;

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
    /**
     * 充值汇总
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;

            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'page' => $page,
                'pagesize' => $limit
            ], 'charge', 'paysum');

            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }

        return $this->fetch();
    }


    /**
     * Notes:转账明细查询
     */
    public function transfer()
    {
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $orderno = input('orderno') ? input('orderno') : '';
            $channelid = intval(input('classid')) ? intval(input('classid')) : 0;
            $start = input('start') ? input('start') : '';
            $end = input('end') ? input('end') : '';
            $status = intval(input('status')) >= 0 ? intval(input('status')) : -1;

            $amount = intval(input('amount')) ? intval(input('amount')) : 0;
            if ($amount > 0) {
                $amount = '0-' . $amount;
            }

            $data = [
                'page' => $page,
                'pagesize' => $limit,
                'roleid' => $roleId,
                'orderno' => $orderno,
                'strartdate' => $start,
                'enddate' => $end,
                'transactionid' => '',
                'status' => $status,
                'classid' => $channelid,
                'isoffline' => 1,
                'amount' => $amount
            ];
            $res = Api::getInstance()->sendRequest($data, 'payment', 'payorder');
            return $this->apiReturn($res['code'], isset($res['data']['ResultData']['list']) ? $res['data']['ResultData']['list'] : [], $res['message'], isset($res['data']['total']) ? $res['data']['total'] : 0, isset($res['data']['ResultData']['sum']) ? $res['data']['ResultData']['sum'] : []);
        }
        return $this->fetch();
    }


    /**
     * Notes:推广充值记录
     */
    public function spreadlist()
    {
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;

            $data = [
                'page' => $page,
                'pagesize' => $limit,
                'roleid' => $roleId,

            ];
            $res = Api::getInstance()->sendRequest($data, 'charge', 'spreadlist');


            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    $v['amount'] = $v['amount'] / 1000;
                }
                unset($v);
            }
//            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
            return $this->apiReturn($res['code'],
                isset($res['data']['list']) ? $res['data']['list'] : [],
                $res['message'], $res['total'],
                isset($res['data']['total']) ? $res['data']['total'] : []);
        }

        return $this->fetch();
    }


    /**
     * Notes:充值明细查询
     */
    public function recharge()
    {
        if ($this->request->isAjax()) {
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $orderno = input('orderno') ? input('orderno') : '';
            $transactionid = input('transactionid') ? input('transactionid') : '';
            $channelid = intval(input('channelid')) ? intval(input('channelid')) : 0;
            $classid = intval(input('classid')) ? intval(input('classid')) : 0;
            $status = intval(input('status')) >= 0 ? intval(input('status')) : -1;
            $isoffline = 0;
            $amount = intval(input('amount')) ? intval(input('amount')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            if ($amount > 0) {
                $amount = '0-' . $amount;
            }

            $res = Api::getInstance()->sendRequest([
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'roleid' => $roleId,
                'orderno' => $orderno,
                'transactionid' => $transactionid,
                'channelid' => $channelid,
                'classid' => $classid,
                'status' => $status,
                'isoffline' => $isoffline,
                'amount' => $amount,
                'page' => $page,
                'pagesize' => $limit
            ], 'payment', 'payorder');
            return $this->apiReturn(
                $res['code'],
                isset($res['data']['ResultData']['list']) ? $res['data']['ResultData']['list'] : [],
                $res['message'],
                isset($res['data']['total']) ? $res['data']['total'] : 0,
                isset($res['data']['ResultData']['sum']) ? $res['data']['ResultData']['sum'] : []
            );
        }

        $channel = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 1000], 'payment', 'channel');
        $class = config('site.zf_class');
        $this->assign('channel', $channel['data']);
        $this->assign('class', $class);
        return $this->fetch();
    }

    //商品管理
    public function ShopManager()
    {
        $bl = bl;
        $db = new MasterDB();
        $db->TShopItem();
        $request = request()->request();
        switch (input('Action')) {
            case 'list': //view
                if ($bl > 1) $field = "CONVERT(DECIMAL ( 18, 2 ),BaseGoodsValue * 1.0 / $bl  ) BaseGoodsValue,RealMoney,ID,CountryCode,CONVERT(DECIMAL ( 18, 2 ),FirstChargeAward* 1.0 / $bl) FirstChargeAward, CONVERT(DECIMAL(18, 2),ExtraAward* 1.0 / $bl) ExtraAward,CONVERT(DECIMAL ( 18, 2 ),parentAward * 1.0 / $bl  ) as parentAward,CONVERT(DECIMAL(18, 2),Vip1GoodsValue * 1.0 / $bl  ) as Vip1GoodsValue,CONVERT(DECIMAL(18, 2),Vip2GoodsValue * 1.0 / $bl  ) as Vip2GoodsValue,CONVERT(DECIMAL(18, 2),Vip3GoodsValue * 1.0 / $bl  ) as Vip3GoodsValue,CONVERT(DECIMAL(18, 2),Vip4GoodsValue * 1.0 / $bl  ) as Vip4GoodsValue,CONVERT(DECIMAL(18, 2),Vip5GoodsValue * 1.0 / $bl  ) as Vip5GoodsValue,CONVERT(DECIMAL(18, 2),Vip6GoodsValue * 1.0 / $bl  ) as Vip6GoodsValue,CONVERT(DECIMAL(18, 2),Vip7GoodsValue * 1.0 / $bl  ) as Vip7GoodsValue,CONVERT(DECIMAL(18, 2),Vip8GoodsValue * 1.0 / $bl  ) as Vip8GoodsValue,CONVERT(DECIMAL(18, 2),Vip9GoodsValue * 1.0 / $bl  ) as Vip9GoodsValue,CONVERT(DECIMAL(18, 2),Vip10GoodsValue * 1.0 / $bl)  as Vip10GoodsValue,CONVERT(DECIMAL(18, 2),Vip11GoodsValue * 1.0 / $bl  ) as Vip11GoodsValue,CONVERT(DECIMAL(18, 2),Vip12GoodsValue * 1.0 / $bl  ) as Vip12GoodsValue,CONVERT(DECIMAL(18, 2),Vip13GoodsValue * 1.0 / $bl  ) as Vip13GoodsValue,CONVERT(DECIMAL(18, 2),Vip14GoodsValue * 1.0 / $bl  ) as Vip14GoodsValue
,CONVERT(DECIMAL(18, 2),Vip15GoodsValue * 1.0 / $bl  ) as Vip15GoodsValue,CONVERT(DECIMAL(18, 2),Vip16GoodsValue * 1.0 / $bl  ) as Vip16GoodsValue,CONVERT(DECIMAL(18, 2),Vip17GoodsValue * 1.0 / $bl  ) as Vip17GoodsValue,CONVERT(DECIMAL(18, 2),Vip18GoodsValue * 1.0 / $bl  ) as Vip18GoodsValue,CONVERT(DECIMAL(18, 2),Vip19GoodsValue * 1.0 / $bl  ) as Vip19GoodsValue,CONVERT(DECIMAL(18, 2),Vip20GoodsValue * 1.0 / $bl  ) as Vip20GoodsValue,DayFirstCharge ";
                else         $field = 'BaseGoodsValue,RealMoney,ID,CountryCode,FirstChargeAward,ExtraAward,parentAward,Vip1GoodsValue,Vip2GoodsValue,Vip3GoodsValue,Vip4GoodsValue,Vip5GoodsValue,Vip6GoodsValue,Vip7GoodsValue,Vip8GoodsValue,Vip9GoodsValue,Vip10GoodsValue,Vip11GoodsValue,Vip12GoodsValue,Vip13GoodsValue,Vip14GoodsValue,Vip15GoodsValue,Vip16GoodsValue,Vip17GoodsValue,Vip18GoodsValue,Vip19GoodsValue,Vip20GoodsValue,DayFirstCharge';
                $result = $db->GetPage("", "RealMoney ASC", $field);
//                $result = $db->getTablePage($table, input('page'), input('limit'), "", "ID", 'asc', $field);
                return $this->apiJson($result);
                break;
            case  'add':
                if (request()->isGet()) { // view
                    $this->assign('action', 'add');
                    $this->assign('info', ['BaseGoodsValue' => 0, 'ExtraAward'=>0,'RealMoney' => 0, 'FirstChargeAward'=>0,'DayFirstCharge'=>0,'ID' => 0, 'CountryCode' => '','parentAward'=>0,'VipAward'=>'0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0']);
//                    $countrycode =new model\CountryCode();
//                    $result = $countrycode->getListAll([],'','sortid');
//                    $this->assign('country',$result);
                    return $this->fetch('shop_manager_item');
                }
                //ajax add
                $countrycode ='';//$request['CountryCode'];
//                if($countrycode=='-1')
//                    $countrycode='';

                $data = ['BaseGoodsValue' => (int)$request['BaseGoodsValue'] * $bl, 'RealMoney' => (int)$request['RealMoney'], 'CountryCode'=>$countrycode,'FirstChargeAward'=>$request['FirstChargeAward'] * $bl,'ExtraAward'=>$request['ExtraAward']* $bl,'parentAward'=>$request['parentAward'] * $bl,'DayFirstCharge'=>$request['DayFirstCharge']];

                $vipgood = explode(',',$request['VipAward']);

                foreach ($vipgood as $item=>$v) {
                    $key = 'Vip'.($item+1).'GoodsValue';
                    $data[$key] =intval(floatval($v)*1000);
                }
                unset($data['VipAward']);
                $row = $db->Insert($data);
                GameLog::logData(__METHOD__, ['操作' => '添加', '金币' => input('BaseGoodsValue'), '金额' => input('RealMoney')], 1, $row == 1 ? lang('添加成功') : lang('添加失败'));
                if ($row > 0) return $this->success("添加成功");
                return $this->error("添加失败");
                break;
            case 'edit':
                $id = input('ID');
                $data = ['BaseGoodsValue' => $request['BaseGoodsValue'], 'RealMoney' => floatval($request['RealMoney']),'FirstChargeAward'=>floatval($request['FirstChargeAward']),'ExtraAward'=>floatval($request['ExtraAward']), 'parentAward'=>floatval($request['parentAward']),'ID' => $id, 'CountryCode' => '','VipAward'=>$request['VipAward'],'DayFirstCharge'=>$request['DayFirstCharge']];
                if (request()->isGet()) { // view
                    $this->assign('info', $data);
                    $this->assign('action', 'edit');
                    $countrycode =new model\CountryCode();
                    $result = $countrycode->getListAll([],'','sortid');
                    $this->assign('country',$result);
                    return $this->fetch('shop_manager_item');
                }
                //ajax update

                $data['BaseGoodsValue'] *= $bl;
                $data['ExtraAward'] *= $bl;
                $data['FirstChargeAward'] *= $bl;
                $data['parentAward'] *= $bl;
                if($data['CountryCode']==-1)
                    $data['CountryCode'] ='';

                $vipgood = explode(',',$request['VipAward']);
                foreach ($vipgood as $item=>$v) {
                    $key = 'Vip'.($item+1).'GoodsValue';
                    $data[$key] =intval(floatval($v)*1000);
                }
                unset($data['ID']);
                unset($data['VipAward']);
                $row =$db->UPData($data, "ID=$id");
                GameLog::logData(__METHOD__, ['操作' => '修改', '金币' => input('BaseGoodsValue'), '金额' => input('RealMoney')], 1, $row == 1 ? lang('添加成功') : lang('添加失败'));
                if ($row > 0) return $this->success("更新成功");
                return $this->error("更新失败");
                break;
            case  'del':
                $id = input('ID');
                $row = $db->DeleteRow("ID=$id");
                GameLog::logData(__METHOD__, ['操作' => '删除', '金币' => input('Goods'), '金额' => input('Money')], 1, $row == 1 ? '删除成功' : '删除失败');
                if ($row > 0) return $this->success("删除成功");
                return $this->error("删除失败");
                break;
        }
        return $this->fetch();
    }




    //竖版商品管理
    public function VerShopManager()
    {
        $bl = bl;
        $db = new MasterDB();
        $db->TShopItem();
        $request = request()->request();
        switch (input('Action')) {
            case 'list': //view
                if ($bl > 1) $field = "CONVERT(DECIMAL ( 18, 2 ),BaseGoodsValue * 1.0 / $bl  ) BaseGoodsValue,RealMoney,ID,CountryCode,CONVERT(DECIMAL ( 18, 2 ),FirstChargeAward* 1.0 / $bl) FirstChargeAward, CONVERT(DECIMAL(18, 2),ExtraAward* 1.0 / $bl) ExtraAward,CONVERT(DECIMAL ( 18, 2 ),parentAward * 1.0 / $bl  ) as parentAward,DayFirstCharge ";
                else         $field = 'BaseGoodsValue,RealMoney,ID,CountryCode,FirstChargeAward,ExtraAward,parentAward,Vip1GoodsValue,Vip2GoodsValue,Vip3GoodsValue,Vip4GoodsValue,Vip5GoodsValue,Vip6GoodsValue,Vip7GoodsValue,Vip8GoodsValue,Vip9GoodsValue,Vip10GoodsValue,Vip11GoodsValue,Vip12GoodsValue,Vip13GoodsValue,Vip14GoodsValue,Vip15GoodsValue,Vip16GoodsValue,Vip17GoodsValue,Vip18GoodsValue,Vip19GoodsValue,Vip20GoodsValue,DayFirstCharge';
                $result = $db->GetPage("", "RealMoney ASC", $field);
//                $result = $db->getTablePage($table, input('page'), input('limit'), "", "ID", 'asc', $field);
                return $this->apiJson($result);
                break;
            case  'add':
                if (request()->isGet()) { // view
                    $this->assign('action', 'add');
                    if(config('is_portrait')==1){
                        $this->assign('info', ['BaseGoodsValue' => 0, 'ExtraAward'=>0,'RealMoney' => 0, 'FirstChargeAward'=>0,'DayFirstCharge'=>0,'ID' => 0, 'CountryCode' => '','parentAward'=>0]);
                    }
                    else{
                        $this->assign('info', ['BaseGoodsValue' => 0, 'ExtraAward'=>0,'RealMoney' => 0, 'FirstChargeAward'=>0,'DayFirstCharge'=>0,'ID' => 0, 'CountryCode' => '','parentAward'=>0,'VipAward'=>'0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0']);
                    }

//                    $countrycode =new model\CountryCode();
//                    $result = $countrycode->getListAll([],'','sortid');
//                    $this->assign('country',$result);
                    return $this->fetch('ver_shop_manager_item');
                }
                //ajax add
                $countrycode ='';//$request['CountryCode'];
//                if($countrycode=='-1')
//                    $countrycode='';
                $data=[];
                if(config('is_portrait')==1){
                    $data = ['BaseGoodsValue' => (int)$request['BaseGoodsValue'] * $bl, 'RealMoney' => (int)$request['RealMoney'], 'CountryCode'=>$countrycode,'FirstChargeAward'=>$request['FirstChargeAward'] * $bl,'ExtraAward'=>$request['ExtraAward']* $bl,'parentAward'=>$request['parentAward'] * $bl,'DayFirstCharge'=>$request['DayFirstCharge']];
                }
                else{
                    $data = ['BaseGoodsValue' => (int)$request['BaseGoodsValue'] * $bl, 'RealMoney' => (int)$request['RealMoney'], 'CountryCode'=>$countrycode,'FirstChargeAward'=>$request['FirstChargeAward'] * $bl,'ExtraAward'=>$request['ExtraAward']* $bl,'parentAward'=>$request['parentAward'] * $bl,'DayFirstCharge'=>$request['DayFirstCharge']];
                    $vipgood = explode(',',$request['VipAward']);
                    foreach ($vipgood as $item=>$v) {
                        $key = 'Vip'.($item+1).'GoodsValue';
                        $data[$key] =intval(floatval($v)*1000);
                    }
                    unset($data['VipAward']);
                }
                $row = $db->Insert($data);
                GameLog::logData(__METHOD__, ['操作' => '添加', '金币' => input('BaseGoodsValue'), '金额' => input('RealMoney')], 1, $row == 1 ? lang('添加成功') : lang('添加失败'));
                if ($row > 0) return $this->success("添加成功");
                return $this->error("添加失败");
                break;
            case 'edit':
                $id = input('ID');
                $data = ['BaseGoodsValue' => $request['BaseGoodsValue'], 'RealMoney' => floatval($request['RealMoney']),'FirstChargeAward'=>floatval($request['FirstChargeAward']),'ExtraAward'=>floatval($request['ExtraAward']), 'parentAward'=>floatval($request['parentAward']),'ID' => $id, 'CountryCode' => '','DayFirstCharge'=>$request['DayFirstCharge']];
                if (request()->isGet()) { // view
                    $this->assign('info', $data);
                    $this->assign('action', 'edit');
                    $countrycode =new model\CountryCode();
                    $result = $countrycode->getListAll([],'','sortid');
                    $this->assign('country',$result);
                    return $this->fetch('ver_shop_manager_item');
                }
                //ajax update

                $data['BaseGoodsValue'] *= $bl;
                $data['ExtraAward'] *= $bl;
                $data['FirstChargeAward'] *= $bl;
                $data['parentAward'] *= $bl;
                if($data['CountryCode']==-1)
                    $data['CountryCode'] ='';

//                $vipgood = explode(',',$request['VipAward']);
//                foreach ($vipgood as $item=>$v) {
//                    $key = 'Vip'.($item+1).'GoodsValue';
//                    $data[$key] =intval(floatval($v)*1000);
//                }
                unset($data['ID']);
                unset($data['VipAward']);
                $row =$db->UPData($data, "ID=$id");
                GameLog::logData(__METHOD__, ['操作' => '修改', '金币' => input('BaseGoodsValue'), '金额' => input('RealMoney')], 1, $row == 1 ? lang('添加成功') : lang('添加失败'));
                if ($row > 0) return $this->success("更新成功");
                return $this->error("更新失败");
                break;
            case  'del':
                $id = input('ID');
                $row = $db->DeleteRow("ID=$id");
                GameLog::logData(__METHOD__, ['操作' => '删除', '金币' => input('Goods'), '金额' => input('Money')], 1, $row == 1 ? '删除成功' : '删除失败');
                if ($row > 0) return $this->success("删除成功");
                return $this->error("删除失败");
                break;
        }
        return $this->fetch();
    }


    /**
     * Notes:补发
     */
    public function bufa()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $orderno = input('orderno') ? input('orderno') : '';
        if (!$orderno || !$roleId) {
            return $this->apiReturn(1, [], '订单号或玩家数据有误');
        }

        //加锁
        $key = 'lock_bufa_' . $orderno;
        if (!Redis::lock($key)) {
            return $this->apiReturn(2, [], '请勿重复操作');
        }

        //获取订单数据
        $orderInfo = Api::getInstance()->sendRequest([
            'orderno' => $orderno,
            'roleid' => $roleId
        ], 'payment', 'orderinfo');
        if ($orderInfo['code'] != 0) {
            Redis::rm($key);
            return $this->apiReturn(3, [], '获取不到订单数据');
        }
        $orderData = $orderInfo['data'];
        if ($orderData['status'] != 0) {
            Redis::rm($key);
            return $this->apiReturn(4, [], '订单不是待付款状态，不能补发');
        }

        //先更新订单状态为成功
        $updateOrder = [
            'cardtype' => $orderData['cardtype'],
            'sporderno' => $orderData['orderno'],
            'status' => 3,
            'transactionid' => $orderData['tratransactionid']
        ];
        $status = Api::getInstance()->sendRequest($updateOrder, 'payment', 'updateorder');

        if ($status['code'] == 0) {
            $socket = new QuerySocket();
//            $res = $socket->addRoleMoney($roleId, $orderData['totalfee'] * 1000);
            $res = $socket->addRoleMoney($roleId, $orderData['totalfee'] * 1000, 0, getClientIP());
            if ($res['iResult'] == 0) {

                //释放锁
                Redis::rm($key);
                GameLog::logData(__METHOD__, $orderData, 1, $res);
                return $this->apiReturn(0, [], '补发成功');
            } else {
                //回退订单状态为原始状态
                $updateOrder['status'] = 0;
                Api::getInstance()->sendRequest($updateOrder, 'payment', 'updateorder');
                GameLog::logData(__METHOD__, $orderData, 0, $res);
                Redis::rm($key);
                return $this->apiReturn(5, [], '补发失败');
            }
        }

        GameLog::logData(__METHOD__, $orderData, 0, $status);
        Redis::rm($key);
        return $this->apiReturn(6, [], '补发失败');
    }


    /**
     * Notes:补发2
     */
    public function bufa2()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $amount = intval(input('amount')) ? intval(input('amount')) : 0;
        $orderno = input('orderno') ? input('orderno') : '';
        if (!$orderno || !$roleId) {
            return $this->apiReturn(1, [], '订单号或玩家数据有误');
        }

        //加锁
        $key = 'lock_bufa_' . $orderno;
        if (!Redis::lock($key)) {
            return $this->apiReturn(2, [], '请勿重复操作');
        }
        $updateOrder = [
            'orderno' => $orderno,
            'status' => 1,
        ];
        $status = Api::getInstance()->sendRequest($updateOrder, 'charge', 'updatespreadorder');


        if ($status['code'] == 0) {
            $socket = new QuerySocket();
            $res = $socket->addRoleMoney($roleId, $amount * 1000, 0, getClientIP());
            if ($res['iResult'] == 0) {

                //释放锁
                Redis::rm($key);
                GameLog::logData(__METHOD__, $orderno, 1, $res);
                return $this->apiReturn(0, [], '补发成功');
            } else {
                //回退订单状态为原始状态
                $updateOrder['status'] = 0;
                Api::getInstance()->sendRequest($updateOrder, 'charge', 'updatespreadorder');
                GameLog::logData(__METHOD__, $orderno, 0, $res);
                Redis::rm($key);
                return $this->apiReturn(5, [], '补发失败');
            }
        }
        GameLog::logData(__METHOD__, $orderno, 0, $status);
        Redis::rm($key);
        return $this->apiReturn(6, [], '补发失败');
    }

    /**
     * Notes:银商出货记录
     */
    public function yinshang()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $param = [
                'roleid' => $roleId,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'classid' => 0,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($param, 'player', 'supercharge');

            return $this->apiReturn($res['code'], $res['data']['list'], $res['message'], $res['total'], $res['data']['sum']);
        }

        return $this->fetch();
    }

    public function yschuhuo()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $superid = input('superid') ? intval(input('superid')) : 0;
            $param = [
                'roleid' => $roleId,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'classid' => 1,
                'superid' => $superid,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($param, 'player', 'supercharge');
            $data = [];
            $sum = 0;
            $total = 0;
            if (!empty($res['data'])) {
                $data = $res['data']['list'];
                $sum = $res['data']['sum'];
                $total = $res['total'];
            }
            return $this->apiReturn($res['code'], $data, $res['message'], $total, $sum);
        }

        return $this->fetch();
    }


    public function diamonlist()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $accountname = input('accountname');
            $starttime = input('starttime');
            $endtime = input('endtime');
            $orderby = input("orderby");
            $ordertype = input("type");
            $param = [
                'roleid' => $roleId,
                'accountname' => $accountname,
                'starttime' => $starttime,
                'endtime' => $endtime,
                'orderby' => $orderby,
                'ordertype' => $ordertype,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($param, 'charge', 'diamonlist');
            $data = [];
            $total = 0;
            $list = [];
            if (!empty($res['data'])) {
                $data = $res['data'];
                $total = $res['total'];
            }

            foreach ($data as $k => $v) {
                $v['running'] = $v['running'] / 1000;
                $v['award'] = $v['award'] / 1000;
                array_push($list, $v);
            }
            return $this->apiReturn($res['code'], $list, $res['message'], $total);
        }

        return $this->fetch();
    }


    public function lotterylist()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $param = [
                'roleid' => $roleId,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($param, 'charge', 'lotterylist');
            $data = [];
            $total = 0;
            $list = [];
            if (!empty($res['data'])) {
                $data = $res['data'];
                $total = $res['total'];
            }
            $config = ['0' => '白银转盘', '1' => '黄金转盘', '2' => '钻石转盘'];
            foreach ($data as $k => $v) {
                $v['lotterytype'] = $config[$v['lotterytype']];
                $v['lotteryaward'] = $v['lotteryaward'] / 1000;
                $v['goodid'] = '金币' . $v['lotteryaward'];
                array_push($list, $v);
            }

            return $this->apiReturn($res['code'], $list, $res['message'], $total);
        }

        $this->assign('roleid', $roleId);
        return $this->fetch();
    }


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

    ///新支付订单表 CD_UserDB.T_UserTransaction
    public function orderlist()
    {
        $strFields = "Id,TransactionNo,a.AccountID,AccountName,RealMoney,VirtualGold,AddTime,PlatformType,CdyType";//
        $tableName = "CD_UserDB.dbo.T_UserTransaction a, (select AccountID,AccountName from CD_Account.dbo.T_Accounts (nolock)) b "; //
        $where = "a.AccountID=b.AccountID ";//

        $orderBy = "Id desc";
        if ($this->request->isAjax()) {
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $transactionid = input('transactionid') ? input('transactionid') : '';
            $PlatformType = intval(input('platformtype')) ? intval(input('platformtype')) : 0;
            $CdyType = intval(input('cdytype')) ? intval(input('cdytype')) : 0;
            $amount = input('amount') ? input('amount') : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $mobile = input('mobile') ? input('mobile') : '';
            //拼装sql
            if ($roleId > 0) $where .= " and a.AccountID =" . $roleId;
            if ($PlatformType >= 0) $where .= " and PlatformType=" . $PlatformType;
            if ($CdyType >= 0) $where .= " and CdyType=" . $CdyType;
            if ($strartdate != '') $where .= " and addtime>= '" . $strartdate . " 0:0:0'";
            if ($enddate != '') $where .= " and addtime<= '" . $enddate . " 23:59:59'";
            if ($transactionid != '') $where .= " and TransactionNo = '" . $transactionid . "' ";
            if ($amount > 0) {
                $amount = $amount * 1000;//'0-' . $amount;
                $where .= " and RealMoney = '" . $amount . "' ";
            }
            if ($mobile != '') $where .= " and AccountName like '%" . $mobile . "%' ";

            $base = new model\BaseModel('CD_UserDB');
            $OFFSET = ($page - 1) * $limit;
            $sqlquery = "SELECT $strFields FROM $tableName where $where ORDER BY $orderBy OFFSET $OFFSET  ROWS FETCH NEXT $limit ROWS ONLY";
//            halt($sqlquery);
            $countQuery = "SELECT COUNT(*) Count FROM $tableName where $where";

            $result = $base->getTableQuery($sqlquery); //记录集
            $count = $base->getTableQuery($countQuery)[0]["Count"]; //总行数


            if (empty($result)) {
                return $this->apiReturn(100, '', '暂无可用数据');
            }
            $paysum = 0;
            $list = $result;//$res['data']['ResultData']['list'];
            foreach ($list as $item => &$value) {
                $money = $base->getConversion($value['RealMoney']); //换算金额
                $value['RealMoney'] = $money;
                $paysum = $paysum + $money;
                if ($value['PlatformType'] == 0) {
                    $value['PlatformType'] = 'Google Pay';
                } else if ($value['PlatformType'] == 1) {
                    $value['PlatformType'] = 'App Store';
                }
                if ($value['CdyType'] == 0) {
                    $value['CdyType'] = lang('商城');
                } else if ($value['CdyType'] == 1) {
                    $value['CdyType'] = lang('储蓄罐');
                } else
                    $value['CdyType'] = lang('特惠充值');
            }
            unset($value);

            $totalsum['ordernum'] = $count;
            $totalsum['paysum'] = $paysum;//sprintf('%.2f',$paysum/1000);

            return $this->apiReturn(
                $res['code'] = 0,
                $list,
                $res['message'] = '',
                isset($count) ? $count : 0,  //输出查询条数，分页用
                $totalsum  //输出订单总数和总金额
            );
        }
        return $this->fetch();
    }


    public function addgoods()
    {
        $id = intval(input('id')) ? intval(input('id')) : 0;
        $goodsname = input('commodityname') ? input('commodityname') : '';
        $cdytype = intval(input('cdytype'));
        $realmoney = input('realmoney') ? input('realmoney') : '0';
        $realmoney = doubleval($realmoney);
        if ($this->request->isAjax()) {
            if ($cdytype == -1 || $realmoney == 0) {
                return $this->apiReturn(100, '', '参数错误');
            }
            $res = Api::getInstance()->sendRequest(
                [
                    'id' => $id,
                    'CommodityName' => $goodsname,
                    'CdyType' => $cdytype,
                    'RealMoney' => $realmoney * 1000
                ], 'payment',
                'addgoods');

            //print_r($res);die;

            if ($res['data'] == 1) {
                return $this->apiReturn(0, '', '添加成功');
            } else if ($res['data'] == 100) {
                return $this->apiReturn(100, '', '商品名称已经存在，请更换');
            } else {
                return $this->apiReturn(100, '', '添加失败');
            }
        }

        $info = ['commodityname' => $goodsname, 'id' => $id, 'realmoney' => $realmoney, 'cdytype' => $cdytype];
        $this->assign('info', $info);
        $goods = config('goodstype');
        $this->assign('goods', $goods);
        return $this->fetch();
    }


    public function delgoods()
    {
        if ($this->request->isAjax()) {
            $commodityname = input('goodname');
            if (!empty($commodityname)) {
                $res = Api::getInstance()->sendRequest(
                    ['CommodityName' => $commodityname],
                    'payment',
                    'delgoods'
                );
                if ($res['data']) {
                    return $this->apiReturn(0, '', '删除成功');
                }
            }
            return $this->apiReturn(100, '', '删除失败');
        }
        return $this->fetch();
    }

    /**
     * 审核扫码订单
     * @return type
     */
    public function examineScanPayOrder()
    {
        try {
            if ($this->request->isPost()) {
                $id = input('id', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception('id不能为空');
                }
                $lib = new \app\admin\lib\ScanPayLib();
                $res = $lib->examineChannelPayOrder($id);
                if (!$res['status']) {
                    throw new \Exception($res['msg']);
                }
            } else {
                throw new \Exception('请求方式错误');
            }
            return $this->successData([], '审核成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 扫码订单审核拒绝
     * @return type
     */
    public function rejectScanPayOrder()
    {
        try {
            if ($this->request->isPost()) {
                $id = input('id', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception('id不能为空');
                }
                $lib = new \app\admin\lib\ScanPayLib();
                $res = $lib->rejectScanPayOrder($id);
                if (!$res['status']) {
                    throw new \Exception($res['msg']);
                }
            } else {
                throw new \Exception('请求方式错误');
            }
            return $this->successData([], '拒绝成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 扫码订单列表
     */
    public function ScanPayOrder()
    {
        if ($this->request->isAjax()) {

            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';

            $account_id = input('account_id', 0, 'intval');
            $order_id = input('order_id', '', 'trim');
            $transaction_no = input('transaction_no', '', 'trim');
            $status = input('status', '', 'strval');

            $filter = ' where 1=1 ';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and PayTime >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and PayTime < \'' . $end . ' \'';
            }
            if (!empty($account_id)) {
                $filter .= ' and AccountID =' . $account_id;
            }
            if (!empty($order_id)) {
                $filter .= ' and OrderId = \'' . $order_id . '\'';
            }
            if (!empty($transaction_no)) {
                $filter .= ' and TransactionNo = \'' . $transaction_no . '\'';
            }
            if (!empty($status) || $status === '0') {
                $filter .= ' and Status = ' . $status;
            }
            $UserDB = new \app\model\UserDB();
            $count = $UserDB->getTotalFromSqlSrv($UserDB::USER_SCAN_ORDER_TRANSACTION, $filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $list = $UserDB->getDataListFromSqlSrv($UserDB::USER_SCAN_ORDER_TRANSACTION, $this->getPageIndex(), $this->getPageLimit(), $filter, '', "Id desc");
            return $this->toDataGrid($count, $list);
        }
        return $this->fetch();
    }



    public function bufacoin()
    {
        //权限验证 
        $auth_ids = $this->getAuthIds();
        if (!in_array(10001, $auth_ids)) {
            return $this->apiReturn(2, [], '没有权限');
        }
        $password = input('password');
        $user_controller = new \app\admin\controller\User();
        $pwd = $user_controller->rsacheck($password);
        if (!$pwd) {
            return json(['code' => 2, 'msg' => '密码错误']);
        }
        $userModel = new userModel();
        $userInfo = $userModel->getRow(['id' => session('userid')]);
        if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
            return json(['code' => 2, 'msg' => '密码有误，请重新输入']);
        }
        $orderid = input('orderid');
        $paylog = new PayNotifyLog();
        $where['Method'] = 'app\client\controller\Notify::easyPayNotify';
        $where['error'] = '金币未发放成功';
        $where['OrderId'] = $orderid;
        $paylogrow = $paylog->getDataRow($where);
        $key = 'lock_bufa_' . $orderid;
        if (!Redis::lock($key)) {
            return $this->apiReturn(2, [], '请勿重复操作');
        }

//        if(empty($paylogrow)){
//            return $this->apiReturn(100,'','回调记录不存在');
//        }
        $draw = new model\UserDB();
        $order = $draw->getTableRow('T_UserChannelPayOrder',['orderId'=>$orderid]);
        $paramters= $paylogrow['Parameter'];
        $transactionId = $orderid;
        if($order['Status']!=1){
            $sendQuery = new sendQuery();
            $ChannelId = $order['ChannelID'];
            $realmoney = $order['RealMoney'];
            //$socket, $UserID, $ChannelId, $Torder,$CurrencyType,$RealMoney,$Money,$ChargeType
            $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE_NEW', [$order['AccountID'], $ChannelId, $transactionId, 'IND', $realmoney, $order['RealMoney']*1000, $order['PayType'],$order['Active']]);
            $code = unpack("LCode", $res)['Code'];
            if (intval($code) === 0) {
                $data = [
                    'PayTime' => date('Y-m-d H:i:s', time()),
                    'Status' =>1,
                    'isReturn' => 1,
                    'TransactionNo' => $transactionId,
                    'checkUser' => session('username')
                ];
                $user = new UserDB();
                $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                $log_txt = lang('订单补发成功');
                $ret = $paylog->updateByWhere(['orderId'=>$orderid],['Error'=>$log_txt]);
                GameLog::logData(__METHOD__, $data, 1, '{"code"=>"订单补发成功"}');
                Redis::rm($key);
                return $this->apiReturn(0,'','订单补发成功');
            }
        }
        $data = [
            'Status' => 1,
            '$orderid' => $transactionId
        ];
        Redis::rm($key);
        GameLog::logData(__METHOD__, $data, 0, '{"code"=>"失败"}');
        return $this->apiReturn(100,'','金币发放失败');
    }





}
