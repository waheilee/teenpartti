<?php

namespace app\admin\controller;
//游戏盘控
use app\common\GameLog;
use app\model\MasterDB;
use app\model\OperationLogsDB;
use app\model\UserDB;
use think\Cache;


class GamePlatecontrol extends Main
{

    /**
     * 曲线图数据
     * @return mixed
     */
    public function Graph()
    {
        //读取缓存
        if (Cache::has('PKGraph')) {
            $result = Cache::get('PKGraph');
            return $this->apiJson($result);
        }
        $DB = new UserDB("T_UserSystemCtrlData");
        //1分钟2条数据  1小时=120 1天=2880
        $result = $DB->getTablePage(null, 1, 180, ' AND ID%720=0 ', "id", 'Desc',
            'TotalOutput,TotalWater,TotalProfit,RoomCtrlValue,AddTime');
        $result['list'] = array_reverse($result['list']);

        if (isset($result['list']) && $result['list']) {
            foreach ($result['list'] as $v) {
                $res[0][] = substr($v['AddTime'], 0, 19);
                $res[1][] = $v['TotalOutput'] / bl;
                $res[2][] = $v['TotalProfit'] / bl;
                $res[3][] = $v['RoomCtrlValue'] / 2;
            }
            $result['list'] = $res;
            unset($v);
            unset($res);

            //缓存30秒
            Cache::set('PKGraph', $result, 120);
            return $this->apiJson($result);
//            return $this->apiReturn($result['code'], $res, $result['message'] = "");

        }
        return $this->apiReturn(0, ['dates' => null, 'numbers' => null, 'numbers2' => null], '接口数据获取失败,或连接超时');


    }

    /**
     * @return mixed
     */
    public function index()
    {
        
        
        $action = input('Action');
        $result = null;
        $request = $this->request;
        $other = self::getLastCtrlData();
        $other['CurWaterIn'] /= bl;
        $other['CurWaterOut'] /= bl;
        $other['TotalOutput'] /= bl;
        $other['TotalWater'] /= bl;
        $other['TotalProfit'] /= bl;
        $other['TotalTax'] /= bl;
        $temp = bcsub($other['TotalOutput'],$other['TotalTax'],3);
        $rtp =0;
        if($other['TotalWater']>0)
            $rtp =bcdiv( $temp,$other['TotalWater'],3)*100;
        $other['rtp'] =$rtp.'%';
        $this->assign("res", $other);

        $m = new UserDB;
        $sqlExec = "exec P_Last_SystemCtrlData_Select";
        $ss = $m->getTableQuery($sqlExec);
        if ($ss) {
            $ss = $ss[0][0];
            $ss['TotalOutput'] /= bl;
            $ss['TotalWater'] /= bl;
            $ss['TotalProfit'] /= bl;
            $ss['TotalTax'] /= bl;
        } else {
            $ss['TotalOutput'] =0;
            $ss['TotalWater'] = 0;
            $ss['TotalProfit'] = 0;
            $ss['TotalTax'] = 0;
        }
        
        $ssinfo = [];
        $ssinfo['TotalOutput'] = bcsub($other['TotalOutput'],$ss['TotalOutput'],3);
        $ssinfo['TotalWater'] = bcsub($other['TotalWater'],$ss['TotalWater'],3);
        $ssinfo['TotalProfit'] = bcsub($other['TotalProfit'],$ss['TotalProfit'],3);
        $ssinfo['TotalTax'] = bcsub($other['TotalTax'],$ss['TotalTax'],3);
        if ($ssinfo['TotalWater'] <= 0) {
            $ssinfo['rtp'] = "0%";
        } else {
            $ssinfo['rtp'] = bcdiv(bcsub($ssinfo['TotalOutput'],$ssinfo['TotalTax'],3),$ssinfo['TotalWater'],3)*100 .'%';
        }
        
        $this->assign("ss", $ssinfo);
        
        switch ($action) {
            case "list": //基础信息
                $DB = new MasterDB();
                $result = $DB->GameConfig()->GetPage(" and Description like '%盘控%'",'','*',true);
                break;

        }

        if ($request->isAjax()) {
            return $this->apiReturn(
                isset($result['code']) ? 0 : 0,
                isset($result['list']) ? $result['list'] : [],
                "查询成功",
                isset($result['count']) ? $result['count'] : 0

            );
        }
        return $this->fetch();
    }


