<?php

namespace app\admin\controller;

use app\admin\controller\traits\FileLog;
use app\model;
use app\model\CommonModel;
use app\model\MasterDB;
use org\Auth;
use redis\Redis;
use socket\sendQuery;
use think\Cache;
use think\Controller;
use think\Db;
use think\Session;
use XLSXWriter;

class Main extends Controller
{

    protected $not_check;
    protected $channelInfoKey = "getChannelInfo"; //支付通道
    protected $channelInfoKeyALL = "getChannelInfoAll"; //支付通道
    protected $kindListKey = "KIND_LIST";   //游戏类型
    protected $roomListKey = "ROOM_LIST";   //房间列表

    /**
     * 初始化
     */
    public function _initialize()
    {
        $module = $this->request->module();
        $username = session('username');
        if (empty($username)) {

            session('username', null);
            session('userid', null);
            //session(null);
            $this->redirect('admin/user/login');
        }

//        if (time() - session('session_start_time') > config('session')['expire']) {
//            session_destroy();//真正的销毁在这里！
//            $this->redirect('admin/user/login');
//        }

        $this->checkMultiLogin();
        //排除权限
        $this->not_check = config('not_check');
        $this->checkAuth();
        $this->getMenu();

        $lang = input('lang') ?: '';
        $cookie_lang = cookie('think_var');
        if (empty($lang) && empty($cookie_lang)) {
            $lang = 'zh-cn';
        }
        if ($lang) {
            cookie('think_var', $lang);
        }
        $lang = cookie('think_var');
        $datalang = 'cn';
        $laypath = "/public/layuiAdmin/layuiadmin";
        switch ($lang) {
            case "en-us":
                $laypath = "/public/layuiAdmin/layuiadmin_en";
                $date_lang = ",lang: 'en'";
                $datalang = 'en';
                break;
//            case "thai":
//                $laypath ="/public/layuiAdmin/layuiadmin_th";
//                $date_lang =",lang: 'en'";
//                $datalang ='th';
//                break;
                defautl:
                $laypath = "/public/layuiAdmin/layuiadmin";
                break;
        }

        $this->assign('laypath', $laypath);
        $this->assign('datelang', $datalang);
        session('lastuse', sysTime());
    }

    protected function checkMultiLogin()
    {
        $userModel = new \app\model\User();
        $info = $userModel->getRowById(session('userid'));
        if (session_id() != $info['session_id']) {
            session('username', null);
            session('userid', null);
            // session(null);
            $this->redirect('admin/user/login');
        }
    }

    /**
     *  分包可用下拉列表
     */
    public function GetPackageList()
    {
        $Packlist = [];
        foreach ($this->GetPackageName() as $key => &$value) {
            if (in_array($value['OperatorId'], explode(",", session('PackID')))) {
                array_push($Packlist, $value);
            }
        }
        return $Packlist;
    }

    //获取支付通道信息
    public function getChannelInfo($showall = 0)
    {
        $key =& $this->channelInfoKey;
        if ($showall)
            $key =& $this->channelInfoKeyALL;
        if (!Cache::has($key)) {
            $db = new model\GamePayChannel();
            $where = '';
            if ($showall === 0)
                $where = " Status=1";
            $result = $db->getListAll($where, "ChannelId,ChannelName,Type", "ChannelName asc");
            if (isset($result)) {
                Cache::set($key, $result);
                return $result;
            }
            return ['ChannelId' => -1, 'ChannelName' => lang('未配置')];
        }
        return Cache::get($key);
    }


    /**
     *支付渠道
     */
    public function GetPayChannelInfo()
    {
        $Channel = $this->getChannelInfo();
        foreach ($Channel as $k => &$v) {
            if ($v['Type'] == 1) unset($Channel[$k], $v);
        }
        return $Channel;
    }

    /**
     *支出渠道
     */
    public function GetOutChannelInfo($showall = 0)
    {
        $Channel = $this->getChannelInfo($showall);
        foreach ($Channel as $k => &$v) {
            if ($v['Type'] == 0) unset($Channel[$k], $v);
        }
        return $Channel;
    }

