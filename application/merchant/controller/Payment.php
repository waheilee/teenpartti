<?php

namespace app\merchant\controller;


use app\common\Api;
use app\common\GameLog;

/**
 * 支付通道
 */
class Payment extends Main
{
    /**
     * 线下转账
     */
    public function offline()
    {
        if ($this->request->isAjax()) {
            $res = Api::getInstance()->sendRequest([], 'payment', 'offline');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], 2);
        }
        return $this->fetch();
    }

    /**
     * 线下转账修改
     */
    public function editOffline()
    {
        if ($this->request->isAjax()) {
            $request  = $this->request->request();
            $validate = validate('Offline');
            $validate->scene('editOffline');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }
            $res = Api::getInstance()->sendRequest([
                'classid'   => $request['classid'],
                'classname' => $request['classname'],

                'bank'      => $request['bank'],
                'cardno'    => $request['cardno'],
                'cardname'  => $request['cardname'],
                'descript'  => $request['descript'],
                'clientidentify' => $request['clientidentify'],
            ], 'payment', 'updateoff');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }


        $this->assign('classid', input('classid'));
        $this->assign('classname', input('classname') ? input('classname') : '');
        $this->assign('bank', input('bank') ? input('bank') : '');
        $this->assign('cardno', input('cardno') ? input('cardno') : '');
        $this->assign('cardname', input('cardname') ? input('cardname') : '');
        $this->assign('descript', input('descript') ? input('descript') : '');

        $this->assign('clientidentify', input('clientidentify') ? input('clientidentify') : '');
        $bankname   = config('site.channelid');
        $this->assign('bankname', $bankname);
        return $this->fetch();
    }

    /**
     * 支付通道
     */
    public function channel()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page  = intval(input('page')) ? intval(input('page')) : 1;

            $res    = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit], 'payment', 'channel');
            $payway = config('site.zf_class');


            foreach ($res['data'] as &$v) {
                $classes   = explode(',', trim($v['classid']));
                $classname = '';
                if ($classes) {
                    foreach ($classes as $c) {
                        if (isset($payway[$c])) {
                            $classname .= $payway[$c] . ',';
                        }
                    }
                }

                $classname      = rtrim($classname, ',');
                $v['classname'] = $classname;
            }
            unset($v);
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        return $this->fetch();
    }

    /**
     * 新增支付通道
     */
    public function addChannel()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();

            $validate = validate('Payment');
            $validate->scene('addChannel');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }

            $payway = $request['payway'];
            if (!is_array($payway)) {
                return $this->apiReturn(2, [], '支付类型有误');
            }
            $sendpayway = '';
            foreach ($payway as $v) {
                if (!in_array($v, [1200, 1300, 1400])) {
                    return $this->apiReturn(3, [], '支付类型有误');
                }
                $sendpayway .= $v . ',';
            }
            $sendpayway = rtrim($sendpayway,',');


