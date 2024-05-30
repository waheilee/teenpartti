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
            if ($redis->EXISTS('in_pgfake_percent_data')) {
                $percent = $redis->get('in_pgfake_percent_data');
            } else {
                $percent = 0;
            }
            $this->assign('rtp',$rtp);
            $this->assign('percent',$percent);
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

    public function inFakeGamePercentage(){
        $percent = (int)request()->param('percent');
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        if ($percent>=0) {
            $res = $redis->set('in_pgfake_percent_data',$percent);
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
            $redis->set('pgfake_data',json_encode($data));
        }
        return json(['code'=>0,'msg'=>'操作成功']);
    }


    //白名单，不进入假pg
    public function indexUser()
    {
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        if (input('action') == 'list') {
            $limit = request()->param('limit') ?: 15;
            $uid    = request()->param('uid');
            $page  = request()->param('page');

            $data = $redis->get('pgfake_whiteip_data');
            if (empty($data) || $data =='[]') {
                return $this->apiReturn(0, [], 'success', 0);
            }
            $data = json_decode($data,1);

            if ($uid != '') {
                if (isset($data[$uid])) {
                    return $this->apiReturn(0, [$data[$uid]], 'success', 1);
                } else{
                    return $this->apiReturn(0, [], 'success', 0);
                }
            }

            $ckunk_data = array_chunk($data, $limit);
            return $this->apiReturn(0, $ckunk_data[$page-1], 'success', count($data));

        } else {
            return $this->fetch();
        }
    }

    public function editUser()
    {

        if ($this->request->method() == 'POST') {
            $uid      = request()->param('uid');

            $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
            $redis = new \Redis();
            $redis->connect($api_config_ip, 6379);
            $redis->auth('wf123520');

            $data = $redis->get('pgfake_whiteip_data');
            if (empty($data)) {
                $data = [];
            } else {
                $data = json_decode($data,1);
            }

            $data[$uid] =
                ['uid'=>$uid,'date'=>date('Y-m-d H:i:s')];
            $redis->set('pgfake_whiteip_data',json_encode($data));
            return json(['code'=>0,'msg'=>'操作成功']);
        } else {
            return $this->fetch();
        }
    }

    public function delUser(){
        $api_config_ip = (new MasterDB)->getTableObject('T_HttpUrlBase')->where('Id',2)->value('UrlBase');
        $redis = new \Redis();
        $redis->connect($api_config_ip, 6379);
        $redis->auth('wf123520');

        $data = $redis->get('pgfake_whiteip_data');
        if (empty($data)) {
            return json(['code'=>1,'msg'=>'操作失败']);
        }
        $data = json_decode($data,1);
        $uid  = request()->param('uid');

        if (isset($data[$uid])) {
            unset($data[$uid]);
            if (!empty($data)) {
                $redis->set('pgfake_whiteip_data',json_encode($data));
            } else {
                $redis->set('pgfake_whiteip_data','');
            }

        }
        return json(['code'=>0,'msg'=>'操作成功']);
    }
}