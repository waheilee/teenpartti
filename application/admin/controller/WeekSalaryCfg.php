<?php

namespace app\admin\controller;

use app\model\MasterDB;

class WeekSalaryCfg extends Main
{

    public function list()
    {
        if ($this->request->isAjax()) {
//            $page = intval(input('page')) ? intval(input('page')) : 1;
//            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $masterDb = new MasterDB();
            $count = $masterDb->getTableObject('T_WeekSalaryCfg')
                ->count();
            $lists = $masterDb->getTableObject('T_WeekSalaryCfg')
                ->select();
            return $this->apiReturn(0, $lists, 'success', $count);

        }
        return $this->fetch();




    }

    public function addWeekSalary()
    {

        return $this->fetch();
    }

    public function create()
    {
        $running = input('Running');
        $baseWeekSalary = input('BaseWeekSalary');
        $lv1Rate = input('Lv1Rate');
        $lv2Rate = input('Lv2Rate');
        $level = input('Level');
        $data = [
            'Running' => $running,
            'BaseWeekSalary' => $baseWeekSalary,
            'Lv1Rate' => $lv1Rate,
            'Lv2Rate' => $lv2Rate,
            'Level' => $level,
        ];
        $masterDb = new MasterDB();
        $masterDb->getTableObject('T_WeekSalaryCfg')->insert($data);
        return $this->successJSON('');
    }

    public function editWeekSalary()
    {
        $id = input('id');
        $masterDb = new MasterDB();
        $info = $masterDb->getTableObject('T_WeekSalaryCfg')->where('ID',$id)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }
    public function update()
    {
        $id = input('id');
        $running = input('Running');
        $baseWeekSalary = input('BaseWeekSalary');
        $lv1Rate = input('Lv1Rate');
        $lv2Rate = input('Lv2Rate');
        $level = input('Level');
        $data = [
            'Running' => $running,
            'BaseWeekSalary' => $baseWeekSalary,
            'Lv1Rate' => $lv1Rate,
            'Lv2Rate' => $lv2Rate,
            'Level' => $level,
        ];
        $masterDb = new MasterDB();
        $masterDb->getTableObject('T_WeekSalaryCfg')
            ->where('ID',$id)->update($data);
        return $this->successJSON('');
    }

    public function delete()
    {
        $id = input('id');


        $masterDb = new MasterDB();
        $masterDb->getTableObject('T_WeekSalaryCfg')
            ->where('ID',$id)->delete();
        return $this->successJSON('');
    }
}