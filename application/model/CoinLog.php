<?php
namespace app\model;

use think\db;
class CoinLog extends BaseModel
{
    protected $table = 't_bankwealthchangelogs a, (select accountid,accountname from [cd_account].dbo.t_accounts) b ';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getCoinLog($changeType,$strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$strFields = "roleid,AccountName,changetype,changemoney,chargeaward,balance,addtime,Description,ClientIP";
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0) {
			foreach ($list['list'] as &$v) {
				$v['premoney'] = sprintf('%.2f', $v['balance'] - $v['changemoney']);
				$v['balance'] = sprintf('%.2f', $v['balance']);
				$v['changemoney'] = sprintf('%.2f', $v['changemoney']);
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
