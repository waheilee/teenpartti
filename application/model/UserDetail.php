<?php

namespace app\model;

class UserDetail extends BaseModel
{
    protected $table = '[CD_UserDB].[dbo].[Vw_UserDetail]';

    public function __construct($connstr) {
        Parent::__construct($connstr);
    }

    public function getUserDetail($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype, $tblName = '') {
        $strFields = " id,accountname,nickname,gamebalance,score,registertime,lastlogintime,lastloginip,totalin,totalout,totalwater,descript,mobile,locked,proxyid,gmtype,b.countrycode,couponcount,couponprocess ";
        if ($tblName == '') {
            $tableName = $this->table;
            $tableName = $tableName . ' a,(select AccountID,countryCode from CD_Account.dbo.T_Accounts) b,(select roleid,CouponCount,CouponProcess from CD_UserDB.dbo.T_RoleExpand) c';
        } else
            $tableName = $tblName;

        $strwhere = $strwhere . " and a.id = b.AccountID and a.id=c.RoleID ";
        $list = $this->getBigPage($tableName, $strFields, $strwhere, $OrderField, $PageIndex, $pagesize, $ordertype);

//        if (isset($list['list']) && $list['count'] > 0) {
//            foreach ($list['list'] as &$v) {                //盈利
//                $v['totalget'] = $v['totalin'] - $v['totalout'];
//                $v['lastloginip'] = $v['lastloginip'] . '(' . getIPcode($v['lastloginip']) . ')';
//                //活跃度
//                $v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;
//                $logintype = $v['gmtype'];
//                if ($logintype == 0)      $v['gmtype'] = '游客';
//                else if ($logintype == 1) $v['gmtype'] = 'Google';
//                else if ($logintype == 2) $v['gmtype'] = 'Facebook';
//                else if ($logintype == 3) $v['gmtype'] = 'IOS';

//            }
        unset($v);
//        }
        return $list;
    }
}
