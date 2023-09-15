<?php
namespace app\model;

use think\db;
class ScoreLog extends BaseModel
{
    protected $table = 'T_ScoreChangeLogs a, (select AccountID,AccountName from [CD_Account].dbo.T_Accounts) b';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getScoreLog($changeType,$strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$strFields = "roleid,AccountName,changetype,ChangeScore,PreScore,LastScore,addtime,[description]";
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0) {
			foreach ($list['list'] as &$v) {
				$v['PreScore'] = sprintf('%.2f', $v['PreScore']);
				$v['LastScore'] = sprintf('%.2f', $v['LastScore']);
				$v['ChangeScore'] = sprintf('%.2f', $v['LastScore'] - $v['PreScore']);
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
