<?php

namespace app\admin\controller;


use app\admin\controller\traits\getSocketRoom;
use app\common\Api;
use app\common\GameLog;
use app\model\Account;
use app\model\AreaMsgRightSwitch;
use app\model\Blacklist;
use app\model\CommonModel;
use app\model\CountryCode;
use app\model\LotteryConfig;
use app\model\MasterDB;
use app\model\GameType;
use socket\QuerySocket;
use think\Exception;

/**
 * 支付通道
 */
class Gamemanage extends Main
{

    use getSocketRoom;

    private $socket = null;

    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }


    /**
     * 新增ip/机器码
     */
    public function addIp()
    {
        return $this->fetch();
    }

    /**
     * Notes: 删除黑名单
     * @return mixed
     */
    public function deleteIp()
    {
        $id = intval(input('id')) ? intval(input('id')) : 0;
        $blacklist = new Blacklist();
        $ret = $blacklist->delRow(['id' => $id]);
        //$res = Api::getInstance()->sendRequest(['id' => $request['id']], 'game', 'delblack');
        GameLog::logData(__METHOD__, $id, $ret, $ret);
        return $this->apiReturn(0, [], '记录已经删除');
    }

    /**
     * 黑名单列表
     */
    public function black()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $ipstr = input('roleid') ? input('roleid') : '';
            $blacklist = new Blacklist();

            $where = [];
            if (!empty($ipstr)) {
                $where['LimitStr'] = $ipstr;
            }

            $list = $blacklist->getList($where, $page, $limit, 'id,limitstr,typeid', ' id desc ');
            $count = $blacklist->getCount($where);
            //$res = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit, 'ipstr' => $ipstr], 'game', 'black');
            return $this->apiReturn(0, $list, 'success', $count);
        }
        return $this->fetch();
    }

    /**
     * 黑名单设置
     */
    public function blacklist()
    {
        if ($this->request->isAjax()) {
            $ip = input('ip');
            $type = input('type');
            if (!in_array($type, [1, 3, 4]) || !$ip) {
                return $this->apiReturn(1, [], '设置信息有误');
            }
            $socket = new QuerySocket();
            if ($type == 1) {
                //ip
                if (!checkIp($ip)) {
                    return $this->apiReturn(2, [], 'ip有误');
                }
                if (in_array($ip, array_values(config('site.sysip')))) {
                    return $this->apiReturn(3, [], '系统网关不予添加');
                }

                $res = $socket->setBlackList($ip, $type);
                GameLog::logData(__METHOD__, $this->request->request(), (isset($res['iResult']) && $res['iResult'] == 0) ? 1 : 0, $res);
                if ($res && $res['iResult'] == 0) {
                    return $this->apiReturn(0, [], '设置成功');
                } else {
                    return $this->apiReturn(5, [], '设置失败');
                }
            } else if ($type == 3) {
                //ip段
                $ipArr = explode('-', $ip);
                if (count($ipArr) != 2) {
                    return $this->apiReturn(3, [], 'ip段有误');
                }
                foreach ($ipArr as $item) {
                    if (!checkIp($item)) {
                        return $this->apiReturn(4, [], 'ip段有误');
                    }
                    if (in_array($item, array_values(config('site.sysip')))) {
                        return $this->apiReturn(5, [], '系统网关不予添加');
                    }
                }

                $res = $socket->setBlackList($ip, $type);
                GameLog::logData(__METHOD__, $this->request->request(), (isset($res['iResult']) && $res['iResult'] == 0) ? 1 : 0, $res);
                if ($res && $res['iResult'] == 0) {
                    return $this->apiReturn(0, [], '设置成功');
                } else {
                    return $this->apiReturn(5, [], '设置失败');
                }
            } else {

                $res = $socket->setBlackList($ip, $type);
                GameLog::logData(__METHOD__, $this->request->request(), (isset($res['iResult']) && $res['iResult'] == 0) ? 1 : 0, $res);
                if ($res && $res['iResult'] == 0) {
                    return $this->apiReturn(0, [], '设置成功');
                } else {
                    return $this->apiReturn(5, [], '设置失败');
                }

            }


        }
        return $this->fetch();
    }

    public function blacklist2()
    {
        if ($this->request->isAjax()) {
            $ip = input('ip');
            $type = input('type');
            if (!in_array($type, [1, 3]) || !$ip) {
                return $this->apiReturn(1, [], '设置信息有误');
            }

            if ($type == 1) {
                //ip
                if (!checkIp($ip)) {
                    return $this->apiReturn(2, [], 'ip有误');
                }
            } else {
                //ip段
                $ipArr = explode('-', $ip);
                if (count($ipArr) != 2) {
                    return $this->apiReturn(3, [], 'ip段有误');
                }
                foreach ($ipArr as $item) {
                    if (!checkIp($item)) {
                        return $this->apiReturn(4, [], 'ip段有误');
                    }
                }
            }

            $socket = new QuerySocket();
            $res = $socket->setBlackList($ip, $type);
            if ($res && $res['iResult'] == 0) {
                return $this->apiReturn(0, [], '设置成功');
            } else {
                return $this->apiReturn(5, [], '设置失败');
            }


        }
        return $this->fetch();
    }

    /**
     * 玩家id对应的ip
     */
    public function searchIpbyId()
    {
        if ($this->request->isAjax()) {
            $roleid2 = input('roleid2') ? input('roleid2') : 0;
            $account = new Account();
            $lastloginip = $account->getValue(['AccountID' => $roleid2], 'LastLoginIP');
            $totaluser = $account->getCount(['lastloginip' => $lastloginip]);

            $resp_data = [
                'ip' => $lastloginip,
                'usernum' => $totaluser
            ];
            //$res = Api::getInstance()->sendRequest(['id' => $roleid2], 'game', 'getip');
            return $this->apiReturn(0, $resp_data, 'success', 0);
        }
        return $this->fetch();
    }

    /**
     * ip对应的玩家数量
     */
    public function searchPlayerNumbyIp()
    {
        if ($this->request->isAjax()) {
            $roleid3 = input('roleid3') ? input('roleid3') : 0;
            $res = Api::getInstance()->sendRequest(['ip' => $roleid3], 'game', 'ipnum');
            return $this->apiReturn(0, $res['data'], $res['message'], $res['total']);
        }
        return $this->fetch();
    }

    /**
     * 游戏任务配置
     */
    public function task()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $res = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit], 'game', 'gametask');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        return $this->fetch();
    }

    /**
     * 新增游戏任务配置
     */
    public function addTask()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();


            $insert = [
                'roomid' => intval($request['roomid']) ? intval($request['roomid']) : 0,
                'taskreqround' => intval($request['taskreqround']) ? intval($request['taskreqround']) : 0,
                'taskaward' => intval($request['taskaward']) ? intval($request['taskaward']) : '',
                //                'taskname'     => $request['taskname'] ? $request['taskname'] : 0,
                'status' => intval($request['status']) ? intval($request['status']) : 0,
            ];

            $res = Api::getInstance()->sendRequest($insert, 'game', 'addtask');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    /**
     * Notes: 编辑任务
     * @return mixed
     */
    public function editTask()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $data = [
                'roomid' => $request['roomid'] ? $request['roomid'] : '',
                'taskreqround' => $request['taskreqround'] ? $request['taskreqround'] : 0,
                'taskaward' => $request['taskaward'] ? $request['taskaward'] : '',
                'taskname' => $request['taskname'] ? $request['taskname'] : 0,

            ];
            $res = Api::getInstance()->sendRequest($data, 'game', 'updatetask');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }


        $this->assign('roomid', input('roomid') ? input('roomid') : 0);
        $this->assign('taskreqround', input('taskreqround') ? input('taskreqround') : 0);
        $this->assign('taskaward', input('taskaward') ? input('taskaward') : 0);
        $this->assign('taskname', input('taskname') ? input('taskname') : '');
        $this->assign('status', input('status') ? input('status') : '');
        return $this->fetch();
    }

    /**
     * Notes: 游戏任务上下架
     * @return mixed
     */
    public function setTaskStatus()
    {
        $request = $this->request->request();


        $res = Api::getInstance()->sendRequest(['roomid' => $request['id'], 'status' => $request['status']], 'game', 'updatetaskstatus');

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * 游戏公告管理
     */
    public function notice()
    {
        $classid = intval(input('classid')) ? intval(input('classid')) : 1;
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $updateNotice = [
                'content' => $request['desc'] ? $request['desc'] : '',
                'clientidentify' => 'Test',
                'status' => 1,
                'classid' => $classid
            ];

            $res = Api::getInstance()->sendRequest($updateNotice, 'game', 'updatenotice');
            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            $res['data'] = $request['desc'];
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $res = Api::getInstance()->sendRequest(['id' => 1], 'game', 'sysnotice');
        $this->assign('notice', $res['data']['msgcontent']);
        return $this->fetch();
    }

    /**
     *公告管理
     */
    public function PublicnoteManage()
    {
        switch (input('Action')) {
            case "list":
                $db = new MasterDB();
                $result = $db->TSysMsgList()->GetPage();
                foreach ($result['list'] as &$row) {
                    $row['status'] = (int)$row['Status'] == 0 ? lang('未审核') : lang('已发送');
                }
                return $this->apiJson($result);
            case "add":
                if (request()->isAjax()) {
                    $request = request()->request();
                    //如果发送时间比当前时间早，就使用当前时间+30秒 作为发送时间
                    $push = $request['PushMsgTime'];
                    $nowTime = date('Y-m-d H:i:s');
                    if (strtotime($push) < strtotime($nowTime)) {
                        $request['PushMsgTime'] = date('Y-m-d H:i:s', time() + 30);
                    }
                    unset($request['s'], $request['Action'], $request['MsgID']);
                    $db = new MasterDB();
                    try {
                        $request['CountryCode'] = trim($request['CountryCode']);
                        $row = $db->TSysMsgList()->Insert($request);
                        $this->synconfig();
                        if ($row > 0) return $this->success('添加成功');
                        return $this->error('添加失败');
                    } catch (Exception $exception) {
                        return $this->error('添加失败');
                    }
                }
                $this->assign('Action', 'add');
                $this->assign('ID', 0);
                $coutryconfig = new CountryCode();
                $result = $coutryconfig->getListAll([], '', 'sortid');
                foreach ($result as $k => &$v) {
                    if (!empty($v['CountryName']))
                        $v['CountryName'] = lang($v['CountryName']);
                }
                unset($v);
                $this->assign('country', $result);
                return $this->fetch('publicnote_item');
            case 'get':
                $id = input('ID');
                $db = new MasterDB();
                $row['list'] = $db->TSysMsgList()->GetRow("MsgID=$id");
                $row['list']['CountryCode'] = trim($row['list']['CountryCode']);
                return $this->apiJson($row);
            case 'edit':
                if (request()->isAjax()) {
                    $id = input('MsgID');
                    $request = request()->request();
                    unset($request['s'], $request['Action'], $request['MsgID']);
                    $db = new MasterDB();
                    $request['CountryCode'] = trim($request['CountryCode']);
                    $row = $db->TSysMsgList()->UPData($request, "MsgID=$id");
                    $this->synconfig();//通知服务端
                    if ($row > 0) return $this->success('更新成功');
                    return $this->error('更新失败');
                }
                $coutryconfig = new CountryCode();
                $result = $coutryconfig->getListAll([], '', 'sortid');
                $this->assign('country', $result);
                $this->assign('Action', 'edit');
                $this->assign('ID', input('ID'));
                return $this->fetch('publicnote_item');
            case 'send':
                $id = input('ID');
                $db = new MasterDB();
                $row = $db->TSysMsgList()->UPData(['Status' => 1], "MsgID=$id");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
            case  'del':
                $id = input('ID');
                $db = new MasterDB();
                $row = $db->TSysMsgList()->DeleteRow("MsgID=$id");
                $this->synconfig();
                if ($row > 0) return $this->success('删除成功');
                return $this->error('删除失败');
                break;
        }
        return $this->fetch();
    }


    public function ajaxnotice()
    {
        $classid = intval(input('classid')) ? intval(input('classid')) : 1;
        $res = Api::getInstance()->sendRequest(['id' => $classid], 'game', 'sysnotice');
        return $this->apiReturn(0, $res['data']['msgcontent'], 'success');
    }


    /**
     * 保存公告
     */
    public function saveNotice()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $insert = [
                'weixinname' => $request['weixinname'] ? $request['weixinname'] : '',
                'type' => $request['type'] ? $request['type'] : 0,
                'noticetip' => $request['noticetip'] ? $request['noticetip'] : '',
                'id' => 0,
            ];
            $res = Api::getInstance()->sendRequest($insert, 'system', 'addvip');
            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        return $this->fetch();
    }


    //弹窗公告管理
    public function alert()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $res = Api::getInstance()->sendRequest(['id' => 3], 'game', 'sysnotice');
