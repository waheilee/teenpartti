<?php

namespace app\admin\controller;

use app\common\Config as ConfigModel;
use app\common\GameLog;
use app\model\AjustConfig;
use app\model\Dictionary;
use app\model\GameOCDB;
use app\model\HttpUrlBase;
use app\model\MailConfig;
use app\model\MasterDB;
use app\model\UpgradeVerConfig;
use app\model\UserDB;
use redis\Redis;
use think\Db;

//配置文件信息
class GameCfg extends Main
{
    protected $model = null;
    protected $dictModel = null;


    public function __construct()
    {
        parent::__construct();
        $this->model = new ConfigModel();
    }


    //多个包的游戏配置管理
    public function PackageConfigManager()
    {
        $request = $this->request;
        $action = input('action');
        if ($request->isAjax()) {
            $page = input('page', 1);
            $limit = input('limit', 15);
            $db = new MasterDB();
            switch ($action) {
                case 'Packlist':   //分包列表
                    $result = $db->getTablePage('T_PackageConfig', $page, $limit);
                    return $this->apiJson($result);
                    break;
                case 'ChangeSwitch': //分包列表开关修改
                    $ID = intval(input('ID')); //ID
                    $field = input('field'); //提交的字段
                    $value = intval(input('type')); //开关状态 0 or 1
                    $row = $db->updateTable('T_PackageConfig', [$field => $value], "OperatorId=$ID");
                    if ($row && $this->synconfig() == 0)
                        return $this->apiReturn(0, [], '更新成功');
                    break;
                case 'AddItem':
                    $packname = input('name');
                    $packID = count(self::GetPackageName());
                    foreach (self::GetPackageName() as $key => &$value) {
                        if ($value["PackageName"] == $packname) return $this->error("包名已存在");
                    }
                    $db->addrow("T_PackageConfig", ["OperatorId" => $packID, "PackageName" => $packname]);
                    $db->addrow("T_PackageGameConfig", ["OperatorId" => $packID, "GameTypeId" => '']);
                    Redis::rm("PackageConfig");
                    return $this->success("添加成功");
                    break;
                case  'ReName':
                    $packname = input('name');
                    $packID = intval(input('packID'));
                    $row = $db->updateTable("T_PackageConfig", ["PackageName" => $packname], "OperatorId=$packID");
                    Redis::rm("PackageConfig");
                    if ($row > 0) return $this->success("改名成功");
                    else return $this->error("改名失败");

                    break;
            }
        }
        return $this->fetch();
    }

    //多包游戏管理
    public function PackageGamelistTree()
    {
        $ID = (int)input('ID');
        if ($this->request->isAjax()) {
            $action = input('action');
            if ($action == 'GetList') {
                $db = new MasterDB();
                //获取所有游戏
                $treeList = $db->getTableQuery("SELECT TypeID,NodeName,ParentId,Nullity FROM T_GameType  WHERE  TypeID>1");

                $dellist = [];//需要删除的数组下标
                foreach ($treeList as $index => $item) {
                    //找被禁用的父节点
                    if (intval($item['Nullity']) == 1) {
                        array_push($dellist, $item['TypeID']);
                        unset($treeList[$index]);//删除
                        continue;
                    }
                    //找父节被禁用相关的子节点
                    if (in_array($item['ParentId'], $dellist)) {
                        array_push($dellist, $item['TypeID']);
                        unset($treeList[$index]);
                    }
                }
                $treeList = array_merge($treeList);//从建索引

//                $tree_rules = $db->getTableQuery("SELECT GameTypeId FROM T_PackageGameConfig WHERE OperatorId=$ID");
                $tree_rules = $db->TPackageGameConfig()->GetRow(["OperatorId" => $ID], 'GameTypeId');
                //字符串切割成数组
                $tree_rules = explode(',', $tree_rules['GameTypeId']);
                //打上勾选状态
                foreach ($treeList as $key => &$value) in_array($value['TypeID'], $tree_rules) && $treeList[$key]['checked'] = true;
                unset($value);
                return $treeList;
            }

            if ($action == 'UpData') {
                $list = $this->request->post()['list'];
                $db = new MasterDB();
                $row = $db->updateTable('T_PackageGameConfig', ["GameTypeId" => implode(',', $list)], "OperatorId=$ID");
                if ($row && $this->synconfig() == 0)
                    $this->success('更新成功');
            }

        }
        $this->assign("ID", $ID);
        return $this->fetch();
    }

