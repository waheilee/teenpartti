<?php

namespace app\admin\controller;

use app\model\DataChangelogsDB;
use app\model\MasterDB;
use think\exception\PDOException;
use think\response\Json;
use think\View;

/**
 * Class PkMatch
 * @package app\admin\controller
 */
class PkMatch extends Main
{

    /**
     * 开关配置视图
     * @return Json|View|void
     */
    public function PkConfig()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->PKmatchConfg();
                return $this->apiJson($result);
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('config_edit');
            case 'edit':
                $request = request()->request();
                $RoomID = $request['RoomId'];
                unset($request['s'], $request['Action'], $request['RoomId']);
                $db = new  MasterDB();
                $row = $db->TPKMatchRoomCfg()->UPData($request, "RoomId=$RoomID");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
            case 'addView':
                $request = request()->request();
                $request['Action'] = 'add';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('config_edit');
            case 'add':
                $request = request()->request();
                unset($request['s'], $request['Action']);
                $db = new  MasterDB();
                $row = $db->TPKMatchRoomCfg()->Insert($request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');
        }
        return $this->fetch();

    }

    /**
     * 连胜奖励配置
     * @return mixed|Json|void
     * @throws PDOException
     */
    public function PKContinueWinConfig()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->TPKContinueWinConfig()->GetPage();
                return $this->apiJson($result);
            case 'addView':
                $request = ['ID' => null, 'WinCount' => null, 'Money' => null, 'Action' => 'add'];
                $this->assign('info', $request);
                return $this->fetch('Winning_streak_edit');
            case 'add':
                $request = request()->request();
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->TPKContinueWinConfig()->Insert($request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                return $this->error('添加失败');
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('Winning_streak_edit');
            case 'edit':
                $request = request()->request();
                $ID = $request['ID'];
                unset($request['s'], $request['Action'], $request['ID']);
                $db = new  MasterDB();
                $row = $db->TPKContinueWinConfig()->UPData($request, "ID=$ID");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
        }

    }

    /**
     * 低注配置
     * @return Json|void
     * @throws PDOException
     */
    public function PKBetValueConfig()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->TGameSingleBetCfg()->GetPage('AND KindID >10000');
                return $this->apiJson($result);
            case 'edit':
                $request = request()->request();
                $id = $request['KindId'];
                $data = ['SingleBetValue' => $request['SingleBetValue']];
                $db = new  MasterDB();
                $row = $db->TGameSingleBetCfg()->UPData($data, "KindId=$id");
                $this->synconfig();
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
        }
    }


    /**
     * 连胜防刷配置
     * @return Json|void
     * @throws PDOException
     */
    public function PKWinRobotConfig()
    {
        switch (input('Action')) {
            case 'config':
                $db = new  MasterDB();
                $result = $db->PKWinRobotConfig()->GetPage();
                return $this->apiJson($result);
            case 'editView':
                $request = request()->request();
                $request['Action'] = 'edit';
                unset($request['s']);
                $this->assign('info', $request);
                return $this->fetch('Robot_config');
            case 'edit':
                $request = request()->request();
                $ID = $request['ID'];
                unset($request['s'], $request['Action'],$request['ID']);
                $data = $request;
                $db = new  MasterDB();
                $row = $db->PKWinRobotConfig()->UPData($data, "ID=$ID");
                if ($row > 0) return $this->success('更新成功');
                return $this->error('更新失败');
        }
    }

    /**
     * 统计报表
     * @return mixed|Json
     * @throws PDOException
     */
    public function PkMatchDayReport()
    {

        switch (input('Action')) {
            case 'list':
                $db = new DataChangelogsDB();
                $Robot=(int)input('Robot');
                $result = $db->PkMatchDayDataDay($Robot);
                return $this->apiJson($result);
        }
        return $this->fetch();
    }

    /**
     * 明细报表
     * @return mixed|Json
     */
    public function PkMatchInfoReport()
    {
        switch (input('Action')) {
            case 'list':
                $db = new DataChangelogsDB();
                $result = $db->PkMatchDataInfo();
                return $this->apiJson($result);
        }
        return $this->fetch();
    }
    

}