//            if (isset($res['data']['msgcontent']) && $res['data']['msgcontent']) {
//                if ($request['desc'] == $res['data']['msgcontent'] && $request['status']==$res['data']['status']) {
//                    return $this->apiReturn(0, $res['data'], '更新成功');
//                }
//            }

            if (isset($res['data']['msgcontent']) && $res['data']['msgcontent'] && $res['data']['clientidentify']) {
//                if ($request['desc'] == $res['data']) {
                if ($request['desc'] == $res['data']['msgcontent'] && $res['data']['clientidentify'] == $request['clientidentify'] && $res['data']['status'] == $request['status']) {
                    return $this->apiReturn(0, $res['data']['msgcontent'], '更新成功');
                }
            }
            $updateNotice = [
                'content' => $request['desc'] ? $request['desc'] : '',
                'clientidentify' => $request['clientidentify'] ? $request['clientidentify'] : '',
                'status' => $request['status'] ? $request['status'] : 0,
                'classid' => 3
            ];
            $res = Api::getInstance()->sendRequest($updateNotice, 'game', 'updatenotice');
            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            $res['data'] = $request['desc'];
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $res = Api::getInstance()->sendRequest(['id' => 3], 'game', 'sysnotice');
//        $this->assign('notice', $res['data']);
        $this->assign('clientidentify', trim($res['data']['clientidentify']) ? trim($res['data']['clientidentify']) : '');

        $bankname = config('site.channelid');
        $this->assign('bankname', $bankname);
        $this->assign('notice', $res['data']['msgcontent']);
        $this->assign('status', $res['data']['status']);
        return $this->fetch();
    }


    //游戏配置
    public function configlist()
    {
        if ($this->request->isAjax()) {
//            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $limit = 10000;
            $page = intval(input('page')) ? intval(input('page')) : 1;

            $hundred = [31];
            $thousand = [5, 6, 7, 30, 34, 42, 43, 44];
            $tenthousand = [];


            $res = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit], 'system', 'cfglist');
            if ($res['data']) {
                foreach ($res['data'] as &$v) {
                    if (in_array($v['cfgtype'], $hundred)) {
                        $v['cfgvalue'] /= 100;
                    } elseif (in_array($v['cfgtype'], $thousand)) {
                        $v['cfgvalue'] /= 1000;
                    } elseif (in_array($v['cfgtype'], $tenthousand)) {
                        $v['cfgvalue'] /= 10000;
                    }
                }
                unset($v);
            }

            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