    //充值开发游戏管理
    public function VipGameListTree()
    {
        switch (input('action')) {
            case 'list':
                $db = new MasterDB();
                //获取所有游戏
                $treeList = $db->getTableQuery("SELECT TypeID,NodeName,ParentId,Nullity,NeedRecharge FROM T_GameType  WHERE  TypeID>1");
                $dellist = [];//需要删除的数组下标
                foreach ($treeList as $index => $item) {
                    //找被禁用的父节点
                    if (intval($item['Nullity']) == 1) {
                        array_push($dellist, $item['TypeID']);
                        unset($treeList[$index]);//删除
                        continue;
                    }
                    //找父节被禁用相关的子节点
                    if (in_array($item['ParentId'], $dellist)) {
                        array_push($dellist, $item['TypeID']);
                        unset($treeList[$index]);
                    }
                    //打上勾选状态
                    $item['NeedRecharge'] == 1 && $treeList[$index]['checked'] = true;
                }
                $treeList = array_merge($treeList);//从建索引

                unset($value);
                return $treeList;
            case 'UpData':
                $list = $this->request->post()['list'];
                $db = new MasterDB();
                $data["NeedRecharge"]=0;
                $where=" 1=1 ";
                if (!empty($list)) {
                    $db->GameType()->UPData($data, $where);
                    $list = implode(',', $list);
                    $where="TypeID IN( $list)";
                    $data["NeedRecharge"]=1;
                }
                $row = $db->GameType()->UPData($data, $where);
                if ($row && $this->synconfig() == 0)
                    $this->success('更新成功');
        }
        return $this->fetch();
    }