//            $minmoney = $request['minmoney'];
//            $maxmoney = $request['maxmoney'];
//            if (!is_numeric($maxmoney) || !is_numeric($minmoney) || $minmoney <= 0 || $maxmoney <= 0) {
//                return $this->apiReturn(4, [], '金额有误');
//            }
//            if ($minmoney >= $maxmoney) {
//                return $this->apiReturn(5, [], '金额有误');
//            }


            $insert = [
                'channelname' => $request['channelname'],
                'classid'     => $sendpayway,
                'mchid'       => $request['mchid'] ? $request['mchid'] : '',
                'appid'       => $request['appid'] ? $request['appid'] : '',
                'noticeurl'   => $request['noticeurl'] ? $request['noticeurl'] : '',
                'descript'    => $request['descript'] ? $request['descript'] : '',
                'status'      => 0,
                'orderid'      => $request['orderid'],
                'minmoney'    => 1,
                'maxmoney'    => 10000,
                'newuser'     => 1,
                'amountlist'    => $request['money'] ? $request['money'] : '',

            ];




            $res = Api::getInstance()->sendRequest($insert, 'payment', 'addchannel');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        return $this->fetch();
    }

    /**
     * Notes: 编辑支付通道
     * @return mixed
     */
    public function editChannel()
    {
        if ($this->request->isAjax()) {
            $request  = $this->request->request();
            $validate = validate('Payment');
            $validate->scene('editChannel');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }

            $payway = $request['payway'];
            if (!is_array($payway)) {
                return $this->apiReturn(2, [], '支付类型有误');
            }
            $sendpayway = '';
            foreach ($payway as $v) {
                if (!in_array($v, [1200, 1300, 1400])) {
                    return $this->apiReturn(3, [], '支付类型有误');
                }
                $sendpayway .= $v . ',';
            }
            $sendpayway = rtrim($sendpayway,',');

//            $minmoney = $request['minmoney'];
//            $maxmoney = $request['maxmoney'];
//            if (!is_numeric($maxmoney) || !is_numeric($minmoney) || $minmoney <= 0 || $maxmoney <= 0) {
//                return $this->apiReturn(4, [], '金额有误');
//            }
//            if ($minmoney >= $maxmoney) {
//                return $this->apiReturn(5, [], '金额有误');
//            }

            $newuser = $request['newuser'];

            if ($newuser != '0' && $newuser != '1') {
                return $this->apiReturn(6, [], '允许新玩家有误');
            }

            $id = $request['id'];
            if (!is_numeric($id) || $id<=0) {
                return $this->apiReturn(7, [], 'id有误');
            }



            $data = [
                'channelid'   => $request['channelid'],
                'id'          => $id,
                'classid'     => $sendpayway,
                'channelname' => $request['channelname'],
                'mchid'       => $request['mchid'] ? $request['mchid'] : '',
                'appid'       => $request['appid'] ? $request['appid'] : '',
                'noticeurl'   => $request['noticeurl'] ? $request['noticeurl'] : '',
                'descript'    => $request['descript'] ? $request['descript'] : '',
                'orderid'      => $request['orderid'],
                'amountlist'      => $request['money'],
                'minmoney'    => 1,
                'maxmoney'    => 10000,
                'newuser'     => $newuser,
                'status'      => $request['status'] ? $request['status'] : 0,
            ];
            $res  = Api::getInstance()->sendRequest($data, 'payment', 'updatechannel');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        $classid = explode(',', trim(input('classid'), ','));
        $this->assign('channelid', input('channelid'));
        $this->assign('classid', $classid);
        $this->assign('newuser', input('newuser'));
        $this->assign('id', input('id'));
        $this->assign('minmoney', input('minmoney'));
        $this->assign('maxmoney', input('maxmoney'));
        $this->assign('orderid', input('orderid'));
        $this->assign('channelname', input('channelname') ? input('channelname') : '');
        $this->assign('mchid', input('mchid') ? input('mchid') : '');
        $this->assign('appid', input('appid') ? input('appid') : '');
        $this->assign('noticeurl', input('noticeurl') ? input('noticeurl') : '');
        $this->assign('descript', input('descript') ? input('descript') : '');
        $this->assign('money', input('amountlist') ? input('amountlist') : '');
        $this->assign('status', input('status') ? input('status') : 0);
        return $this->fetch();
    }

    /**
     * Notes: 删除支付通道
     * @return mixed
     */
    public function deleteChannel()
    {
        $request  = $this->request->request();
        $validate = validate('Payment');
        $validate->scene('deleteChannel');
        if (!$validate->check($request)) {
            return $this->apiReturn(1, [], $validate->getError());
        }

        $res = Api::getInstance()->sendRequest(['id' => $request['id']], 'payment', 'delchannel');

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * Notes: 开启、关闭通道
     * @return mixed
     */


    public function setChannelStatus()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
//        $validate = validate('Payment');
//        $validate->scene('setChannelStatus');
//        if (!$validate->check($request)) {
//            return $this->apiReturn(1, [], $validate->getError());
//        }
            $classid = intval(input('classid')) ? intval(input('classid')) : 0;
            if ($classid === 0) {
                $res = Api::getInstance()->sendRequest(['id' => $request['id'], 'status' => $request['status'], 'newuser' => 0, 'classid' => $classid], 'payment', 'setchannel');
            } else if ($classid === 1) {
                $res = Api::getInstance()->sendRequest(['id' => $request['id'], 'status' => 0, 'newuser' => $request['newuser'], 'classid' => $classid], 'payment', 'setchannel');
            }


            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

    }


    /**
     * 支付金额
     */
    public function amount()
    {
        if ($this->request->isAjax()) {
            $page     = intval(input('page')) ? intval(input('page')) : 1;
            $limit    = intval(input('limit')) ? intval(input('limit')) : 10;
            $channelid  = input('channelid') ? input('channelid') : 0;
            $data = [
                'channelid' => $channelid,
                'page'     => $page,
                'pagesize' => $limit
            ];
            $res  = Api::getInstance()->sendRequest($data, 'payment', 'amount');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $channel = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 1000], 'payment', 'channel');
        $class   = config('site.zf_class');
        $this->assign('channel', $channel['data']);
        $this->assign('class', $class);
        return $this->fetch();
    }

    /**
     * 新增金额
     */
    public function addAmount()
    {
        if ($this->request->isAjax()) {
            $request  = $this->request->request();
            $validate = validate('Payment');
            $validate->scene('addAmount');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }
            $res = Api::getInstance()->sendRequest([
                'amount' => $request['amount'],
                'channelid' => $request['channelid'],
            ], 'payment', 'addamount');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $channel = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 1000], 'payment', 'channel');
        $class   = config('site.zf_class');
        $this->assign('channel', $channel['data']);
        $this->assign('class', $class);
        return $this->fetch();
    }

    /**
     * Notes: 修改金额
     * @return mixed
     */
    public function editAmount()
    {
        if ($this->request->isAjax()) {
            $request  = $this->request->request();
            $validate = validate('Payment');
            $validate->scene('editAmount');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }
            $res = Api::getInstance()->sendRequest([
                'id'     => $request['id'],
                'amount' => $request['amount']
            ], 'payment', 'updateamount');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }


        $this->assign('id', input('id'));
        $this->assign('amount', input('amount') ? input('amount') : '');
        return $this->fetch();
    }

    /**
     * Notes: 删除固定金额
     * @return mixed
     */
    public function deleteAmount()
    {
        $request  = $this->request->request();
        $validate = validate('Payment');
        $validate->scene('deleteAmount');
        if (!$validate->check($request)) {
            return $this->apiReturn(1, [], $validate->getError());
        }
        $res = Api::getInstance()->sendRequest(['id' => $request['id']], 'payment', 'delamount');

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * 通道金额关系列表
     */
    public function payment()
    {
        if ($this->request->isAjax()) {
            $page      = input('page') ? input('page') : 1;
            $pagesize  = input('limit') ? input('limit') : 10;
            $classid   = input('classid') ? input('classid') : 0;
            $channelid = input('channelid') ? input('channelid') : 0;

            $res = Api::getInstance()->sendRequest([
                'classid'   => $classid,
                'channelid' => $channelid,
                'page'      => $page,
                'pagesize'  => $pagesize
            ], 'payment', 'relate');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }

        $channel = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 1000], 'payment', 'channel');
        $class   = config('site.zf_class');
        $this->assign('channel', $channel['data']);
        $this->assign('class', $class);
        return $this->fetch();
    }


    /**
     * Notes: 删除关系
     * @return mixed
     */
    public function deletePayment()
    {
        $request  = $this->request->request();
        $validate = validate('Payment');
        $validate->scene('deletePayment');
        if (!$validate->check($request)) {
            return $this->apiReturn(1, [], $validate->getError());
        }

        $res = Api::getInstance()->sendRequest(['id' => $request['id']], 'payment', 'delrelate');

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * 新增通道金额关系
     */
    public function addPayment()
    {
        if ($this->request->isAjax()) {
            $request  = $this->request->request();
            $validate = validate('Payment');
            $validate->scene('addPayment');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }
            $res = Api::getInstance()->sendRequest([
                'amountid'  => $request['amountid'],
                'classid'   => $request['classid'],
                'channelid' => $request['channelid'],
            ], 'payment', 'addrelate');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }


        $amountchannel = Api::getInstance()->sendRequest([], 'payment', 'amoutchannel');
        $class         = config('site.zf_class');
        $this->assign('class', $class);
        $this->assign('amountchannel', $amountchannel['data'] ? $amountchannel['data'] : []);
        return $this->fetch();
    }








}