    /**
     * 权限检查
     * @return bool
     */
    protected function checkAuth()
    {
        if (!Session::has('userid')) {
            session('username', null);
            session('userid', null);
            session(null);
            $this->redirect('admin/user/login');
        }
        $module = $this->request->module();
        $controller = $this->request->controller();
        $action = $this->request->action();
        // 排除权限
        $notCheck = $this->not_check;
        if (!in_array($module . '/' . $controller . '/' . $action, $notCheck)) {
            $auth = new Auth();
            $adminId = Session::get('userid');

            if (!$auth->check($module . '/' . $controller . '/' . $action, $adminId) && $adminId != 1) {
                $this->apiReturn(-200, [], '没有权限');
                //$this->error('没有权限', 'index/index');
            }
        }
    }

    /**
     * 权限列表
     * @return bool
     */
    protected function getAuthIds()
    {
        if (!Session::has('userid')) {
            session('username', null);
            session('userid', null);
            session(null);
            $this->redirect('admin/user/login');
        }
        $adminId = Session::get('userid');
        $group_id = Db::table('game_auth_group_access')->where('uid', $adminId)->value('group_id');
        $rules = Db::table('game_auth_group')->where('id', $group_id)->value('rules');
        return explode(',', $rules);
    }

    /**
     * 获取侧边栏菜单
     */
    protected function getMenu()
    {
        $menu = [];
        $adminId = Session::get('userid');
        $auth = new Auth();
        $auth_rule_list = Db::name('auth_rule')->where('status', 1)->order('sort,pid asc')->select();
//        echo Db::getlastsql();
//        die();
        foreach ($auth_rule_list as $value) {
            if ($auth->check($value['name'], $adminId) || $adminId == 1) {
                if ($value['id'] == 159 && $adminId>5) {
                    unset($value);
                } else {
                    $menu[] = $value;
                }
            }
        }
        $menu = !empty($menu) ? array2tree($menu) : [];
        $menu_arr = array_column($menu, 'sort');
        array_multisort($menu_arr, SORT_ASC, $menu);
//        echo json_encode($menu);
//               print_r($menu);
        //exit();
        $this->assign('menu', $menu);
    }