//            return $this->apiReturn($res['code'], $res['data'], $res['message'], 0);
        }
        return $this->fetch();
    }

    //游戏配置2
    public function configlist2()
    {
        $strFields = "cfgtype,cfgvalue,[description]";
        $tableName = " [OM_MasterDB].[dbo].[T_GameConfig] ";
        $where = " where 1=1 ";
        $orderBy = " order by cfgtype asc";

        if ($this->request->isAjax()) {
//            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $limit = 10000;
            $page = intval(input('page')) ? intval(input('page')) : 1;

            $hundred = [31];
            $thousand = [5, 6, 7, 30, 34, 42, 43, 44];
            $tenthousand = [];

            //拼装sql
            $limits = " top " . ($page * $limit);

            $comm = new CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit, $orderBy);
            $count = $list['count'];
            $result = $list['list'];

            if ($result) {
                foreach ($result as &$v) {
                    if (in_array($v['cfgtype'], $hundred)) {
                        $v['cfgvalue'] /= 100;
                    } elseif (in_array($v['cfgtype'], $thousand)) {
                        $v['cfgvalue'] /= 1000;
                    } elseif (in_array($v['cfgtype'], $tenthousand)) {
                        $v['cfgvalue'] /= 10000;
                    }
                }
                unset($v);
            }

            $res['data']['list'] = $result;
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $count;
            return $this->apiReturn($res['code'], $res['data']['list'], $res['message'], $res['total']);
//            return $this->apiReturn($res['code'], $res['data'], $res['message'], 0);
        }
        return $this->fetch();
    }

    //编辑游戏配置
    public function editconfig()
    {
        if ($this->request->isAjax()) {
            $cfgtype = intval(input('cfgtype')) ? intval(input('cfgtype')) : 0;
            $cfgvalue = input('cfgvalue');
            $descript = input('description');
            if (!$cfgtype) {
                return $this->apiReturn(1, [], '编辑失败');
            }
            $hundred = [31];
            $thousand = [5, 6, 7, 30, 34, 42, 43, 44];
            $tenthousand = [];
            if (in_array($cfgtype, $hundred)) {
                $cfgvalue *= 100;
            } elseif (in_array($cfgtype, $thousand)) {
                $cfgvalue *= 1000;
            } elseif (in_array($cfgtype, $tenthousand)) {
                $cfgvalue *= 10000;
            }
            $res = Api::getInstance()->sendRequest(['cfgtype' => $cfgtype, 'cfgvalue' => $cfgvalue, 'description' => $descript], 'system', 'addcfg');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }

        $descript = $_GET['description'];
        $descript = ($descript == '' || $descript == 'null') ? '' : $descript;
        $this->assign('cfgtype', $_GET['cfgtype']);
        $this->assign('description', $descript);
        $this->assign('cfgvalue', $_GET['cfgvalue']);
        return $this->fetch();
    }

    //删除游戏配置
    public function deleteconfig()
    {
        $request = $this->request->request();
        $res = Api::getInstance()->sendRequest(['id' => $request['cfgtype']], 'system', 'delcfg');
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    //新增游戏配置
    public function addconfig()
    {
        if ($this->request->isAjax()) {
            $cfgtype = intval(input('cfgtype')) ? intval(input('cfgtype')) : 0;
            $cfgvalue = input('cfgvalue');
            $descript = input('description');
            if (!$cfgtype) {
                return $this->apiReturn(1, [], '编辑失败');
            }
            $res = Api::getInstance()->sendRequest(['cfgtype' => $cfgtype, 'cfgvalue' => $cfgvalue, 'description' => $descript], 'system', 'addcfg');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }

        return $this->fetch();
    }

    /**
     * 转盘概率设置
     */
    public function lotterylist()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $lotterytype = input('lotterytype', '-1');

            $lottery = new LotteryConfig();

            $where = [];
            if ($lotterytype > -1)
                $where['LotteryType'] = $lotterytype;

            $list = $lottery->getList($where, $page, $limit, '*', ' GoodId asc ');
            $count = $lottery->getCount($where);
            if ($list) {
                foreach ($list as &$v) {
                    ConVerMoney($v['AwardGold']);
                    $ratio = bcdiv($v['Ratio'], 10000, 4) * 100;
                    $v['Ratio'] = sprintf('%.2f', $ratio);
                }
                unset($v);
            }
            return $this->apiReturn(0, $list, 'success', $count);
        }
        return $this->fetch();
    }


    /**
     * Notes: 更新转盘设置
     * @return mixed
     */
    public function updatelottery()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $lottery = new LotteryConfig();
            $save_data = [
                'Ratio' => $request['ratio'] * 100,
                'AwardGold' => $request['awardgold'] * 1000
            ];
            $code = $lottery->updateByWhere(['GoodId' => $request['id']], $save_data);
            //log记录
            GameLog::logData(__METHOD__, $request, (isset($code) && $code == 0) ? 1 : 0, $code);
            return $this->apiReturn(0, '', lang('更新成功'));
        }
        $id = input('id');
        $ratio = input('ratio', '');
        $awardgold = input('awardgold', '');
        $this->assign('id', $id);
        $this->assign('ratio', $ratio);
        $this->assign('awardgold', $awardgold);
        return $this->fetch();
    }

    /**
     * 流水奖励配置
     */
    public function scorelist()
    {

        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $clientidentify = input('clientidentify') ? input('clientidentify') : '';
            $res = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit, 'clientidentify' => $clientidentify], 'player', 'scorelist');

            if (isset($res['data']) && $res['data']) {
                foreach ($res['data'] as &$v) {
                    $v['awardmoney'] = $v['awardmoney'] / 1000;
                }
                unset($v);
            }
            return $this->apiReturn($res['code'], $res['data'] ?? [], $res['message'], $res['total'] ?? 0);
        }
        return $this->fetch();

    }

    /**
     * 流水奖励配置
     */
    public function wateraward()
    {

        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $db = new  MasterDB();
            $res = $db->getTablePage('T_UserRunningReturnConfig', $page, $limit);
//            $res = Api::getInstance()->sendRequest(['page' => $page, 'pagesize' => $limit], 'player', 'wateraward');
            if (isset($res['list']) && $res['list']) {
                foreach ($res['list'] as &$v) {
                    $v['MinRunning'] = $v['MinRunning'] / 1000;
                    $v['MaxRunning'] = $v['MaxRunning'] / 1000;
                    $v['ReturnPercent'] = $v['ReturnPercent'] / 100 . '%';
//                    $v['returnpercent'] = $v['returnpercent']  .'%';
                    if ($v['MaxRunning'] == 0 && $v['MaxRunning'] != 0) {
                        $v['MaxRunning'] = '以上';
                    }
                    $v['MaxMinRunning'] = $v['MinRunning'] . '-' . $v['MaxRunning'];
                }
                unset($v);
            }
            return $this->apiJson($res);
//            var_dump($res['data']);die;
//            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        return $this->fetch();

    }

    /**
     * 新增流水奖励配置
     */
    public function waterawardupdate()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();


            $insert = [
                'minrunning' => $request['minrunning'] ? $request['minrunning'] * 1000 : 0,
                'maxrunning' => $request['maxrunning'] ? $request['maxrunning'] * 1000 : 0,
                'returnpercent' => $request['returnpercent'] ? $request['returnpercent'] * 100 : 0,
                'id' => $request['id'] ? $request['id'] : 0,
            ];
            $res = Api::getInstance()->sendRequest($insert, 'player', 'waterawardupdate');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        return $this->fetch();
    }

    /**
     * Notes: 编辑流水奖励配置
     * @return mixed
     */
    public function edit_wateraward()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $ID = $request['ID'] ? intval($request['ID']) : 0;
            $data = [
                'MinRunning' => $request['MinRunning'] ? $request['MinRunning'] * bl : 0,
                'MaxRunning' => $request['MaxRunning'] ? $request['MaxRunning'] * bl : 0,
                'ReturnPercent' => $request['ReturnPercent'] ? $request['ReturnPercent'] * 100 : 0,
            ];
            //  $res = Api::getInstance()->sendRequest($data, 'player', 'waterawardupdate');
            $db = new MasterDB();
            $row = $db->updateTable('T_UserRunningReturnConfig', $data, "Id=$ID");
            if ($row) {
                //log记录
                GameLog::logData(__METHOD__, $request, $row, $row);
                return $this->apiReturn(0, [], "修改成功");
            }
        }

        $this->assign('ID', input('ID'));
        $this->assign('MinRunning', input('MinRunning') ? input('MinRunning') : 0);
        $this->assign('MaxRunning', input('MaxRunning') ? input('MaxRunning') : 0);
        $this->assign('ReturnPercent', input('ReturnPercent') ? str_replace('%', '', input('ReturnPercent')) : 0);

        return $this->fetch();
    }

    /**
     * Notes: 删除流水奖励配置
     * @return mixed
     */
    public function waterawarddel()
    {
        $request = $this->request->request();
        $res = Api::getInstance()->sendRequest(['id' => $request['id']], 'player', 'waterawarddel');
        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * 新增流水奖励
     */
    public function addScorelist()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();


            $insert = [
                'awardmoney' => $request['awardmoney'] ? $request['awardmoney'] * 1000 : 0,
                'awardid' => $request['awardid'] ? $request['awardid'] : 0,
                //                'id'       => $request['id'] ? $request['id'] : 0,
                'id' => 0,
            ];
            $res = Api::getInstance()->sendRequest($insert, 'player', 'scoreupdate');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        return $this->fetch();
    }

    /**
     * Notes: 编辑流水奖励配置
     * @return mixed
     */
    public function editScorelist()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();

            $data = [
                'awardmoney' => $request['awardmoney'] ? $request['awardmoney'] * 1000 : 0,
                'awardid' => $request['awardid'] ? $request['awardid'] : 0,
                'id' => $request['id'] ? $request['id'] : 0,
            ];
            $res = Api::getInstance()->sendRequest($data, 'player', 'scoreupdate');
            //log记录
