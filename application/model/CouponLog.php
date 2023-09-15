<?php
namespace app\model;

use think\db;
class CouponLog extends BaseModel
{
    protected $table = 't_couponchangelogs a, (select accountid,accountname from [cd_account].dbo.t_accounts) b';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getCouponLog($changeType,$strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$strFields = "roleid,AccountName,changetype,ChangeCoupon,PreCoupon,LastCoupon,addtime,[description]";
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0 ) {
                foreach ($list['list'] as &$v) {
                    $v['PreCoupon'] = sprintf('%.2f', $v['PreCoupon']);
                    $v['LastCoupon'] = sprintf('%.2f', $v['LastCoupon']);
                    $v['ChangeCoupon'] = sprintf('%.2f', $v['LastCoupon'] - $v['PreCoupon']);
                    foreach ($changeType as $k2 => $v2) {
                        if ($v['changetype'] == $k2) {
                            $v['changename'] = $v2;
                            break;
                        }
                    }
                }
                unset($v);
            }
		return $list;
	}
}
