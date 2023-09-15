<?php

namespace app\admin\controller;

use app\model\PushMessage;
use redis\Redis;
use app\common\GameLog;

class Push extends Main
{

    public function record(){
        return $this->fetch();
    }

    public function getPushRecord(){
        $page = input('page', 1);
        $limit = input('limit', 15);
        $roleId = input('roleId', 0);
        $title = trim(input('title'));
        $message = trim(input('message'));
        $descript = trim(input('descript'));
        $startdate = input('startdate');
        $enddate = input('enddate');
        $status = input('status');
        $where = "";

        if ($roleId > 0) {
            $where .= " and RoleId like '%".$roleId."%'";
        }
        if (!empty($title)) {
            $where .= " and title like '%".$title."%'";
        }
        if (!empty($message)) {
            $where .= " and message like '%'".$message."%'";
        }
        if (!empty($descript)) {
            $where .= " and descript like '%'".$descript."%'";
        }
        if (!empty($startdate)) {
            $where .= " and addTime>='".date('Y-m-d H:i:s',strtotime($startdate))."'";
        }
        if (!empty($enddate)) {
            $where .= " and addTime<='".date('Y-m-d H:i:s',strtotime($enddate))."'";
        }
        if (!empty($status)) {
            $where .= " and status=".$status;
        }

        $db = new PushMessage('T_PushMessage');
        $order = 'id desc';
        $list = $db->GetPage($where, $order);
        if (!empty($list['list'])) {
            foreach ($list['list'] as $key => &$val) {
                $val['total'] = Redis::get('push_count_total'.$val['Id']) ?: 0;
                $val['remain'] = Redis::get('push_count_remain'.$val['Id']) ?: 0;
            }
        }    
        return $this->apiJson($list);
    }

    public function check(){
        $id = (int)input('ID');
        $action = input('Action');

        $db = new PushMessage();
        $record = $db->getRowById($id);
        if (empty($record)) {
            return $this->apiReturn(0, [], '记录不存在');
        }
        if ($record['Status'] != 0) {
            return $this->apiReturn(0, [], '记录已审核或已作废');
        }
        if (!in_array($action, ['agree','del'])) {
            return $this->apiReturn(0, [], '操作方法有误');
        }
        switch ($action) {
            case 'agree':
                $status = 1;
                break;
            case 'del':
                $status = 2;
                break;
            default:
                # code...
                break;
        }
        $updateData = [
            'Status'=>$status,
            'Checker'=>session('username'),
            'UpdateTime'=>date('Y-m-d H:i:s')
        ];
        if ($status == 1) {
            //加入推送队列
            Redis::lpush('push_list',json_encode($record));
        }

        $res = $db->updateById($id,$updateData);
        GameLog::logData(__METHOD__, "审核 推送ID=$id  服务端状态码:$res 审核人" . session('username'), 1);
        if ($res) {
            return $this->apiReturn(1, [], '操作成功');
        } else {
            return $this->apiReturn(0, [], '操作失败');
        }
    }

    public function addPushList(){

    }

    public function addRecord(){
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $date = date('Y-m-d H:i:s');
            if(empty($data['SendTime'])){
                $data['SendTime'] = $date;
            }
            if (!in_array($data['SendType'], ['1','2','3'])) {
                return $this->apiReturn(0, [], '设备类型有误');
            }
            $data['Author'] = session('username');
            $data['AddTime'] = $data['UpdateTime'] = $date;
            $data['Status'] = 0;
            $data['Checker'] = '';
            $db = new PushMessage();
            if (empty($data['id'])) {
                $res = $db->add($data);
            } else {
                $db->updateById($data['id'],$data);
            }
            if ($res) {
                return $this->apiReturn(1, [], '操作成功');
            } else {
                return $this->apiReturn(0, [], '操作失败');
            }
        } else {
            $id = (int)input('ID');
            $record = [];
            if ($id) {
                $db = new PushMessage();
                $record = $db->getRowById($id);
            }
            $this->assign('data',$record);
            return $this->fetch();
        }
    }





    public function index() {
        return $this->fetch();
    }

    /**
     * 推送消息
     * 测试通过google api获取Token
	 * $tmp = (new AccessToken)->getAccessToken('./extend/fcm/service-account-file.json');
	 * $topic_name 推送标题
	 * $desc 推送内容
	 * $link 推送链接
	 * $classid 推送渠道
     */
    public function push($topic_name = '',$desc ='',$link='',$classid )
    {
		/*
			if ($val['nClientType'] == 0) {
                    $loginset = '电脑';
                } else if ($val['nClientType'] == 1) {
                    $loginset = '安卓';
                } else if ($val['nClientType'] == 2) {
                    $loginset = 'IOS';
                }
			SELECT  DeviceType,DeviceToken  FROM [CD_Account].[dbo].[T_Accounts]
		*/
		$DeviceType = '';
		$re = explode(',',$classid);
		for($i = 0;$i<count($re);$i++) {
			if ($DeviceType != '')
				$DeviceType .= ',';
			if ($re[$i] == 'android') {
				$DeviceType .= 1;
			} else if ($re[$i] == 'ios') {
				$DeviceType .= 2;
				$push2 = new Push2();
				$del_token = $push2->send_feedback_request(); //IOS群发前先核对是否有删除APP的用户
				//该行调用函数处理T_Accounts用户数据
				
			} else{
				$DeviceType .= 3;
			}
		}
        $acc = new T_Accounts();

        $user_srv = $acc->getRow('DeviceType in (' . $DeviceType . ') and DeviceToken IS NOT NULL', 'AccountID,DeviceType,DeviceToken', 'order by AccountID desc', '');
        for ($i = 0; $i < count($user_srv); $i++) {
            $user_srv[$i]['topic_name'] = $topic_name;
            $user_srv[$i]['desc'] = $desc;
            $user_srv[$i]['link'] = $link;
            $user_srv[$i]['status'] = 0; //推送状态，默认为0
        }
        $push = json_encode($user_srv);
        Redis::set('test_push', $push);
        $pushlist = Redis::get('test_push');

        return $this->apiReturn('0', [], '操作成功');
        //echo $pushlist;
    }
}
