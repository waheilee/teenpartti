<?php

namespace app\admin\controller;

use app\common\GameLog;
use think\Db;

class auth extends Main
{
    function index() {
        //获取权限列表
        if ($this->getGroupId(session('userid')) == 1) {
             $auth = Db::name('auth_rule')
            ->order(['sort' => 'ASC'])
            ->select();
        } else {
            $auth_ids = $this->getAuthIds();
            $auth = Db::name('auth_rule')
                    ->where('id','in',$auth_ids)
                    ->order(['sort' => 'ASC'])
                    ->select();
        }
        
        foreach ($auth as $k=>&$v){
            $v['title'] =lang($v['title']);
        }
        unset($v);

        $auth = array2Level($auth);
        return $this->fetch('index', ['auth' => $auth]);
    }


    //新增权限
    function add() {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $str = $post['icon'];
            $post['icon'] = checkStr($str);
            $validate = validate('auth');
            $res = $validate->check($post);
            if ($res !== true) {
                $this->error($validate->getError());
            } else {

                Db::name('auth_rule')->insert($post);
                //记录log
                GameLog::logData(__METHOD__, $post, 1);
                $this->success('success');
            }
        } else {
            $auth = Db::name('auth_rule')->where(['status' => 1])->order(['sort' => 'asc'])->select();
            foreach ($auth as $k=>&$v){
                $v['title'] =lang($v['title']);
            }
            unset($v);
            $auth = array2Level($auth);
            //print_r($auth);exit();
            return $this->fetch('add', ['auth' => $auth]);
        }

    }

    /*权限编辑*/
    function edit($id) {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $id = $post['id'];
            $validate = validate('auth');
            $validate->scene('edit');
            $res = $validate->check($post);
            if ($this->getGroupId(session('userid')) != 1) {
                $auth_ids = $this->getAuthIds();
                if (!in_array($id, $auth_ids)) {
                    return $this->apiReturn(1, [], '没有权限');
                }
            }
            
            if ($res !== true) {
                $this->error($validate->getError());
            } else {
                unset($post['id']);
                Db::name('auth_rule')->where('id', $id)->update($post);
                $status = $post['status'] ? lang('显示') : lang('隐藏');
                array_push($post, $status);
                GameLog::logData(__METHOD__, $post);
                $this->success('success');
            }
        } else {
            $pid = Db::name('auth_rule')->where('id', $id)->value('pid');
            if ($pid !== 0) {
                $p_title = Db::name('auth_rule')
                    ->where('id', $pid)
                    ->value('title');
            } else {
                $p_title = lang('顶级菜单');
            }
            $this->assign('p_title', $p_title);
            $data = Db::name('auth_rule')->where('id', $id)->find();
            return $this->fetch('edit', ['data' => $data]);
        }

    }

    /*权限删除*/
    function delete() {
        $id = $this->request->post('id');
        if ($this->getGroupId(session('userid')) != 1) {
            $auth_ids = $this->getAuthIds();
            if (!in_array($id, $auth_ids)) {
                return $this->apiReturn(1, [], '没有权限');
            }
        }
        $juge = Db::name('auth_rule')
            ->where('pid', $id)
            ->find();
        if (!empty($juge)) {
            $this->error('请先删除子权限');
        } else {
            Db::name('auth_rule')
                ->delete($id);

            GameLog::logData(__METHOD__, $this->request->post());
            $this->success('success');
        }
    }

    /*角色页面展示*/
    function role() {
       if ($this->getGroupId(session('userid')) == 1) {
            $role = Db::name('auth_group')
            ->order('id desc')
            ->paginate('15');
        } else {
            $role = Db::name('auth_group')
            ->where('id',$this->getGroupId(session('userid')))
            ->order('id desc')
            ->paginate('15');
        }
        
        $this->assign('role', $role);
        return $this->fetch('role');
    }


    function showRole() {
        if ($this->getGroupId(session('userid')) == 1) {
            $role = Db::name('auth_group')
            ->order('id desc')
            ->paginate('15');
        } else {
            $role = Db::name('auth_group')
            ->where('id',$this->getGroupId(session('userid')))
            ->order('id desc')
            ->paginate('15');
        }
        
        $this->assign('role', $role);
        return $this->fetch('role');
    }

    //新增角色
    function addRole() {
        $auth_group = $this->request->post('role_name');
        if (!empty($auth_group)) {
            $res = Db::name('auth_group')
                ->where('title', $auth_group)
                ->find();
            if (empty($res)) {
                Db::name('auth_group')
                    ->insert(['title' => $auth_group, 'status' => 0]);
                GameLog::logData(__METHOD__, ['title' => $auth_group, 'status' => 0]);
                $this->success('添加成功');
            } else {
                $this->error('系统中已经存在该用户名');
            }
        } else {
            $this->error('请输入角色名称再添加');
        }
    }

    //切换角色状态
    function doChangeRoleStatus() {
        $data = $this->request->post();
        if ($data['id'] == 1) {
            $this->error('不允许修改超级管理员角色');
        }
        if ($data['status'] == 0) {
            Db::name('auth_group')
                ->where('id', $data['id'])
                ->update(['status' => 1]);
            GameLog::logData(__METHOD__, ['id' => $data['id'], 'status' => 1]);
            $this->success('启用成功');
        } else {
            Db::name('auth_group')
                ->where('id', $data['id'])
                ->update(['status' => 0]);
            $logData = [
                'userid' => session('userid'),
                'username' => session('username'),
                'action' => __METHOD__,
                'request' => json_encode(['id' => $data['id'], 'status' => 0], JSON_UNESCAPED_UNICODE),
                'logday' => date('Ymd'),
                'recordtime' => date('Y-m-d H:i:s'),
                'status' => 1
            ];
            GameLog::log($logData);
            $this->success('禁用成功');
        }
    }

    /**
     * 更新权限组规则
     * @param $id
     * @param $auth_rule_ids
     */
    public function updateAuthGroupRule($id) {
        if ($this->request->isPost()) {
            $post = $this->request->post();
//            if($post['id']==1){
//                if(session('userid')!==1)   $this->error('超级管理员信息无法编辑');
//
//            }
            $group_data['id'] = $post['id'];
            $group_data['rules'] = is_array($post['auth_rule_ids']) ? implode(',', $post['auth_rule_ids']) : '';
            $logData = [
                'userid' => session('userid'),
                'username' => session('username'),
                'action' => __METHOD__,
                'request' => json_encode($post, JSON_UNESCAPED_UNICODE),
                'logday' => date('Ymd'),
                'recordtime' => date('Y-m-d H:i:s')
            ];
            GameLog::log($logData);
            if (Db::name('auth_group')->where('id', $post['id'])->update($group_data) !== false) {
                $logData['status'] = 1;
                GameLog::log($logData);
                $this->success('授权成功');
            } else {
                $logData['status'] = 0;
                GameLog::log($logData);
                $this->error('授权失败');
            }
        } else {
            $this->assign('id', $id);
            return $this->fetch('auth');
        }
    }

    //角色编辑
    function editRole($id) {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            if ($post['id'] == 1) {
                $this->error('超级管理员状态无法编辑');
            }
            $validate = validate('role');
            $res = $validate->check($post);
            if (!$res) {
                $this->error($validate->getError());
            } else {
                Db::name('auth_group')
                    ->where('id', $post['id'])
                    ->update(['title' => $post['title'], 'status' => $post['status']]);

                $logData = [
                    'userid' => session('userid'),
                    'username' => session('username'),
                    'action' => __METHOD__,
                    'request' => json_encode($post, JSON_UNESCAPED_UNICODE),
                    'logday' => date('Ymd'),
                    'recordtime' => date('Y-m-d H:i:s'),
                    'status' => 1
                ];
                GameLog::log($logData);
                $this->success('更新成功');
            }
        } else {
            $data = Db::name('auth_group')
                ->where('id', $id)
                ->find();
            return $this->fetch('roleEdit', ['data' => $data]);
        }
    }

    // 删除角色
    function delRole() {
        $id = $this->request->post('id');
        if ($id !== '1') {
            $res = Db::name('auth_group')
                ->delete($id);
            $logData = [
                'userid' => session('userid'),
                'username' => session('username'),
                'action' => __METHOD__,
                'request' => json_encode(['id' => $id], JSON_UNESCAPED_UNICODE),
                'logday' => date('Ymd'),
                'recordtime' => date('Y-m-d H:i:s'),
                'status' => 1
            ];
            GameLog::log($logData);
            $this->success('删除成功');
        } else {
            $this->error('超级管理员无法删除');
        }
    }

    //获取规则数据
    public function getJson() {
        $id = $this->request->post('id');
        $auth_group_data = Db::name('auth_group')->find($id);
        $auth_rules = explode(',', $auth_group_data['rules']);

        //超级管理组 拥有全部权限
        if ($this->getGroupId(session('userid')) ==1) {
            $auth_rule_list = Db::name('auth_rule')->field('id,pid,title')->select();
            //额外权限
            $auth_rule_list[] = [
                'id'=>10000,
                'pid'=>0,
                'title'=>'额外权限',
            ];
            $auth_rule_list[] = [
                'id'=>10001,
                'pid'=>10000,
                'title'=>'操作金币',
            ];
            $auth_rule_list[] = [
                'id'=>10002,
                'pid'=>10000,
                'title'=>'支付方式绑定解绑',
            ];
            $auth_rule_list[] = [
                'id'=>10003,
                'pid'=>10000,
                'title'=>'增加打码量',
            ];
            $auth_rule_list[] = [
                'id'=>10004,
                'pid'=>10000,
                'title'=>'去除打码量',
            ];
            $auth_rule_list[] = [
                'id'=>10005,
                'pid'=>10000,
                'title'=>'代理关系绑定',
            ];
            $auth_rule_list[] = [
                'id'=>10006,
                'pid'=>10000,
                'title'=>'首页授权',
            ];
            $auth_rule_list[] = [
                'id'=>10007,
                'pid'=>10000,
                'title'=>'删除账号',
            ];
            $auth_rule_list[] = [
                'id'=>10008,
                'pid'=>10000,
                'title'=>'导出记录',
            ];
            $auth_rule_list[] = [
                'id'=>10010,
                'pid'=>10000,
                'title'=>'邮件审核',
            ];
            $auth_rule_list[] = [
                'id'=>10011,
                'pid'=>10000,
                'title'=>'提现手动完成付款',
            ];
            $auth_rule_list[] = [
                'id'=>10012,
                'pid'=>10000,
                'title'=>'编辑渠道额度',
            ];
            $auth_rule_list[] = [
                'id'=>10013,
                'pid'=>10000,
                'title'=>'设置打码百分比',
            ];
        } else {
            $auth_ids = $this->getAuthIds();
            $auth_rule_list = Db::name('auth_rule')->field('id,pid,title')->where('id','in',$auth_ids)->select();
            if (in_array(10000, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10000,
                    'pid'=>0,
                    'title'=>'额外权限',
                ];
            }
            if (in_array(10001, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10001,
                    'pid'=>10000,
                    'title'=>'操作金币',
                ];
            }
            if (in_array(10002, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10002,
                    'pid'=>10000,
                    'title'=>'支付方式绑定解绑',
                ];
            }

            if (in_array(10003, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10003,
                    'pid'=>10000,
                    'title'=>'增加打码量',
                ];
            }
            if (in_array(10004, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10004,
                    'pid'=>10000,
                    'title'=>'去除打码量',
                ];
            }
            if (in_array(10005, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10005,
                    'pid'=>10000,
                    'title'=>'代理关系绑定',
                ];
            }
            if (in_array(10006, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10006,
                    'pid'=>10000,
                    'title'=>'首页授权',
                ];
            }
            if (in_array(10007, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10007,
                    'pid'=>10000,
                    'title'=>'删除账号',
                ];
            }
            if (in_array(10008, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10008,
                    'pid'=>10000,
                    'title'=>'导出记录',
                ];
            }
            if (in_array(10010, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10010,
                    'pid'=>10000,
                    'title'=>'邮件审核',
                ];
            }
            if (in_array(10011, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10011,
                    'pid'=>10000,
                    'title'=>'提现手动完成付款',
                ];
            }
            if (in_array(10012, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10012,
                    'pid'=>10000,
                    'title'=>'编辑渠道额度',
                ];
            }
            if (in_array(10013, $auth_ids)) {
                $auth_rule_list[] = [
                    'id'=>10013,
                    'pid'=>10000,
                    'title'=>'设置打码百分比',
                ];
            }
        }
        

        
        foreach ($auth_rule_list as $key => &$value) {
            if ($value['id'] == 159 && session('userid')>10) {
                 unset($auth_rule_list[$key]);
            } else {
                $value['title'] = lang( $value['title']);
                in_array($value['id'], $auth_rules) && $auth_rule_list[$key]['checked'] = true;
            }            
        }
        unset($value);
        $auth_rule_list = array_merge($auth_rule_list);
        return $auth_rule_list;
    }

    public function getGroupId($adminId){
        return Db::table('game_auth_group_access')->where('uid',$adminId)->value('group_id');
    }
}