    /**
     * 返回最后一条盘控数据
     * @return array
     */
    public function getLastCtrlData($field = null)
    {
        $DB = new UserDB("T_UserSystemCtrlData");
        return $DB->TUserSystemCtrlData()->GetRow(null, $field, ["id" => "Desc"]);
    }

    //收放水操作 如果  $WaterIn  $WaterOut 不等于0 不允许操作

    /**
     * @return mixed
     */
    public function PlatecontrolItemAdd()
    {
        $request = request();

        if ($request->isPost()) {
            $WaterOut = -1;
            $WaterIn = -1;
            $Water = intval(input('Water', 0));
            $WaterType = (int)input('Watertype');
            switch ($WaterType) {
                case 0: /*收*/
                    $WaterIn = $Water * bl;
                    break;
                case 1:/*放*/
                    $WaterOut = $Water * bl;
                    break;
                case 3:/*reset*/
                    $WaterIn=0;
                    $WaterOut=0;
                    break;
            }
            //判断 之前的操作是否结束
            //获取数据
//            $result = self::getLastCtrlData("CurWaterIn,CurWaterOut,ID");
//            if (((int)$result['CurWaterIn'] | $result['CurWaterOut']) == 0) {
            $res = $this->sendGameMessage("SeMDSystemCtrlWater", [$WaterIn, $WaterOut]);
            $resutl['code'] = unpack("cchars/nint", $res)['int'];
            if ($resutl['code'] == 0) $resutl['msg'] = lang('操作成功');
            else $resutl['msg'] = lang('操作失败');
            //插入日志表
            $data = ['CheckUser' => session('username'), 'Type' => $WaterType, 'Water' => $Water];
            $DB = new OperationLogsDB();
            $DB->TSystemCtrlWaterLog()->Insert($data);
//           } else                 $resutl['msg'] = '盘控未结束不允许有新的操作';
            return $this->apiReturn(isset($resutl['code']) ? $resutl['code'] : 0, [], $resutl['msg'], 0);
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res['message']);
        }
        return $this->fetch();

    }

    /**
     * @return mixed
     */
    public function PlatecontrolItemEdit()
    {
        $request = $this->request;
        $ID = intval(input('ID')) ? intval(input('ID')) : 0;
        if ($request->isGet() && $ID > 0) {
            $DB = new MasterDB();
            $res = $DB->TSystemCtrlConfig()->GetRow(["ID" => $ID]);
            $this->assign("LimitDown", $res["LimitDown"]);
            $this->assign("LimitUp", $res["LimitUp"]);
            $this->assign("WinRate", $res["WinRate"]);
        }

        if ($request->isPost()) {
            $ID = intval(input('ID')) ? intval(input('ID')) : 0;
            $data["LimitDown"] = intval(input('LimitDown')) ? intval(input('LimitDown')) : 0;
            $data["LimitUp"] = intval(input('LimitUp')) ? intval(input('LimitUp')) : 0;
            $data["WinRate"] = intval(input('WinRate')) ? intval(input('WinRate')) : 0;

            $DB = new MasterDB();
            $count = $DB->TSystemCtrlConfig()->UPData($data, "ID=$ID");

            if ($count > 0) {
                $res['code'] = 0;
                $res['message'] = lang("更新成功");
            } else {
                $res['code'] = -1;
                $res['message'] = lang("更新失败");
            }
            GameLog::logData(__METHOD__, $request, $res['code'], $res['message']);
//            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res['message']);
            return $this->apiReturn($res['code'], [], $res['message'], 0);
        }
        return $this->fetch();

    }

    public function PlatecontrolLog()
    {
        if ($this->request->isAjax()) {
            $CheckUser = input('txtsearch', null);
            $type = input('type', -1);
            $startTime = input('startTime');
            $endTime = input('endTime');
            $where = "";
            if (!IsNullOrEmpty($CheckUser)) $where .= " AND CheckUser='$CheckUser' ";
            if (!IsNullOrEmpty($startTime) && !IsNullOrEmpty($endTime)) $where .= " AND InsertTime BETWEEN '$startTime' AND '$endTime' ";
            if ($type >= 0) $where .= " AND Type = $type ";
            //拉取数据
            $DB = new OperationLogsDB(null, "SystemCtrlWaterLog");
            $resutl = $DB->getTablePage(null, input('page', 1), input('limit', 15), $where, "ID", "desc");
            return $this->apiJson($resutl);
//            return $this->apiReturn(isset($resutl['code']) ? $resutl['code'] : 0,
//                isset($resutl['list']) ? $resutl['list'] : [],
//                $resutl['msg'] = 0, 0);
        }
        return $this->fetch();

    }

}