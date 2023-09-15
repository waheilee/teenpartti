<?php

namespace app\admin\controller;

use app\common\Api;
use redis\Redis;
use think\Db;

class Qmtg extends Main
{
    private $db2;

    public function __construct()
    {
        parent::__construct();
        $this->db2 = config('database_qmfx.database');
    }

    /**
     * 明细
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page    = intval(input('page')) ? intval(input('page')) : 1;
            $limit   = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId  = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $start  = intval(input('start')) ? intval(input('start')) : 0;
            if (!$roleId || !$start) {
                return $this->apiReturn(1, [], '请输入玩家id和日期进行查询', 0);
            }

            $key = __METHOD__ . 'roleid' . $roleId . '_day' . $start . '_page' . $page;
            $data = Redis::get($key);
            if (!$data) {
                $where = ['roleid' => $roleId, 'day' => $start];
                $count = Db::connect($this->db2)->name('incomelog')
                    ->where($where)
                    ->count();
                if (!$count) {
                    return $this->apiReturn(0, [], '', 0);
                }
                $res = Db::connect($this->db2)->name('incomelog')
                    ->where($where)
                    ->field('roleid,  fromuser, level,  totaltax, changemoney, realrate, day, createtime')
                    ->page($page, $limit)
                    ->select();
                foreach ($res as &$v) {
                    $v['realrate'] *= 100;
                }
                unset($v);

                $sumdata = Db::connect($this->db2)->name('dayrecord')->where($where)->field('changemoney, tax')->find();
                $sumdata = $sumdata ? $sumdata : ['changemoney' => 0, 'tax' => 0];
                $data = [
                    'data' => $res,
                    'sum'  => $sumdata,
                    'count' => $count
                ];
                //之前的缓存1天
                $expire = ($start == date('Ymd')) ? 180 : 86400;
                Redis::set($key, $data, $expire);
            }

            return $this->apiReturn(0, $data['data'], '', $data['count'], $data['sum']);
        }

        return $this->fetch();
    }

    /**
     * 玩家上下级
     */
    public function levelquery()
    {
        if ($this->request->isAjax()) {
            $userid = input('userid');
            $type   = intval(input('type'));
            $page   = intval(input('page'));
            $limit  = intval(input('limit'));
            if (!$userid || !$type || !in_array($type, [1,2])) {
                return $this->apiReturn(1, [], '请输入玩家id进行查询', 0);
            }
            if ($type == 1) {//直属上级
                $info = Db::connect($this->db2)->name('player')->where(['roleid' => $userid])->find();
                if (!$info['parentid']) {
                    return $this->apiReturn(0, [], '', 0);
                }
                $data  = Db::connect($this->db2)->name('player')->where(['parentid' => $info['parentid']])->select();
                $count = 1;
            } else {
                $count = Db::connect($this->db2)->name('player')->where(['parentid' => $userid])->count();
                if (!$count) {
                    return $this->apiReturn(0, [], '', 0);
                }
                $data = Db::connect($this->db2)->name('player')->where(['parentid' => $userid])->page($page, $limit)->select();
            }
            return $this->apiReturn(0, $data, '', $count);
        }
        return $this->fetch();
    }

    /**
     * Notes:推广充值记录
     */
    public function spreadlist()
    {
        if ($this->request->isAjax()) {

            $page      = intval(input('page')) ? intval(input('page')) : 1;
            $limit     = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId    = intval(input('roleid')) ? intval(input('roleid')) : 0;

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
                isset($res['data']['list']) ? $res['data']['list'] : [] ,
                $res['message'], $res['total'],
                isset($res['data']['total']) ? $res['data']['total']/1000 : []);
        }

        return $this->fetch();
    }

}
