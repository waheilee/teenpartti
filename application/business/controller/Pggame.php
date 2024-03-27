<?php

namespace app\business\controller;
use app\model\MasterDB;

class Pggame extends Main
{

    
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
            //取交集
            $ids = array_keys($data);
            $uids = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where(['ProxyChannelId'=>session('business_ProxyChannelId')])->where('AccountID','in',implode($ids, ','))->column('AccountID',"AccountID");
            $data = array_intersect_key($data,$uids);
            if (empty($data)) {
                return $this->apiReturn(0, [], 'success', 0);
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
            $RoleInfo    = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where(['AccountID'=>$uid,'ProxyChannelId'=>session('business_ProxyChannelId')])->find();
            if(empty($RoleInfo)){
                return ['code' => 1,'msg'=>'权限不足'];
            }
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
        $RoleInfo    = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where(['AccountID'=>$uid,'ProxyChannelId'=>session('business_ProxyChannelId')])->find();
        if(empty($RoleInfo)){
            return ['code' => 1,'msg'=>'权限不足'];
        }
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