//            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }


        $this->assign('id', input('id'));
        $this->assign('awardmoney', input('awardmoney') ? input('awardmoney') : '');
        $this->assign('awardid', input('awardid') ? input('awardid') : '');
        return $this->fetch();
    }

    /**
     * Notes: 删除流水奖励配置
     * @return mixed
     */
    public function deleteScorelist()
    {
        $request = $this->request->request();
        $res = Api::getInstance()->sendRequest(['id' => $request['id']], 'player', 'scoredel');
        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * 流水奖励明细
     */
    public function waterreward()
    {

        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = input('asc') ? input('asc') : 0;
            $res = Api::getInstance()->sendRequest([
                'page' => $page,
                'pagesize' => $limit,
                'orderby' => $orderby,
                'asc' => $asc,
                'roleid' => $roleid
            ], 'player', 'useraward');
            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    $v['dayrunning'] = $v['dayrunning'] / 1000;
                    $v['changemoney'] = $v['changemoney'] / 1000;

                }
                unset($v);
            }
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], isset($res['total']) ? $res['total'] : 0, [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);
        }
//        $bankname   = config('site.channelid');
//        $this->assign('bankname', $bankname);
        return $this->fetch();

    }

    //系统参数配置
    public function systemconfig()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;

            $res = Api::getInstance()->sendRequest([
                'page' => $page,
                'pagesize' => $limit,
            ], 'system', 'systemcfglist');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], isset($res['total']) ? $res['total'] : 0);
        }
        return $this->fetch();
    }

    //编辑系统参数
    public function editsystemconfig()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();

            $data = [
                'cfgkey' => $request['cfgkey'] ? $request['cfgkey'] : '',
                'cfgname' => $request['cfgname'] ? $request['cfgname'] : '',
                'cfgvalue' => $request['cfgvalue'] ? $request['cfgvalue'] : 0,
                'cfgtype' => $request['cfgtype'] ? $request['cfgtype'] : 0,
                'id' => $request['id'] ? $request['id'] : 0,
            ];
            $res = Api::getInstance()->sendRequest($data, 'system', 'updatesystemcfg');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        $this->assign('id', input('id'));
        $this->assign('cfgname', input('cfgname') ? input('cfgname') : '');
        $this->assign('cfgtype', input('cfgtype') ? input('cfgtype') : '');
        $this->assign('cfgkey', input('cfgkey') ? input('cfgkey') : '');
        $this->assign('cfgvalue', input('cfgvalue') ? input('cfgvalue') : '');
        return $this->fetch();
    }

    public function cjconfig()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $kindid = intval(input('limit')) ? intval(input('kindid')) : 0;
            $postdata = [
                'page' => $page,
                'pagesize' => $limit,
                'kindid' => $kindid
            ];
            $res = Api::getInstance()->sendRequest($postdata, 'game', 'cjconfig');
            $newlist = [];
            if (isset($res['data'])) return $this->apiReturn(0, $newlist, '', 0);
            foreach ($res['data'] as $item => $v) {
                $runingration = intval($v['RunningTransferRatio']);
                if ($runingration > 0) {
                    $v['RunningTransferRatio'] = ($runingration / 1000) * 100;
                } else {
                    $v['RunningTransferRatio'] = 0;
                }

                array_push($newlist, $v);
            }
            return $this->apiReturn($res['code'], $newlist, $res['message'], $res['total']);
        }

        $kindList = $this->GetKindList();
        $newlist = [];
        foreach ($kindList as $item => $v) {
            if (intval($v['KindID']) >= 4000) {
                array_push($newlist, $v);
            }
        }
        $this->assign('kindlist', $newlist);
        return $this->fetch();
    }

    public function editcjrate()
    {
        $kindid = input('kindid');
        $name = input('name');
        $rate = intval(input('rate')) ? intval(input('rate')) : 0;
        if ($this->request->isAjax()) {
            if ($rate > 0) {
                $rate = ($rate / 100) * 1000;
            }
            $data = [
                'GameKind' => $kindid,
                'RunningTransferRatio' => $rate
            ];
            $res = Api::getInstance()->sendRequest($data, 'game', 'setcjrate');
            if ($res['data']) {
                return $this->apiReturn(0, '', '修改成功');
            } else {
                return $this->apiReturn(0, '', '修改失败');
            }
        }
        $info = ['kindid' => $kindid, 'ratio' => $rate, 'name' => $name];
        $this->assign('info', $info);
        return $this->fetch();
    }
    //
    //系统彩金设置
    public function sysCaijinconfig()
    {
        if ($this->request->isAjax()) {
            $postdata = [];
            $db = new  MasterDB();
            $res = $db->getTablePage('T_jackpotconfig', 1, 100, '', '', '',
                'jackpottype,virtualcaijin,realcaijin,minstock,maxstock');

//            $res = Api::getInstance()->sendRequest($postdata, 'game', 'cjsetlist');
            $newlist = [];
            $sysconfig = [
                '0' => '超级奖池',
                '1' => '财神到，埃及秘宝，熊猫富贵，公路之王，海豚之夜',
                '2' => '水牛，ROLD WILD，雪女，白狮，五龙争霸',
                '3' => '魔术师，挖矿，小蜜蜂，圣诞老人，大蓝鲸',
                '10' => '其它',
                '11' => '11',
                '12' => '12'
            ];

            foreach ($res['list'] as $item => $v) {
                $v['jackpottypename'] = $sysconfig[$v['jackpottype']];
                $v['virtualcaijin'] = intval($v['virtualcaijin']) / bl;
                $v['realcaijin'] = intval($v['realcaijin']) / bl;
                if (intval($v['jackpottype']) > 0) {
                    $v['minstock'] = '-';
                    $v['maxstock'] = '-';
                } else {
                    $v['minstock'] = $v['minstock'] / bl;
                    $v['maxstock'] = $v['maxstock'] / bl;
                }
                array_push($newlist, $v);
            }
            $res['list'] = $newlist;
            return $this->apiJson($res);
            //return $this->apiReturn($res['code'], $newlist, $res['message'], $res['count']);
        }

        return $this->fetch();
    }

    //设置虚拟彩金
    public function editcaijin()
    {
        $jacktype = intval(input('jacktype')); //
        $vcj = intval(input('vcj'));//虚拟彩金金额
        $jackname = input('jackname');
        $minstock = intval(input('minstock'));// 最小
        $maxstock = intval(input('maxstock'));//最大

        if ($this->request->isAjax()) {
            $postdata = [
                'VirtualCaijin' => $vcj * bl,
                'MinStock' => $minstock * bl,
                'MaxStock' => $maxstock * bl
            ];
            $db = new  MasterDB();
            $res = $db->updateTable('T_JackpotConfig', $postdata, "JackpotType = $jacktype");
//            $res = Api::getInstance()->sendRequest($postdata, 'game', 'updatecjset');
            if ($res) {
                return $this->apiReturn(0, '', '修改成功');
            } else {
                return $this->apiReturn(0, '', '修改失败');
            }
        }

        $info = ['jacktype' => $jacktype, 'vcj' => $vcj, 'jacktypename' => $jackname, 'minstock' => $minstock, 'maxstock' => $maxstock];
        $this->assign('info', $info);
        return $this->fetch();
    }

    public function mailbox()
    {
        $mailtype = config('mailtype');
        $extratype = config('extratype');
        //var_dump($extratype[0]);die;
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $postdata = ["page" => $page, "pagesize" => $limit];
            $res = Api::getInstance()->sendRequest($postdata, 'game', 'getmailboxlist');
            if (!empty($res['data'])) {
                $list = $res['data'];
                foreach ($list as $k => &$v) {
                    $v['RecordType'] = $mailtype[$v['RecordType']];
                    $v['ExtraType'] = $extratype[$v['ExtraType']];
                    $tick = strtotime($v['addtime']) - sysTime();
                    $v['isfurture'] = 0;
                    $v['opertext'] = '正常';
                    if ($tick > 0) {
                        if (substr($v['addtime'], 0, 4) != '2099') {
                            $v['isfurture'] = 1;
                        } else {
                            $v['opertext'] = '已撤回';
                        }
                    }

                }
                unset($v);
                return $this->apiReturn(0, $list, 'success', $res['total']);
            }
            return $this->apiReturn(0, [], 'fail', 0);
        }
        return $this->fetch();
    }

    public function addmailbox()
    {

        if ($this->request->isAjax()) {
            $rolelist = input('rolelist', '');
            $mailtxt = input('mailtxt', '');
            $recordtype = input('recordtype', -1);
            $extratype = input('extratype', -1);
            $sendtime = input('sendtime', '');
            $sendtype = input('sendtype', -1);
            $amount = input('amount', 0);
            $title = input('title', '');

            if ($recordtype == -1 || $extratype == -1 || $sendtype == -1 || $mailtxt == '' || $title == '') {
                return $this->apiReturn(100, '', '请确认输入都正确');
            }
            if ($extratype > 0) {
                if ($amount == '') {
                    return $this->apiReturn(100, '', '请输入附件数量');
                }
            }
            if ($sendtype == 1) {
                if ($sendtime == '') {
                    return $this->apiReturn(100, '', '请输入定时发送时间');
                } else {
                    $delaytime = strtotime($sendtime) - sysTime();

                    if ($delaytime <= 60) {
                        return $this->apiReturn(100, '', '定时时间须大于1分钟以上');
                    }
                }
            }
            if ($rolelist == '全部') {
//                if ($extratype = 0) {
//                    $amount == 0;
//                }

                $delaytime = 0;
                if ($sendtype == 1) {
                    $delaytime = strtotime($sendtime) - sysTime();
                }
                //1603296000|0|0|0|100|testtest
                //print($delaytime.'|'.$rolelist.'|'.$recordtype.'|'.$extratype.'|'.$amount.'|'.$mailtxt);
                $res = $this->socket->sentMailBox(0, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                return $this->apiReturn(0, '', '邮件已发送成功');
            } else {
                if (strstr($rolelist, ',')) {
                    $arrRole = explode(',', $rolelist);
                    foreach ($arrRole as $v) {
                        if (!empty($v)) {
//                            if ($extratype = 0) {
//                                $amount == 0;
//                            }

                            $delaytime = 0;
                            if ($sendtype == 1) {
                                $delaytime = strtotime($sendtime) - sysTime();
                            }
                            $res = $this->socket->sentMailBox($v, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                        }
                    }
                    return $this->apiReturn(0, '', '邮件已发送成功');
                } else {
                    if (is_numeric($rolelist)) {
//                        if ($extratype = 0) {
//                            $amount == 0;
//                        }

                        $delaytime = 0;
                        if ($sendtype == 1) {
                            $delaytime = strtotime($sendtime) - sysTime();
                        }
                        $res = $this->socket->sentMailBox($rolelist, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                        return $this->apiReturn(0, '', '邮件已发送成功');
                    }
                }
            }
        }

        $mailtype = config('mailtype');
        $extratype = config('extratype');
        $this->assign('mailtype', $mailtype);
        $this->assign('extratype', $extratype);
        return $this->fetch();
    }

    public function playermail()
    {
        $mailtype = config('mailtype');
        $extratype = config('extratype');
        //$res = $this->socket->getProfitPercent($roomId); //调用socket
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $postdata = ["page" => $page, "pagesize" => $limit];
            $res = Api::getInstance()->sendRequest($postdata, 'game', 'getmailboxlist');
            if (!empty($res['data'])) {
                $list = $res['data'];
                foreach ($list as $k => &$v) {
                    $v['RecordType'] = $mailtype[$v['RecordType']];
                    $v['ExtraType'] = $extratype[$v['ExtraType']];
                    $tick = strtotime($v['addtime']) - sysTime();
                    $v['isfurture'] = 0;
                    $v['opertext'] = lang('正常');
                    if ($tick > 0) {
                        if (substr($v['addtime'], 0, 4) != '2099') {
                            $v['isfurture'] = 1;
                        } else {
                            $v['opertext'] = lang('已撤回');
                        }
                    }

                }
                unset($v);
                return $this->apiReturn(0, $list, 'success', $res['total']);
            }
            return $this->apiReturn(0, [], 'fail', 0);
        }
        return $this->fetch();
    }

    public function addplayermail()
    {

        if ($this->request->isAjax()) {
            $sendid = input('sendid', 0);
            $rolelist = input('rolelist', 0);
            $mailtxt = input('mailtxt', '');
            $recordtype = input('recordtype', -1);
            $extratype = input('extratype', -1);
            $sendtime = input('sendtime', '');
            $sendtype = input('sendtype', -1);
            $amount = input('amount', 0);
            $title = input('title', '');
            $delaytime = 0;
            if ($recordtype == -1 || $extratype == -1 || $sendtype == -1 || $mailtxt == '' || $title == '') {
                return $this->apiReturn(100, '', '请确认输入都正确');
            }


            if ($extratype > 0) {
                if ($amount == '') {
                    return $this->apiReturn(100, '', '请输入附件数量');
                }
            }

            if ($sendtype == 1) {
                if ($sendtime == '') {
                    return $this->apiReturn(100, '', '请输入定时发送时间');
                } else {
                    $delaytime = strtotime($sendtime) - sysTime();

                    if ($delaytime <= 60) {
                        return $this->apiReturn(100, '', '定时时间须大于1分钟以上');
                    }
                }
            }
            //print($sendid.'|'.$rolelist.'|'.$recordtype.'|'.$extratype.'|'.$amount.'|'.$delaytime.'|'.$mailtxt.'|'.$title);exit();
            if ($rolelist == 0) {


                if ($sendtype == 1) {
                    $delaytime = strtotime($sendtime) - sysTime();
                }
                //print($delaytime.'|'.$rolelist.'|'.$recordtype.'|'.$extratype.'|'.$amount.'|'.$mailtxt);exit();
                $res = $this->socket->sentPlayerMail($sendid, 0, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                return $this->apiReturn(0, '', '邮件已发送成功');
            } else {
                if (strstr($rolelist, ',')) {
                    $arrRole = explode(',', $rolelist);
                    foreach ($arrRole as $v) {
                        if (!empty($v)) {
                            $delaytime = 0;
                            if ($sendtype == 1) {
                                $delaytime = strtotime($sendtime) - sysTime();
                            }
                            $res = $this->socket->sentPlayerMail($sendid, $v, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                        }
                    }
                    return $this->apiReturn(0, '', '邮件已发送成功');
                } else {
                    if (is_numeric($rolelist)) {
                        $delaytime = 0;
                        if ($sendtype == 1) {
                            $delaytime = strtotime($sendtime) - sysTime();
                        }
                        $res = $this->socket->sentPlayerMail($sendid, $rolelist, $recordtype, $extratype, $amount, $delaytime, $mailtxt, $title);
                        return $this->apiReturn(0, '', '邮件已发送成功');
                    }
                }
            }
        }

        $mailtype = config('mailtype');
        $extratype = config('extratype');
        $sendtype = config('sendtype');
        $this->assign('sendtype', $sendtype);
        $this->assign('mailtype', $mailtype);
        $this->assign('extratype', $extratype);
        return $this->fetch();
    }


    public function mailback()
    {
        $id = input('id', 0);
        if ($id > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $id], 'game', 'getmailbox');
            $result = $res['data'];
            if (!empty($result)) {

                $tick = strtotime($result['addtime']) - sysTime();
                if ($tick > 0) {
                    $res = Api::getInstance()->sendRequest(['id' => $id], 'game', 'updatemailbox');
                    $result = $res['data'];
                    if ($result) {
                        return $this->apiReturn(0, [], '撤回成功');
                    }
                }
            }
            return $this->apiReturn(100, [], '撤回失败');
        } else {
            return $this->apiReturn(100, [], '参数错误');
        }
    }

    /*
     * 区域开关
     */
    public function countrylist()
    {

        if ($this->request->isAjax()) {
            $country = new AreaMsgRightSwitch();
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $where = ['isshow' => 1];
            $totalrow = $country->getCount($where);
            $list = $country->getList($where, $page, $limit, '*', 'Code');
            return $this->apiReturn(0, $list, '获取成功', $totalrow);
        }

        return $this->fetch();
    }


    public function addcountry()
    {
        $arearight = new AreaMsgRightSwitch();
        if ($this->request->isAjax()) {
            $code = input('countrycode', '');

            $arearight->updateByWhere(['Code' => $code], ['IsShow' => 1]);
            return $this->apiReturn(0, '', '添加成功！');
        }

        $list = $arearight->getListAll(['isshow' => 0], 'code,country', 'country ');
        $this->assign('areainfo', $list);
        return $this->fetch();
    }


    public function setareastate()
    {
        $code = input('id', '');
        $state = input('state');
        $arearight = new AreaMsgRightSwitch();
        $status = $state == "true" ? 1 : 0;
        $arearight->updateByWhere(['code' => $code], ['AreaMsgRight' => $status]);
        return $this->apiReturn(0, '', '设置成功！');

    }


    public function gametypelist()
    {
        $parentId = input('parent_id');
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $page = intval(input('page')) ? intval(input('page')) : 1;

            $gametype = new GameType();
            $where = [];
            if (!empty($parentId)) {
                $where['ParentId'] = $parentId;
            } else {
                $where['ParentId'] = 1;
            }

            $dic = $gametype->getListAll();
            $list = $gametype->getList($where, $page, $limit, '*', 'SortID desc ');
            foreach ($list as $k => &$v) {
                $v['StatusName'] = lang('开启');
                if ($v['Nullity'] == 1) {
                    $v['StatusName'] = lang('关闭');
                }
                $v['Nullity'] = $v['Nullity'] == 0 ? 1 : 0;
                $v['Maintain'] = $v['Maintain'] == 0 ? 1 : 0;
                $found_arr = array_column($dic, 'TypeID');
                $found_key = array_search($v['ParentId'], $found_arr);
                $v['ParentName'] = $dic[$found_key]['NodeName'];
            }
            $count = $gametype->getCount($where);
            return $this->apiReturn(0, $list, 'success', $count);
        }
        $this->assign('pid', $parentId);
        return $this->fetch();
    }


    public function setGameTypeStatus()
    {
        $typeid = input('TypeID', 0);
        $status = input('status', -1);
        if ($status > -1 && $typeid > 0) {
            $gametype = new GameType();
            $gametype->updateByWhere(['TypeID' => $typeid], ['Nullity' => $status]);
            $this->synconfig();
            return $this->apiReturn(0, '', '设置成功');
        } else
            return $this->apiReturn(100, '', '设置失败');
    }

    public function setGameTypeMaintain()
    {
        $typeid = input('TypeID', 0);
        $status = input('status', -1);
        if ($status > -1 && $typeid > 0) {
            $gametype = new GameType();
            $gametype->updateByWhere(['TypeID' => $typeid], ['Maintain' => $status]);
            $this->synconfig();
            return $this->apiReturn(0, '', '设置成功');
        }
        return $this->apiReturn(100, '', '设置失败');
    }


    public function thirdMaintain()
    {
        if ($this->request->isAjax()) {
            $kingid = input('gametype');
            $status = input('status');
            $thirdID = [36000, 37000, 38000,39400,40000];
            if (in_array($kingid, $thirdID)) {
                $db = new MasterDB();
                $where = '';
                if ($kingid == 36000) {
                    $where = ' KindID>27500 and KindID<27700 ';
                }
                if ($kingid == 37000) {
                    $where = ' KindID>28000 and KindID<28100 ';
                }
                if ($kingid == 38000) {
                    $where = ' KindID>27200 and KindID<27500 ';
                }
                if ($kingid == 39400) {
                    $where = ' KindID>39400 and KindID<39410 ';
                }
                if ($kingid == 40000) {
                    $where = ' KindID>40000 and KindID<41000 ';
                }
                $status = $db->updateTable('T_GameType', ['Maintain' => $status], $where);
                $this->synconfig();
                return $this->apiReturn(0, '', '设置成功');
            }
            return $this->apiReturn(100, '', '设置失败');
        }
        return $this->fetch();
    }


}