    /**
     * Notes: 接口数据返回
     * @param $code
     * @param array $data
     * @param string $msg
     * @param int $count
     * @param array $other
     * @return mixed
     */
    public function apiReturn($code, $data = [], $msg = '', $count = 0, $other = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg' => lang($msg),
            'count' => $count,
            'other' => $other
        ]);
    }

    /**
     * 配合 getTablePage 使用
     *  getTablePage 返回的数据直接使用 这个函数返回给前端
     * @param array $data getTablePage 返回的对象
     * @param false $debug 输出sql语句
     * @return \think\response\Json
     */
    public function apiJson($data = [], $debug = false)
    {
        $tmp = [
            'code' => isset($data['code']) ? $data['code'] : 0,
            'data' => isset($data['list']) ? $data['list'] : [],
            'orderType' => isset($data['ordertype']) ? $data['ordertype'] : "",
            'message' => isset($data['msg']) ? lang($data['msg']) : "",
            'count' => isset($data['count']) ? $data['count'] : 0,
            'other' => isset($data['other']) ? $data['other'] : []
        ];
        if (isset($data["debug"]) || $debug) $tmp['sql'] = $data['sql'];
        return json($tmp);
    }


    //房间配置 room kindlist
    public function GetKindList()
    {
        $key = &$this->kindListKey;
        if (!Cache::has($key)) {
            $db = new model\MasterDB();
            $rsult = $db->getTableQuery("SELECT KindID,KindName,Locked,PayTypeID  FROM T_GameKind  order by kindid ");
            $treeList = $db->getTableQuery("SELECT KindID,NodeName,ParentId,Nullity FROM T_GameType  WHERE  Nullity=1");
            $dellist = [];// [2200, 3000, 3100, 3200, 3300, 3400];

            foreach ($treeList as $index => &$item) {
                if ($item['KindID'] > 0 && intval($item['Nullity']) == 1) {
                    array_push($dellist, (int)$item['KindID']);
                }
            }
            unset($item);
            foreach ($rsult as $index => &$item) {
                $KindID = $item['KindID'];
                if (in_array($KindID, $dellist) || mb_substr($item['KindName'], 0, 3) == 'sn_') {
                    unset($rsult[$index]);
                }

                if ($KindID > 27200 && $KindID < 28100) {
                    unset($rsult[$index]);
                }

            }
            unset($item);
            $array_kind = [];
            foreach ($rsult as $index => $item) {
                $item['KindName'] = lang($item['KindName']);
                array_push($array_kind, $item);
            }

            Cache::set($key, $array_kind);
            return $array_kind;
        }
        return Cache::get($key);
    }

    //房间配置 room kind
    public function GetRoomList()
    {
        $key = &$this->roomListKey;
        if (true) {
            $db = new model\MasterDB();
            $sql = "
SELECT  TypeId as RoomID,NodeName+'-('+CONVERT(VARCHAR,TypeId)+')' RoomName
  FROM [dbo].[T_GameType]  WHERE Nullity=0 AND NodeType=5 and   ParentId in (SELECT TypeID FROM  [dbo].[T_GameType]  WHERE Nullity=0 AND NodeType=3)";
            $rsult = $db->getTableQuery($sql);
            //列出禁用的KindID 加以屏蔽
//            $treeList = $db->getTableQuery("SELECT KindID,typeid,NodeName,ParentId,Nullity FROM T_GameType  WHERE  Nullity=1 and kindid not in(3600,3700,20900,21000,23600,23700,23800,20800,20700)");
//            $dellist = [27200,27300];// [2200, 3000, 3100, 3200, 3300, 3400];
//
//            foreach ($treeList as $index => &$item) {
//                if ($item['KindID'] > 0 && intval($item['Nullity']) == 1) {
//                    array_push($dellist, (int)$item['KindID']);
//                }
//            }
//            unset($item);
//            foreach ($rsult as $index => &$item) {
//                $roomID = (int)($item['RoomID'] / 10) * 10;
//                if (in_array($roomID, $dellist)) {
//                    unset($rsult[$index]);
//                }
//                if (strpos($item['RoomName'], '训练场') !== false) {
//                    unset($rsult[$index]);
//                }
//            }
            $apiroom = [
                ['RoomID' => 36000, 'RoomName' => 'PG-(36000)'],
                ['RoomID' => 37000, 'RoomName' => 'EvoLive-(37000)'],
                ['RoomID' => 38000, 'RoomName' => 'PP-(38000)'],
                // ['RoomID' => 39400, 'RoomName' => 'Spribe-(39400)'],
                // ['RoomID' => 40000, 'RoomName' => 'HaBa-(40000)']
            ];

            if (config('has_jili') == 1) {
                $apiroom[] = ['RoomID' => 39000, 'RoomName' => 'JILI-(39000)'];
            }

            if (config('has_spr') == 1) {
                $apiroom[] = ['RoomID' => 39400, 'RoomName' => 'JDB-(39400)'];
            }
            if (config('has_haba') == 1) {
                $apiroom[] = ['RoomID' => 40000, 'RoomName' => 'HaBa-(40000)'];
            }
            if (config('has_hacksaw') == 1) {
                $apiroom[] = ['RoomID' => 41000, 'RoomName' => 'HackSaw-(41000)'];
            }

            if (config('has_yesbingo') == 1) {
                $apiroom[] = ['RoomID' => 42000, 'RoomName' => 'YESBINGO-(42000)'];
            }

            if (config('has_fcgame') == 1) {
                $apiroom[] = ['RoomID' => 44000, 'RoomName' => 'FCGame-(44000)'];
            }

            if (config('has_tadagame') == 1) {
                $apiroom[] = ['RoomID' => 45000, 'RoomName' => 'TaDa-(45000)'];
            }
            $apiroom[] = ['RoomID' => 46000, 'RoomName' => 'PPLive-(46000)'];


            $rsult = array_merge($rsult, $apiroom);
            unset($item);
            Cache::set($key, $rsult, 86400);
            return $rsult;
        }
        return Cache::get($key);
    }

    //清理GetKindList GetRoomList缓存
    public function ClearCacheRKList()
    {
        Cache::rm($this->roomListKey);
        Cache::rm($this->kindListKey);
        Cache::rm($this->channelInfoKey);
        Cache::rm($this->channelInfoKeyALL);

    }

    //桌子列表 room tablelist
    public function gettablelist()
    {
        $key = "getTableList";
        if (!Cache::has($key)) {
            $db = new  model\MasterDB();
            $result = $db->getTablePage('T_GameTableScheme', 1, 1000)['list'];
            Cache::set($key, $result, 43200);
            return $result;
        }
        return Cache::get($key);
    }

    //获取服务器列表 room serverlist
    public function getServerList($id = 1, $locked = 0)
    {
        $key = "getServerList";
        if (!Cache::has($key)) {
            $db = new  model\MasterDB();
            $result = $db->getTablePage('T_GameServerInfo', 1, 1000, "AND ServerType=$id AND Locked=$locked")['list'];
            Cache::set($key, $result, 43200);
            return $result;
        }
        return Cache::get($key);
    }

    /*
    *room roompreperty
    *SELECT roomid,LuckyEggTaxRate/10 as mingtax,SysMaxLoseMoneyPerRound/1000 as goldmoney  FROM [OM_MasterDB].[dbo].[T_GameRoomInfo]
    */
    public function getroompreperty()
    {
        $strFields = "roomid,LuckyEggTaxRate/10 as mingtax,SysMaxLoseMoneyPerRound/1000 as goldmoney";
        $tableName = " [OM_MasterDB].[dbo].[T_GameRoomInfo] ";
        $comm = new CommonModel;
        $list = $comm->getPageList($tableName, $strFields);
        $count = $list['count'];
        $result = $list['list'];
        return $result;
    }

    //在线用户列表
    public function GetOnlineUserlist($param = 'ProcessDMQueryAllOnline2')
    {
        $result = [];
        if (!Redis::has('onlinelist')) {
            //$log = FileLog::Init("online", "OnlineList");
            $res = $this->sendGameMessage('CMD_MD_QUERY_ALL_ONLINE_PLAYER', [], "DC", $param);
            //$log::INFO("iTotalCount:" . $res['iTotalCount']);
            if ($res['iTotalCount'] > 0) {
                $result = array_column($res['onlinelist'], 'iUserId');
                Redis::set('onlinelist', $result, 10);
            }
        } else {
            $result = Redis::get('onlinelist');
        }
        return $result;
    }

    //在线用户列表
    public function GetOnlineUserlist2($type = 'list')
    {
        $param = 'ProcessDMQueryAllOnlinePlayerRes2';
        $result = [];
        $result['total'] = [];
        $result['game'] = [];
        $result['hall'] = [];
        $result['room'] = [];
        $result['roominfo'] = [];
        $result['total_num'] = 0;
        if ($type == 'list') {
            $rediskey = 'onlinelist2';
        } else {
            $rediskey = 'onlineNum';
        }
        if (!Redis::has($rediskey)) {
            $is_end = 1;
            $page = 0;
            $res = [];
            $res['iTotalCount'] = 0;
            $res['onlinelist'] = [];
            $res['iTotalCount'] = 0;
            while ($is_end) {
                $res_arr = $this->sendGameMessage('CMD_MD_QUERY_ALL_ONLINE_PLAYER', [$page, 100], "DC", $param);
                if ($res_arr['iPageCount'] > 0) {
                    $res['iTotalCount'] = $res_arr['iTotalCount'];
                    $res_arr['onlinelist'] = $res_arr['onlinelist'] ?? [];
                    $res['onlinelist'] = array_merge($res_arr['onlinelist'], $res['onlinelist']);
                    if ($res_arr['iPageCount'] < 100) {
                        $is_end = 0;
                    } else {
                        $page = $page + 1;
                    }
                } else {
                    $is_end = 0;
                }


            }
            if ($rediskey == 'onlineNum') {
                Redis::set($rediskey, $res['iTotalCount'], 10);
                return $res['iTotalCount'];
            }
            if ($res['iTotalCount'] > 0) {
                $result['total_num'] = $res['iTotalCount'] ?? 0;
                $result['total'] = array_column($res['onlinelist'], 'iUserId');
                $result['game'] = [];
                $result['hall'] = [];
                foreach ($res['onlinelist'] as $key => &$val) {
                    if ($val['iRoomId'] > 0) {
                        array_push($result['game'], $val['iUserId']);
                        if (!isset($result['room'][$val['iRoomId']])) {
                            $result['room'][$val['iRoomId']] = [];
                        }
                        array_push($result['room'][$val['iRoomId']], $val['iUserId']);
                        $result['roominfo'][$val['iUserId']] = $val['iRoomId'];
                    } else {
                        array_push($result['hall'], $val['iUserId']);
                    }
                }
                Redis::set($rediskey, $result, 10);
            }
        } else {
            $result = Redis::get($rediskey);
        }

        return $result;
    }

    //桌子列表 System ActivityInfo
    public function getActivityInfo()
    {
        $strFields = "activityid, activityname, activitydesc, activitytype, begintime, endtime, status, roomid, needcontinue, vipawardlevel";
        $tableName = " [OM_MasterDB].[dbo].[T_OperatingActivityInfo] ";
        $orderBy = " order by ActivityID asc";
        $comm = new CommonModel;
        $list = $comm->getsql($tableName, $strFields);
        $count = $list['count'];
        $result = $list['list'];
        return $result;
    }

    //发送消息通知服务端;

    /**
     * @param $messageFunc
     * @param $parameter
     * @param string $conSrv
     * @param null $changeFunc
     * @return string
     */
    public function sendGameMessage($messageFunc, $parameter, $conSrv = "DC", $changeFunc = null)
    {
        $comm = new sendQuery();
        return $comm->callback($messageFunc, $parameter, $conSrv, $changeFunc);
    }

    /**
     * 通知服务端 @return mixed
     */
    public function synconfig()
    {
        Redis::rm('PackageConfig');//删除分包列表缓存
        Redis::rm('GameConfig');//系统配置表 缓存
        Cache::rm('getCountryCode');//国家代码列表
        self::ClearCacheRKList(); ////清理GetKindList GetRoomList缓存
        $data = $this->sendGameMessage('CMD_MD_RELOAD_GAME_DATA', [0]);
        return unpack('Lint', $data)['int'];
    }

    //游戏配置压入Redis
    public static function SetRedisGameConfig()
    {
        $key = "GameConfig";
        if (Redis::has($key)) Redis::rm($key);
        $db = new model\MasterDB();
        $result = $db->getTablePage('T_GameConfig', 1, 1000, "", 'CfgType', 'asc', "CfgType,CfgValue")['list'];
        $result = array_reduce($result, function (&$arr_Array, $v) {
            $arr_Array[$v['CfgType']] = $v['CfgValue'];
            return $arr_Array;
        });
        Redis::set($key, $result);
    }

