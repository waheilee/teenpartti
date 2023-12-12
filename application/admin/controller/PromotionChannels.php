<?php

namespace app\admin\controller;

use app\model\MasterDB;

class PromotionChannels extends Main
{

    public function list()
    {
        if ($this->request->isAjax()) {
            $page = input('page');
            $limit = input('limit');
            $masterDB = new MasterDB();
            $count = $masterDB->getTableObject('T_PixelID')
                ->count();
            $list = $masterDB->getTableObject('T_PixelID')
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
        $platform = input('Platform','');
        $ic = input('ic','');
        $key = input('key','');
        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();
            $data = [
                'Platform' => $platform,
                'RoleId' => $ic,
                'Key' => $key
            ];
            $add = $masterDB->getTableObject('T_PixelID')
                ->where('RoleId',$ic)
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
        $platform = input('Platform','');
        $ic = input('ic','');
        $key = input('key','');
        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();
            $data = [
                'Platform' => $platform,
                'RoleId' => $ic,
                'Key' => $key
            ];
            $roleId = $masterDB->getTableObject('T_PixelID')
                ->where('RoleId',$ic)
                ->find();

            if (!empty($roleId)){
                return $this->apiReturn(1, '', 'ic:'.$roleId['RoleId'].'已存在');
            }

            $add = $masterDB->getTableObject('T_PixelID')
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
        $RoleId = input('RoleId','');
        $masterDB = new MasterDB();

        try {
            $masterDB->startTrans();

            $add = $masterDB->getTableObject('T_PixelID')
                ->where('RoleId',$RoleId)
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