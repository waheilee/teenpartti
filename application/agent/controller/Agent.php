<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2020/5/9
 * Time: 17:31
 */
namespace app\agent\controller;

use app\model\ProxyMsgLog;
use socket\QuerySocket;

class Agent extends Main
{

    private $socket = null;
    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }


    public function sendmailbox()
    {

        if ($this->request->isAjax()) {

            $sender = $this->proxyid;
            $recordtype = 10;
            $extratype = 0;
            $amount = 0;

            $roleid = input('rolelist', '');
            $title = input('title', '');
            $content = input('mailtxt', '');
            $sendtime = input('sendtime', '');
            $sendtype = input('sendtype', -1);

            if ($sendtype == 1) {
                if (trim($sendtime) == '') {
                    return $this->apiReturn(100, '', '请输入定时发送时间');
                } else {
                    $delaytime = strtotime($sendtime) - time();
                    if ($delaytime <= 60 * 5) {
                        return $this->apiReturn(100, '', '定时时间须大于5分钟以上');
                    }
                }
            }

            if ($roleid == '全部') {
                $roleid = 0;
            }

            if (strpos($roleid, ',')) {
                $roleids = explode(',', $roleid);
                $delaytime = 0;
                if ($sendtype == 1) {
                    $delaytime = strtotime($sendtime) - time();
                }
                foreach ($roleids as $r) {
                    if (!empty($r) && is_numeric($r)) {
                        $result = $this->socket->sentPlayerMail(
                            $sender,
                            $r,
                            $recordtype,
                            $extratype,
                            $amount,
                            $delaytime,
                            $content,
                            $title
                        );
                    }
                }
                return $this->apiReturn(0, '', '邮件已发送成功');
            } else if (is_numeric($roleid)) {
                $delaytime = 0;
                if ($sendtype == 1) {
                    $delaytime = strtotime($sendtime) - time();
                }
                $result = $this->socket->sentPlayerMail(
                    $sender,
                    $roleid,
                    $recordtype,
                    $extratype,
                    $amount,
                    $delaytime,
                    $content,
                    $title
                );
                return $this->apiReturn(0, [], '邮件已发送成功');
            } else {
                return $this->apiReturn(100, [], '发送失败，请重试！');
            }
        }
        return $this->fetch();
    }

    public function messagelog()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $startdate = input('startdate', '');
            $enddate = input('enddate', '');

            $where = ['SenderId' => $this->proxyid];
            if (!empty($startdate)) {
                $where['addtime'] = ['>=', $startdate . ' 00:00:00'];
            }

            if (!empty($enddate)) {
                $where['addtime'] = ['<=', $enddate . ' 23:59:59'];
            }

            $ProxyMsgLog = new ProxyMsgLog();
            $total = $ProxyMsgLog->getCount($where);
            $list = $ProxyMsgLog->getList($where, $page, $limit, '*', ' id desc ');
            if (isset($list)) {
                foreach ($list as $k => &$v) {
                    if ($v['RoleId'] == 0) {
                        $v['RoleId'] = '群发';
                    }
                }
            }
            return $this->apiReturn(0, $list, '获取成功', $total);
        }
        return $this->fetch();
    }


}