//    /**
//     * 读取系统配置表
//     * @param int $key CfgType
//     * @return int  CfgValue
//     */
//    private static function GetGameConfig($key)
//    {
//        if (!Redis::has("GameConfig")) self::SetRedisGameConfig();
//        return Redis::get("GameConfig")[$key];
//    }


    /**
     * 通过游戏账号获取角色ID
     * @param string $name 游戏账号
     * @return int
     */
    public function GetUserIDByAccount($name)
    {
        if (!Cache::has($name)) {
            $db = new model\AccountDB();
            $UserID = $db->getTableRow('T_Accounts', "AccountName='$name'", "AccountID")["AccountID"];
            Cache::set($name, $UserID);
            return $UserID;
        }
        return Cache::get($name);
    }

    //分包列表
    public static function GetPackageName()
    {
        $key = "PackageConfig";
        if (!Redis::has($key)) {
            $db = new model\MasterDB();
            $Packlist = $db->getTableList('T_PackageConfig', "", 1, 100, 'OperatorId,PackageName', "OperatorId asc")['list'];
            Redis::set($key, $Packlist, 3600);
            return $Packlist;
        }
        return Redis::get($key);
    }

    /**
     * @param $filename string 表格文件名称
     * @param $header_types array 表头定义
     * @param $rows         array 记录集 二维数组
     * @param $returnObj bool 是否自行处理行输出
     * @param int[] $width
     * @return XLSXWriter
     */
    public function GetExcel($filename, $header_types, &$rows, $returnObj = false, $width = [20, 20, 20, 20, 20, 20, 20, 20, 20, 20])
    {
        ob_clean();
        $writer = new XLSXWriter();
        // header
        if (1) {
            header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename("$filename.xlsx") . '"');
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        }
        /*每列的标题头
        $header_types = array(' 开单时间 ' => 'string', ' 收款时间 ' => 'string', ' 开票项目 ' => 'string',
            ' 票据编号 ' => 'string', ' 客户名称 ' => 'string', ' 实收金额 ' => '0.00', ' 收款方式 ' => 'string', ' 收款人 ' => 'string',
        );*/
        $sheet1 = 'sheet1'; //表名
        /* 设置标题头，指定样式 */
        $styles1 = array('freeze_rows' => 1, 'font' => ' 宋体 ', 'font-size' => 10, 'font-style' => 'bold', 'fill' => '#fff',
            'halign' => 'center', 'border' => 'left,right,top,bottom', 'widths' => $width);
        $writer->writeSheetHeader($sheet1, $header_types, $styles1);
        // 最后是数据，foreach 写入
        $styles2 = ['height' => 16, 'halign' => 'center',];
        if ($returnObj) {
            return $writer;
        } else {
            foreach ($rows as $index => &$row) {
                $writer->writeSheetRow($sheet1, $row, $styles2);
                unset($rows[$index]);
            }
        }

        unset($row);
        $writer->writeToStdOut();
        exit();
    }

    /**
     * 保留小数点$decimal位  不进位
     * @param type $num 要保留小数位的数
     * @param type $decimal 保留小数位数
     * @return type
     */
    public function formatDecimal($num, $decimal, $bl = 1)
    {
        if (empty($num)) {
            return 0;
        } else {
            $num /= $bl;
        }
        $index = strpos($num, '.');
        if ($index > 0) {
            $result = substr($num, 0, $decimal + $index + 1);
        } else {
            $result = $num;
        }
        return $result;
    }

    /**
     * 返回错误信息。
     * @param type $message
     */
    protected function failData($message = null, $code = 1)
    {
        return array('code' => $code, 'status' => false, 'msg' => lang($message));
    }

    /**
     * 返回成功信息
     * @param type $data
     */
    protected function successData($data = NULL, $msg = "success", $code = 0)
    {
        return array('code' => $code, 'status' => true, 'data' => $data, 'msg' => lang($msg));
    }

    /**
     * 输出错误JSON信息。
     * @param type $message
     */
    protected function failJSON($message, $options = true, $code = 1)
    {
        $jsonData = array('code' => $code, 'status' => false, 'msg' => lang($message));
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success", $options = 256, $code = 0)
    {
        $jsonData = array('code' => $code, 'status' => true, 'data' => $data, 'msg' => lang($msg));
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }

    /**
     * 获取当前页码
     * @return type
     */
    protected function getPageIndex()
    {
        return input('page', -1);
    }

    /**
     * 获取每页显示数量
     * @return type`
     */
    protected function getPageLimit()
    {
        $pageLimit = input('limit', 10);
        if ($pageLimit > 50) {
            $pageLimit = 50;
        }
        return $pageLimit;
    }

    protected function toDataGrid($count = 0, $data = [], $code = 0, $msg = '')
    {
        return json(array('data' => $data, 'count' => $count, 'code' => $code, 'msg' => $msg));
    }

    public function getCountryCode()
    {
        $key = "getCountryCode";
        if (!Cache::has($key)) {
            $db = new MasterDB('T_CountryConfig');
            $result = $db->GetPage('', 'SortID', 'CountryCode,CountryCode name,CountryCode value')['list'];
            Cache::set($key, $result, 43200);
            return $result;
        }
        return Cache::get($key);

    }

}
