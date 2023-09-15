<?php
namespace app\model;

use think\db;
class UserTransaction extends BaseModel
{
    protected $table = '[cd_userdb].[dbo].[t_usertransaction]';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getUserTransaction($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$strFields = "transactionno,commodityname,accountid,realmoney,virtualgold,addtime,platformtype,cdytype,id";
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0) {
			foreach ($list['list'] as &$v) {
				//盈利
				$v['totalget'] = $v['totalin'] - $v['totalout'];
				$v['lastloginip'] = $v['lastloginip'] . '(' . getIPcode($v['lastloginip']) . ')';
				//活跃度
				$v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;

				$logintype = $v['gmtype'];
				if($logintype==0){
					$v['gmtype'] =lang('游客');
				}
				else if($logintype==1){
					$v['gmtype'] ='Google';
				}
				else if($logintype==2){
					$v['gmtype'] ='Facebook';
				}
				else if($logintype==3){
					$v['gmtype'] ='IOS';
				}
			}
			unset($v);
		}
		return $list;
	}
}
