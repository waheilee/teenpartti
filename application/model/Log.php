<?php

namespace app\model;


class Log extends CommonModel
{
    protected $table = 'log';

    //客服日志
    public function GetServcieLogList()
    {
        $username = input('username');
        $controller = input('controller');
        $method = input('method');
        $start = input('start');
        $end = input('end');
        $page = input('page');
        $limit = input('limit');
        $content = input('content');
        $where = "1=1 ";
        switch (input('logType')) {
            case 'Servcie':
                if (!$controller) $where .= " AND action like '%Player::%' or   action like '%CustomerServiceSystem::%' ";
                break;
            case 'Ctrllog':
                if (!$method) $where .= " AND action like '%::setPlayerRate' or action like '%::setTigerProfit' or action like '%::setRoomTigerRate' or action like '%::setProfit'";
                break;
        }
        if ($content) $where .= " AND request like  '%$content%'";
        if ($username) $where .= " AND username like '%$username%'";
        if ($controller) $where .= " AND action like '%$controller::$method%'";
        if ($method) $where .= " AND action like '%::$method' ";
        if ($start) {
            if ($end && $end > $start) {
                $where .= " AND logday BETWENT '$start' AND '$end'";
            } else {
                $where .= " AND logday='$start'";
            }
        }
        $data = [
            'code' => 0,
            'msg' => '',
            'count' => 0,
            'data' => []
        ];
        $count = $this->getCount($where);
        if (!$count) return $data;
        $list = $this->getList($where, $page, $limit, '*', ['id' => 'desc']);
        foreach ($list as &$v) {
            $arr = explode('::', $v['action']);
            //按\分隔，最后一个是操作控制器
            // $controller = explode('\\', $arr[0]);
            // $controller = $controller[count($controller) - 1];
            //获取中文名
            // $controllerName = isset(config('site.controller')[$controller]) ? config('site.controller')[$controller] : $controller;

            // $controllerName = str_replace('<br>','',$controllerName);
            // $controllerName = lang($controllerName);
            // $v['controller'] = $controllerName;
            $methodName = isset(config('classes')[$controller][$arr[1]]) ? config('classes')[$controller][$arr[1]] : $arr[1];
            $v['method'] = $methodName;
            switch ($v['method']) {
                case 'setProfit':
                    $v['controller'] = '游戏设置';
                    break;
                case 'setPlayerRate':
                    $v['controller'] = '个体玩家控制';
                    break;
                case 'setRoomTigerRate':
                    $v['controller'] = '老虎机一键设置';
                    break;
                case 'setTigerProfit':
                    $v['controller'] = '老虎机单款设置';
                    break;
                case 'setDm':
                    $v['controller'] = '设置打码';
                    break;
                case 'relation':
                    $v['controller'] = '修改上级ID';
                    break;
                case 'ResetPwd':
                    $v['controller'] = '修改密码';
                    break;
                case 'forceQuit':
                    $v['controller'] = '玩家强退';
                    break;
                case 'addCommnet':
                    $v['controller'] = '添加玩家备注';
                    break;
                default:
                    # code...
                    break;
            }
            if (isset(config('dict')[$controller][$arr[1]])) {
                $request = json_decode($v['request'], true);
                $request = array_values($request);
                $content = config('dict')[$controller][$arr[1]];
                for ($i = 0; $i < count($request); $i++) {
                    $thisdata = isset($request[$i]) ? $request[$i] : '';
                    if (is_array($thisdata)) {
                        $thisdata = json_encode($thisdata);
                    }
//                        $content = str_replace('{ct' . $i . '}', $thisdata, $content); //{ct0}{ct1}
                    $content = str_replace('{' . $i . '}', $thisdata, $content);//新格式 {0}{1}
                }
                $v['content'] = $content;
            } else {
                $v['content'] = $v['request'];
            }
        }
        unset($v);
        $data['data'] = $list;
        $data['count'] = $count;
        return $data;
    }

}