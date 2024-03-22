<?php

namespace app\admin\controller;
use app\model\MasterDB;

class Pggame extends Main
{

    public function index()
    {
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        if (input('action') == 'list') {
            $limit = request()->param('limit') ?: 15;
            $pg_id    = request()->param('pg_id');
            $page  = request()->param('page');
           
            $data = $redis->get('pgfake_data');
            if ($data == null || $data == 'null' || $data == '') {
                $redis->del('pgfake_data');
            }
            $data = $redis->get('pgfake_data');
            if (empty($data)) {
                //默认数据
                $default_data = config('fake_pg_data');
                $redis->set('pgfake_data',json_encode($default_data));
                $data = $default_data;
            } else {
                $data = json_decode($data,true);
            }

            if ($pg_id != '') {
                if (isset($data[$pg_id])) {
                    return $this->apiReturn(0, [$data[$pg_id]], 'success', 1);
                } else{
                    return $this->apiReturn(0, [], 'success', 0);
                }
            }
            
            $ckunk_data = array_chunk($data, $limit);
            return $this->apiReturn(0, $ckunk_data[$page-1], 'success', count($data));
            
        } else {
            if ($redis->EXISTS('pgfake_rtp_data')) {
                $rtp = $redis->get('pgfake_rtp_data');
            } else {
                $rtp = -1;
            }
            $this->assign('rtp',$rtp);
            return $this->fetch();
        }
    }

    public function setrtp(){
        $rtp = (int)request()->param('rtp');
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        if ($rtp>=0) {
            $res = $redis->set('pgfake_rtp_data',$rtp);
            if ($res) {
                return json(['code'=>0,'msg'=>'操作成功']);
            }
        } 
        return json(['code'=>1,'msg'=>'操作失败']);
    }

    public function onekey()
    {
        $status    = request()->param('status');
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        $data = $redis->get('pgfake_data');
        if (empty($data)) {
            //默认数据
            $default_data = config('fake_pg_data');
            $redis->set('pgfake_data',json_encode($default_data));
            $data = $default_data;
        } else {
            $data = json_decode($data,true);
        }

        foreach ($data as $key => &$val) {
            $val['status']=$status;
        }

        $redis->set('pgfake_data',json_encode($data));
        return json(['code'=>0,'msg'=>'操作成功']);
    }


    public function changeStatus()
    {
        $status    = request()->param('status');
        $pg_id    = request()->param('pg_id');
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        $data = $redis->get('pgfake_data');
        if (empty($data)) {
            //默认数据
            $default_data = config('fake_pg_data');
            $redis->set('pgfake_data',json_encode($default_data));
            $data = $default_data;
        } else {
            $data = json_decode($data,true);
        }

        if (isset($data[$pg_id])) {
            $data[$pg_id]['status']=$status;
        } 
        $redis->set('pgfake_data',json_encode($data));
        return json(['code'=>0,'msg'=>'操作成功']);
    }

    public function edit()
    {
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        $data = $redis->get('pgfake_data');
        if (empty($data)) {
            //默认数据
            $default_data = config('fake_pg_data');
            $redis->set('pgfake_data',json_encode($default_data));
            $data = $default_data;
        } else {
            $data = json_decode($data,true);
        }
        if ($this->request->method() == 'POST') {
            $id     = request()->param('id');
            $name   = request()->param('name');
            $pg_id  = request()->param('pg_id');
            $status = request()->param('status');
            $hidden_id = request()->param('hidden_id');

            if ($hidden_id) {
                if ($hidden_id != $pg_id) {
                    unset($data[$hidden_id]);
                }
            }
            $data[$pg_id] = 
                ['id'=>$id,'name'=>$name,'pg_id'=>$pg_id,'status'=>$status];
            $redis->set('pgfake_data',json_encode($data));
            return json(['code'=>0,'msg'=>'操作成功']);
        }
        $pg_id = request()->param('pg_id')?:0;
        if (isset($data[$pg_id])) {
            $this->assign('data',$data[$pg_id]);
        } else {
            $this->assign('data',['status'=>0]);
        }
        return $this->fetch();
    }

    public function del(){
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        $data = $redis->get('pgfake_data');
        if (empty($data)) {
            //默认数据
            $default_data = config('fake_pg_data');
            $redis->set('pgfake_data',json_encode($default_data));
            $data = $default_data;
        } else {
            $data = json_decode($data,true);
        }

        $pg_id  = request()->param('pg_id');
        
        if (isset($data[$pg_id])) {
            unset($data[$pg_id]);
        }
        return json(['code'=>0,'msg'=>'操作成功']);
    }



    /**
     * 转盘手机号码列表
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pgWhiteConfigList()
    {
        if ($this->request->isAjax()) {
            $page = input('page');
            $limit = input('limit');
            $accountId = input('account_id');
            $masterDB = new MasterDB();
            $count = $masterDB->getTableObject('T_PgWhiteConfigList')
                ->count();
            $list = $masterDB->getTableObject('T_PgWhiteConfigList')->alias('a')
                ->field('a.account_id,b.Mobile,b.OperatorId')
                ->join('[CD_UserDB].[dbo].[View_Accountinfo] b','b.AccountID=a.account_id','LEFT')
                ->where(function ($q) use ($accountId) {
                    if ($accountId) {
                        $q->where('account_id', $accountId);
                    }
                })
                ->page($page, $limit)
                ->select();
            $data['count'] = $count;
            $data['list'] = $list;
            return $this->apiJson($data);
        }
        return $this->fetch();
    }

    /**
     * 转盘手机号码列表添加号码
     * @return mixed
     * @throws \think\exception\PDOException
     */
    public function pgWhiteListAdd()
    {
        $accountId = input('account_id');
        $accountIds = explode(',', $accountId);
        $masterDB = new MasterDB();
        try {
            $masterDB->startTrans();
            $data = [];
            foreach ($accountIds as $account) {
                if (empty($account)) {
                    continue;
                }
                $item = [];
                $item['account_id'] = $account;
                $data[] = $item;
            }

            $add = $masterDB->getTableObject('T_PgWhiteConfigList')
                ->insertAll($data);
            // 提交事务
            $masterDB->commit();
            if ($add) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (\Exception $e) {
            // 回滚事务
            $masterDB->rollback();
            return $this->apiReturn(1, '', '添加操作失败');
        }

    }

    /**
     * 转盘手机号码删除号码
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function pgWhiteListDelete()
    {
        $id = input('id');
        $type = input('type');
        $masterDB = new MasterDB();
        if ($type == 1) {
            //单个删除
            $del = $masterDB->getTableObject('T_PgWhiteConfigList')
                ->delete($id);
            if ($del) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } elseif($type == 2) {
            //批量删除
            $ids = explode(',', $id);
            try {
                $masterDB->startTrans();
                $del = $masterDB->getTableObject('T_PgWhiteConfigList')
                    ->delete($ids);
                // 提交事务
                $masterDB->commit();
                if ($del) {
                    return $this->apiReturn(0, '', '操作成功');
                } else {
                    return $this->apiReturn(1, '', '操作失败');
                }
            } catch (\Exception $e) {
                // 回滚事务
                $masterDB->rollback();
                return $this->apiReturn(1, '', '操作失败');
            }

        }else{

            $masterDB->getTableObject('T_PgWhiteConfigList')->where('1=1')->delete();
            return $this->apiReturn(0, '', '操作成功');
        }

    }
}