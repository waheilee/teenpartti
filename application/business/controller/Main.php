<?php

namespace app\business\controller;

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
        $username = session('business_LoginAccount');
        if (empty($username)) {
            session('business_LoginAccount', null);
            session('business_ProxyChannelId', null);
            session('business_Proxytype', null);
            $this->redirect('business/user/login');
        }

        $this->getMenu();

        $lang = input('lang') ?: '';
        $cookie_lang = cookie('think_var');
        if (empty($lang) && empty($cookie_lang)) {
            $lang = 'zh-cn';
        }
        if ($lang) {
            cookie('think_var',$lang);
        }
        $lang = cookie('think_var');
        $datalang = 'cn';
        $laypath ="/public/layuiAdmin/layuiadmin";
        switch ($lang){
            case "en-us":
                $laypath ="/public/layuiAdmin/layuiadmin_en";
                $date_lang =",lang: 'en'";
                $datalang ='en';
                break;
//            case "thai":
//                $laypath ="/public/layuiAdmin/layuiadmin_th";
//                $date_lang =",lang: 'en'";
//                $datalang ='th';
//                break;
            defautl:
                $laypath ="/public/layuiAdmin/layuiadmin";
                break;
        }

        $this->assign('laypath',$laypath);
        $this->assign('datelang',$datalang);
        session('lastuse', sysTime());
    }


    /**
     * 获取侧边栏菜单
     */
    protected function getMenu()
    {
        $menu = [];

        $auth_rule_list = Db::name('business_auth_rule')->where('status', 1)->order('sort,pid asc')->select();
        foreach ($auth_rule_list as $value) {
            $menu[] = $value;
        }
        $menu = !empty($menu) ? array2tree($menu) : [];
        $menu_arr = array_column($menu, 'sort');
        array_multisort($menu_arr, SORT_ASC, $menu);

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

    
    /**
     * 返回错误信息。
     * @param type $message     
     */
    protected function failData($message = null, $code = 1) {
        return array('code'=>$code, 'status' => false, 'msg' => lang($message));
    }

    /**
     * 返回成功信息
     * @param type $data
     */
    protected function successData($data = NULL, $msg = "success", $code = 0) {
        return array('code'=>$code, 'status' => true, 'data' => $data, 'msg'=>lang($msg));
    }
    
    /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message, $options = true, $code = 1) {
        $jsonData = array('code'=>$code, 'status' => false, 'msg' => lang($message));
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success", $options = 256, $code = 0) {
        $jsonData = array('code'=>$code, 'status' => true, 'data' => $data, 'msg' => lang($msg));
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }
    /**
     * 获取当前页码
     * @return type
     */
    protected function getPageIndex() {
        return input('page', -1);
    }

    /**
     * 获取每页显示数量
     * @return type`
     */
    protected function getPageLimit() {
        $pageLimit = input('limit', 10);
        if ($pageLimit > 50){
            $pageLimit = 50;
        }
        return $pageLimit;
    }
    
    protected function toDataGrid($count=0, $data=[], $code=0, $msg='') {
        return json(array('data'=>$data, 'count'=>$count, 'code'=>$code, 'msg'=>$msg));
    }

    //房间配置 room kind
    public function GetRoomList()
    {
        $key = &$this->roomListKey;
        if (Cache::has($key)) {
            $db = new model\MasterDB();
            $sql ="select b.RoomID,RoomName+'-('+CONVERT(VARCHAR,b.RoomID)+')' RoomName  from [OM_GameOC].[dbo].[T_GameRoomSort] as a left join  T_GameRoomInfo as b on a.RoomID=b.RoomID  where b.Nullity=0 order by a.sortID";
            $rsult = $db->getTableQuery($sql);
            //列出禁用的KindID 加以屏蔽
            $treeList = $db->getTableQuery("SELECT KindID,NodeName,ParentId,Nullity FROM T_GameType  WHERE  Nullity=1");
            $dellist =[];// [2200, 3000, 3100, 3200, 3300, 3400];

            foreach ($treeList as $index => &$item) {
                if ($item['KindID'] > 0 && intval($item['Nullity']) == 1) {
                    array_push($dellist, (int)$item['KindID']);
                }
            }
            unset($item);
            foreach ($rsult as $index => &$item) {
                $roomID = (int)($item['RoomID'] / 10) * 10;
                if (in_array($roomID, $dellist)) {
                    unset($rsult[$index]);
                }
            }
            $apiroom=[
                ['RoomID'=>36000,'RoomName'=>'PG-(36000)'],
                ['RoomID'=>37000,'RoomName'=>'EvoLive-(37000)'],
                ['RoomID'=>38000,'RoomName'=>'PP-(38000)']
            ];
            $rsult=array_merge($rsult,$apiroom);
            unset($item);
            Cache::set($key, $rsult, 86400);
            return $rsult;
        }
        return Cache::get($key);
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
            $page   = 0;
            $res = [];
            $res['iTotalCount'] = 0;
            $res['onlinelist'] = [];
            $res['iTotalCount'] = 0;
            while ($is_end) {
                $res_arr = $this->sendGameMessage('CMD_MD_QUERY_ALL_ONLINE_PLAYER', [$page,100], "DC", $param);
                if ($res_arr['iPageCount'] > 0) {
                    $res['iTotalCount'] = $res_arr['iTotalCount'];
                    $res_arr['onlinelist'] = $res_arr['onlinelist'] ?? [];
                    $res['onlinelist']  = array_merge($res_arr['onlinelist'],$res['onlinelist']);
                    if ($res_arr['iPageCount'] < 100) {
                        $is_end = 0;
                    } else {
                        $page = $page+1;
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
                        array_push($result['game'],$val['iUserId']);
                        if (!isset($result['room'][$val['iRoomId']])) {
                            $result['room'][$val['iRoomId']] = [];
                        }
                        array_push($result['room'][$val['iRoomId']],$val['iUserId']);
                        $result['roominfo'][$val['iUserId']] = $val['iRoomId'];
                    } else {
                        array_push($result['hall'],$val['iUserId']);
                    }
                }
                Redis::set($rediskey, $result, 10);
            }
        } else {
            $result = Redis::get($rediskey);
        }

        return $result;
    }

    public function sendGameMessage($messageFunc, $parameter, $conSrv = "DC", $changeFunc = null)
    {
        $comm = new sendQuery();
        return $comm->callback($messageFunc, $parameter, $conSrv, $changeFunc);
    }

    public function GetOutChannelInfo($showall=0)
    {
        $Channel = $this->getChannelInfo($showall);
        foreach ($Channel as $k => &$v) {
            if ($v['Type'] == 0) unset($Channel[$k], $v);
        }
        return $Channel;
    }

    //获取支付通道信息
    public function getChannelInfo($showall=0)
    {
        $key =& $this->channelInfoKey;
        if($showall)
            $key =& $this->channelInfoKeyALL;
        if (!Cache::has($key)) {
            $db = new model\GamePayChannel();
            $where= '';
            if($showall===0)
                $where=" Status=1";
            $result = $db->getListAll($where, "ChannelId,ChannelName,Type","ChannelName asc");
            if (isset($result)) {
                Cache::set($key, $result);
                return $result;
            }
            return ['ChannelId' => -1, 'ChannelName' => lang('未配置')];
        }
        return Cache::get($key);
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
}