    public function index()
    {
        $list = $this->model->select();
        foreach ($list as &$v) {
            if ($v['content']) {
                $v['content'] = json_decode($v['content'], true);
            }
            if (in_array($v['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                $v['value'] = explode(',', $v['value']);
            }
            if ($v['type'] == 'array') {
                $v['value'] = json_decode($v['value'], true);
            }
            $v['title'] = htmlspecialchars($v['title']);
            $v['tip'] = htmlspecialchars($v['tip']);
        }
        unset($v);
        //p($list);
        $this->assign('lists', $list);
        return $this->fetch();
    }

    //支出配置
    function ChannelPayOut()
    {
        return $this->fetch();
    }


    //打码量配置
    function needAwardConfig(){
        if($this->request->isAjax()){
            $db =  new MasterDB();
            $page = input('page',1);
            $limit = input('limit',15);
            $orderfield = input('');

            $sql = "(SELECT WealthType as CfgType,WageMultiply as CfgValue,Description FROM T_WageRequireConfig union all 
                SELECT CfgType,CfgValue,Description FROM T_GameConfig WHERE CfgType IN(146,181,203)) as gc";

            $field ='CfgType,CfgValue,Description';
            $result =$db->getTableList($sql,'',$page,$limit,$field,' CfgType asc ');
            foreach ($result['list'] as $k=>&$v){
                if ($v['CfgType'] == 146  || $v['CfgType'] == 181 || $v['CfgType'] == 203) {
                    if ($v['CfgType'] == 146) {
                        $v['CfgValue'] = bcdiv($v['CfgValue'],1000,0);
                    }
                } else {
                    $v['CfgValue'] = bcdiv($v['CfgValue'],10,2);
                }
                
            }
            unset($v);
            return $this->apiJson($result);
        }
        return $this->fetch();
    }



    public function RequireWageEdit()
    {
        $request = $this->request;
        $ID = intval(input('ID'));
        $Value = input('Value', -1);
        $msg = input('Msg');
        $otype = false;
        $rate = 10;


        //Get操作 view
        if ($request->isGet()) {
            $this->assign("ID", $ID);
            $this->assign('Value', $Value);
            $this->assign('msg', $msg);
            return $this->fetch();
        }

        if ($request->isPost()) {
            $action = "";
            // 编辑操作 post 提交操作
            if ($request->isPost() && input('type') == null) {
                $action = "编辑";
                if ($ID == 146 || $ID == 181 || $ID == 203) {
                   $db = new MasterDB('GameConfig');//走name 去掉表头 
                    if ($ID==146) {
                        $rate = 1000;
                    } else {
                        $rate = 1;
                    }
                    $data = ['CfgValue' => $Value*$rate, 'Description' => $msg];
                    if ($db->updateTable('T_GameConfig',$data,['CfgType' => $ID]) > 0 && $this->synconfig() == 0){
                        $otype = true;  
                    }
                } else {
                    $db = new MasterDB('WageRequireConfig');//走name 去掉表头
                    $data = ['WageMultiply' => $Value*$rate, 'Description' => $msg];
                    if ($db->updateTable('T_WageRequireConfig',$data,['WealthType' => $ID]) > 0 && $this->synconfig() == 0){
                        $otype = true;  
                    }    
                }
            }
            if ($otype) {
                GameLog::logData(__METHOD__, ["action" => $action, "ID" => $ID, "Value" => $Value, "Msg" => $msg], 1);
                //$this->SetRedisGameConfig(); //刷新Redis
                return $this->apiReturn(0, [], '更新成功');
            } else  return $this->apiReturn(1, [], '更新失败');


        }
        return $this->fetch();
    }




    public function FunctionItemEdit()
    {
        $request = $this->request;
        $ID = intval(input('ID'));
        $Value = input('Value', -1);
        $msg = input('Msg');
        $key = "lock_GameConfig_$ID";
        $switc = input('type', -1);
        $ttype = input('ttype',0);
        $otype = false;
        $rate = input('Rate',1);
        if(empty($rate))
            $rate=1;

        //Get操作 view
        if ($request->isGet()) {
            $arr = [
                '0'=>'未分类',
                '1'=>'客户端配置',
                '2'=>'新手保护',
                '3'=>'转盘配置',
                '4'=>'控制配置',
                // '5'=>'打码配置',
                '6'=>'充值及返利',
                '7'=>'系统基础配置'
            ];
            $this->assign("type_arr", $arr);
            $this->assign("ID", $ID);
            $this->assign('Value', $Value);
            $this->assign('msg', $msg);
            $this->assign('Rate',$rate);
            $this->assign('ttype',$ttype);
            $this->assign('keyValue', $request->request('keyValue') ?? '');
            return $this->fetch();
        }

        if ($request->isPost()) {
            $action = "";
            // 编辑操作 post 提交操作
            if ($request->isPost() && input('type') == null) {
                $action = "编辑";
                $db = new MasterDB('gameconfig');//走name 去掉表头
                $data = ['CfgValue' => $Value*$rate, 'Description' => $msg, 'keyValue' => $request->request('keyValue') ?? '','Type'=>$ttype];
                if ($db->updateByWhere(['CfgType' => $ID], $data) > 0 && $this->synconfig() == 0)
                    $otype = true;
            }
            //开关操作 type 判断
            if ($request->isPost() && $switc >= 0) {
                $action = lang("开关").':';
                if ($switc) $action .= lang("打开"); else $action .= lang("关闭");
                $data = ['CfgValue' => $switc];
                $db = new MasterDB('gameconfig');//走name 去掉表头
                if ($db->updateByWhere(['CfgType' => $ID], $data) > 0 && $this->synconfig() == 0) $otype = true;
            }
            if ($otype) {
                GameLog::logData(__METHOD__, ["action" => $action, "ID" => $ID, "Value" => $Value, "Msg" => $msg], 1);
                $this->SetRedisGameConfig(); //刷新Redis
                return $this->apiReturn(0, [], '更新成功');
            } else  return $this->apiReturn(1, [], '更新失败');


        }
        return $this->fetch();
    }

    public function FunctionSwitch()
    {
        switch (input('Action')) {
            case 'list':
                if ($this->request->isAjax()) {
                    $type = input('Type', -1);
                    $Switch = input('Switch', -1);
                    $ids = input('IDS');

                    $where = ' AND cfgtype not in(1,10,11,12,29,34,100,114,136,150,146,181,203) ';
                    if ($type >= 0) $where .= " AND type=$type";
                    if ($Switch >= 0) $where .= " AND Switch=$Switch";
                    if (!empty($ids)) $where .= " AND  cfgtype in($ids)";
                    $db = new MasterDB();
                    $result = $db->getTablePage('T_GameConfig', input('page'), input('limit'), $where,
                        input('orderfield'), input('ordertype'));
                    if(!empty($result['list'])){
                        foreach ($result['list'] as $k=>&$v){
                            $div_rate =$v['Rate'];
                            if($div_rate==0)
                                $div_rate =1;
                            $v['CfgValue'] =bcdiv($v['CfgValue'],$div_rate,0);
                            $v['Description'] = lang($v['Description'] );
                        }
                    }
                    unset($v);
                    return $this->apiJson($result);
                }
            case 'version':
                $db = new MasterDB('T_VersionConfig');
                $result = $db->GetPage();
                return $this->apiJson($result);
            case 'country':
                $db = new MasterDB('T_CountryConfig');
                $result = $db->GetPage(""," CountryCode  ");
                return $this->apiJson($result);

        }
        return $this->fetch();
    }

    public function GameAntiBrushConfig()
    {
        switch (input('Action')) {
            case 'list':
                $db = new  MasterDB();
                $kind = $this->GetKindList();
                $FreeProp = input('AllowBuyFreeProp');
                $OpenAnti = input('OpenAntiBomb');
                $IDS = input('IDS');
                $where = "";
                if (!empty($FreeProp)) $where = " AND AllowBuyFreeProp=$FreeProp OR OpenAntiBomb=$OpenAnti";
                if (!empty($IDS)) $where = " AND KindId IN ($IDS) ";
                $result = $db->TGameSingleBetCfg()->GetPage($where);
                foreach ($result['list'] as &$row) {
                    $found_arr = array_column($kind, 'KindID');
                    $found_key = array_search($row['KindId'], $found_arr);
                    if($found_arr!==false){
                        $row['KindName'] = $kind[$found_key]['KindName'];
                    }
//                    for ($i = 18; $i < count($kind); $i++) {
//                        if ($row['KindId'] == $kind[$i]['KindID']) {
//                            $row['KindName'] = $kind[$i]['KindName'];
//                        }
//                    }
                }
                unset($row);
                return $this->apiJson($result);
            case 'switch':
                $filed = input('field');
                $KindID = input('ID');
                $type = input('type');
                $request = request()->request();
                unset($request['s']);
                $db = new  MasterDB();
                $row = $db->TGameSingleBetCfg()->UPData([$filed => $type], "KindId=$KindID");
                $this->synconfig();
                GameLog::logData(__METHOD__, $request);
                if ($row > 0) return $this->success();
                else return $this->error();
            case 'editView':
                $this->assign('KindID', input('KindID'));
                $this->assign('AntiBombMaxMultiply', input('AntiBombMaxMultiply'));
                $this->assign('AntiBombSysMaxLostMultiply', input('AntiBombSysMaxLostMultiply'));
                return $this->fetch('game_antibrush_edit');
            case 'edit':
                $request = request()->request();
                $KindID = $request['KindID'];
                unset($request['s'], $request['Action'], $request['KindID']);
                $db = new  MasterDB();
                $row = $db->TGameSingleBetCfg()->UPData($request, "KindId=$KindID");
                if ($row > 0) {
                    $this->synconfig();
                    return $this->success('修改成功');
                } else return $this->error('修改失败');
        }
        return $this->fetch();
    }

    /**防刷配置*/
    public function AntiBrushConfig()
    {
        return $this->fetch();
    }

    //版本开关
    public function VserionSwitch()
    {
        $request = request()->request();
        $db = new MasterDB('T_VersionConfig');
        switch (input('Action')) {
            case 'update':
                $version = $request['ID'];
                $where = "Version='$version'";
                $row = $db->UPData([$request['field'] => $request['type']], $where);
                $this->synconfig();
                array_push($request, lang('更新'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success();
                else return $this->error();
            case  'add':
                $this->assign('Action', 'add');
                if (request()->isAjax()) {
                    $version = $request['Version'];
                    $row = $db->GetRow("Version='$version'");
                    if (isset($row)) return $this->error('添加失败,版本号已经存在,不允许重复');
                    $row = $db->Insert(['Version' => $request['Version'], 'DeviceType' => $request['DeviceType'], 'Status' => 1]);
                    $this->synconfig();
                    array_push($request, lang('添加'));
                    GameLog::logData(__METHOD__, $request, 1);
                    if ($row > 0) return $this->success('添加成功');
                    else return $this->error('添加失败');
                }
                return $this->fetch('version_item');
            case  'del':
                $version = $request['ID'];
                $row = $db->DeleteRow("Version='$version'");
                $this->synconfig();
                array_push($request, lang('删除'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success('删除成功');
                else return $this->error('删除失败');
            case  'edit':
                $ID = $request['DeviceType'];
                $row = $db->UPData(['DeviceType' => $request['value']], "Version='$ID'");
                $this->synconfig();
                array_push($request, lang('修改'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success('修改成功');
                else return $this->error('修改失败');
        }
        $this->synconfig();
    }

    //国家开关
    public function CountrySwitch()
    {
        $request = request()->request();
        $db = new MasterDB('T_CountryConfig');
        switch (input('Action')) {
            case 'update':
                $version =trim($request['ID']);
                $where = "CountryCode='$version'";
                $row = $db->UPData([$request['field'] => $request['type']], $where);
                $this->synconfig();
                array_push($request, lang('更新'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success();
                else return $this->error();
            case  'add':
                $row = $db->Insert(['CountryCode' => $request['name'], 'Status' => 1]);
                $this->synconfig();
                array_push($request, lang('添加'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success('添加成功');
                else return $this->error('添加失败');
            case  'del':
                $version = $request['ID'];
                $row = $db->DeleteRow("CountryCode='$version'");
                $this->synconfig();
                array_push($request, lang('删除'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success('删除成功');
                else return $this->error('删除失败');
            case 'editView':
                $request = request()->request();
                $request['ID'] = trim($request['ID']);
                $request['CountryCode'] = trim($request['CountryCode']);
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('Country_item');
            case 'edit':
                $ID = trim(input('ID'));
                $request['CountryCode'] =trim($request['CountryCode']);
                unset($request['s'], $request['Action'], $request['ID']);
                $row = $db->UPData($request, "CountryCode='$ID'");
                $this->synconfig();
                array_push($request, lang('修改'));
                GameLog::logData(__METHOD__, $request, 1);
                if ($row > 0) return $this->success('修改成功');
                else return $this->error('修改失败');
        }
    }

    //添加字典分类
    public function addDirectory()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            if (!$request['title'] || !$request['name']) {
                return $this->apiReturn(1, [], '参数不能为空');
            }
            $data = [
                'name' => $request['name'],
                'group' => $request['name'],
                'title' => $request['title'],
                'type' => 'array',
                'rule' => 'required',
                'tip' => '',
                'value' => '{}',
                'content' => '',
                'extend' => ''

            ];
            $res = $this->model->insert($data);
            return $this->apiReturn(0, [], '成功');
        }

        return $this->fetch();
    }

    //编辑游戏配置
    public function editGame()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if ($data) {
                $logData = [
                    'userid' => session('userid'),
                    'username' => session('username'),
                    'action' => __METHOD__,
                    'logday' => date('Ymd'),
                    'recordtime' => date('Y-m-d H:i:s'),
                    'request' => json_encode($this->request->post(), JSON_UNESCAPED_UNICODE)
                ];
                $this->doHandle($data, $logData);
            }
        }
    }

    //编辑字典配置
    public function editDict()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if ($data) {
                $logData = [
                    'userid' => session('userid'),
                    'username' => session('username'),
                    'action' => __METHOD__,
                    'logday' => date('Ymd'),
                    'recordtime' => date('Y-m-d H:i:s'),
                    'request' => json_encode($this->request->post(), JSON_UNESCAPED_UNICODE)
                ];
                $this->doHandle($data, $logData);
            }
        }
    }

    //删除字典配置
    public function delCfg()
    {
        if ($this->request->isAjax()) {
            $id = intval(input('id')) ? intval(input('id')) : 0;
            if ($id) {
                $info = Db::name('config')->where(['id' => $id])->find();
                if ($info) {
                    $logData = [
                        'userid' => session('userid'),
                        'username' => session('username'),
                        'action' => __METHOD__,
                        'logday' => date('Ymd'),
                        'recordtime' => date('Y-m-d H:i:s'),
                        'request' => json_encode($info, JSON_UNESCAPED_UNICODE)
                    ];
                    Db::name('config')->where(['id' => $id])->delete();
                    try {
                        $this->refreshFile();
                    } catch (\Exception $e) {

                        $response = ['code' => 1, 'msg' => $e->getMessage()];
                        $logData['response'] = json_encode($response, JSON_UNESCAPED_UNICODE);
                        $logData['status'] = 0;
                        GameLog::log($logData);
                        return $this->apiReturn(1, [], '删除失败');
                    }
                    $response = ['code' => 0, 'msg' => lang('删除成功')];
                    $logData['response'] = json_encode($response, JSON_UNESCAPED_UNICODE);
                    $logData['status'] = 1;
                    GameLog::log($logData);
                    return $this->apiReturn(0, [], '删除成功');
                }
            }

        }
    }

    private function doHandle($data, $logData)
    {
        $response = [];
        $configList = [];
        foreach ($this->model->all() as $v) {
            if (isset($data[$v['name']])) {
                $value = $data[$v['name']];
                if (is_array($value) && isset($value['field'])) {
                    $value = json_encode(ConfigModel::getArrayData($value), JSON_UNESCAPED_UNICODE);
                } else {
                    $value = is_array($value) ? implode(',', $value) : $value;
                }
                $v['value'] = $value;
                $configList[] = $v->toArray();
            }
        }
//        var_dump($configList);
//        die;
        $this->model->allowField(true)->saveAll($configList);
        try {
            $this->refreshFile();
        } catch (\Exception $e) {
            $response = ['code' => 1, 'msg' => $e->getMessage()];
            $logData['response'] = json_encode($response, JSON_UNESCAPED_UNICODE);
            $logData['status'] = 0;
            GameLog::log($logData);
            $this->error($e->getMessage());
        }

        $response = ['code' => 0, 'msg' => lang('更新成功')];
        $logData['response'] = json_encode($response, JSON_UNESCAPED_UNICODE);
        $logData['status'] = 1;
        GameLog::log($logData);
        $this->success('更新成功');
    }

    /**
     * 刷新配置文件
     */
    protected function refreshFile()
    {
        $config = [];
        foreach ($this->model->all() as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array)json_decode($value['value'], TRUE);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(APP_PATH . 'extra' . DS . 'site.php', '<?php' . "\n\nreturn " . var_export($config, true) . ";");
    }


    //转发地址配置
    public function httpurlCfg(){
        if($this->request->isAjax()){
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            $httpurl =new HttpUrlBase();
            $where=[];
            $list =$httpurl->getList($where,$page,$limit,'*','id desc');
            $count =$httpurl->getCount($where);
            return $this->apiReturn(0,$list,'',$count);
        }
        return $this->fetch();
    }

    public function httpurledit(){
        $id= input('Id',0);
        $httpurl =new HttpUrlBase();
        if($this->request->isAjax()){
            $baseurl = input('urlbase');
            $descript = input('descript');

            if(empty($baseurl)){
                return $this->failJSON('url地址不能为空');
            }

            $data= [
                'UrlBase'=>$baseurl,
                'Description'=>$descript
            ];
            $ret = $httpurl->updateById($id,$data);
            if($ret){
                $this->synconfig();
                return $this->successJson([]);
            }
            else
                return $this->failJSON('操作失败');
        }

        $row = $httpurl->getRowById($id);
        $this->assign('id',$id);
        $this->assign('info',$row);
        return $this->fetch();
    }


    //邮件配置
    public function mailConfig(){
        $mailcfg =new MailConfig();
        if($this->request->isAjax()){
            $data = $this->request->post();
            $status = $mailcfg->updateById(1,$data);
            if($status)
                return $this->successJSON([],lang('数据提交成功'));
            else
                return $this->failJSON(lang('数据提交失败'));
        }
        $config = $mailcfg->getRowById(1);
        $this->assign('mail',$config);
        return $this->fetch();
    }


    public function upgradeversion(){
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $upgrade =new UpgradeVerConfig();
        $where=[];
        $list =$upgrade->getList($where,$page,$limit,'*','id desc');
        $count =$upgrade->getCount($where);
        return $this->apiReturn(0,$list,'',$count);
    }


    public function upgradeConfigEdit(){
        $Version = input('Version','');
        $downurl =input('DownUrl','');
        $data =[
            'Version'=>$Version,
            'DownUrl'=>$downurl
        ];
        if($this->request->isAjax())
        {
            $upgrade =new UpgradeVerConfig();
            $upgrade->updateById(1,$data);
            return $this->successJSON('');
        }
        $this->assign('data',$data);
        return $this->fetch();
    }


    //ajust配置信息
    public function ajustConfig(){
        if($this->request->isAjax()){
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $ajustcfg =new  AjustConfig();
            $where=[];
            $list =$ajustcfg->getList($where,$page,$limit,'*','id desc ');
            $count =$ajustcfg->getCount($where);
            return $this->apiReturn(0,$list,'',$count);
        }
        return $this->fetch();
    }

    //ajust 配置
    public function ajustEdit(){
        $action = input('action','');
        $channelid= input('channel_id',0);
        $ajustcfg =new  AjustConfig();
        if($this->request->isAjax()){
            $data = $this->request->param();
            if($action=='edit'){
                unset($data['channel_id']);
                $status=$ajustcfg->updateByWhere(['channel_id'=>$channelid],$data);
                GameLog::logData(__METHOD__, $data, $status, lang('更新ajust配置成功'));
                return $this->successJSON('');
            }
            else{
                $count = $ajustcfg->getCount(['channel_id'=>$channelid]);
                if($count>0){
                    return $this->failJSON('渠道已经存在');
                }
                $status=$ajustcfg->add($data);
                GameLog::logData(__METHOD__, $data, $status, lang('添加ajust配置成功'));
                return $this->successJSON('');
            }

        }
        $detail = $ajustcfg->getDataRow(['channel_id'=>$channelid],'*');
        $this->assign('info',$detail);
        $this->assign('action',$action);
        return $this->fetch();
    }

    //删除ajust配置
    public function deleteAjust()
    {
        if ($this->request->isPost()) {
           $id= input('id',0);
           $status =0;
           if($id>0){
               $ajustcfg =new  AjustConfig();
               $status = $ajustcfg->delRow(['channel_id'=>$id]);
               GameLog::logData(__METHOD__, [$id, lang('删除ajust配置')], $status, lang('删除ajust配置成功'));
               return $this->successJSON([],'删除成功');
           }
            return $this->failJSON('删除失败');
        }
    }

}