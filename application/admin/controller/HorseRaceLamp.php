<?php

namespace app\admin\controller;

use app\model\MasterDB;

class HorseRaceLamp extends Main
{

    public function list()
    {
        if ($this->request->isAjax()) {
            $page = input('page');
            $limit = input('limit');
            $masterDB = new MasterDB();
            $count = $masterDB->getTableObject('T_TrottingMsg')
                ->count();
            $list = $masterDB->getTableObject('T_TrottingMsg')
                ->page($page, $limit)
                ->select();
            $data['count'] = $count;
            $data['list'] = $list;
            return $this->apiJson($data);
        }
        return $this->fetch();
    }

    public function edit()
    {
        $id = input('id','');
        $platform = input('TrottingMsg','');
        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();
            $data = [
                'TrottingMsg' => trim($platform),
            ];
            $add = $masterDB->getTableObject('T_TrottingMsg')
                ->where('ID',$id)
                ->update($data);
            // 提交事务
            $masterDB->commit();
            if ($add) {
                $this->synconfig();
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (\Exception $e) {
            save_log('PromotionChannels', '===' . $e->getMessage() . $e->getTraceAsString() . $e->getLine());
            // 回滚事务
            $masterDB->rollback();
            return $this->apiReturn(1, '', '添加操作失败');
        }
    }

    public function create()
    {
        $platform = input('TrottingMsg','');

        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();
            $data = [
                'TrottingMsg' => trim($platform),
            ];
            $add = $masterDB->getTableObject('T_TrottingMsg')
                ->insert($data);
            // 提交事务
            $masterDB->commit();
            if ($add) {
                $this->synconfig();
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (\Exception $e) {
//            save_log('PromotionChannels', '===' . $e->getMessage() . $e->getTraceAsString() . $e->getLine());
            // 回滚事务
            $masterDB->rollback();
            return $this->apiReturn(1, '', '添加操作失败');
        }
    }

    public function delete()
    {
        $id = input('id','');
        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();

            $add = $masterDB->getTableObject('T_TrottingMsg')
                ->where('ID',$id)
                ->delete();
            // 提交事务
            $masterDB->commit();
            if ($add) {
                $this->synconfig();
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        } catch (\Exception $e) {
//            save_log('PromotionChannels', '===' . $e->getMessage() . $e->getTraceAsString() . $e->getLine());
            // 回滚事务
            $masterDB->rollback();
            return $this->apiReturn(1, '', '添加操作失败');
        }
    }
}