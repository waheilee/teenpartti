<?php
namespace app\model;

use think\db;
class GameLog extends BaseModel
{
    protected $table = '[CD_DataChangelogsDB].[dbo].[T_UserGameChangeLogs]';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getGameLog($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$strFields = "roleid,rolename,serverid as roomid,roomtype,serialnumber,changetype,kindid,[money],lastmoney,addtime,tax,gameroundrunning";
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0) {
			foreach ($list['list'] as &$v) {
				$v['premoney'] = sprintf('%.2f', $v['lastmoney'] +$v['tax'] - $v['money'] );
				$v['awardmoney'] = sprintf('%.2f', $v['gameroundrunning'] + $v['money']);
				$v['gameroundrunning'] = sprintf('%.2f', $v['gameroundrunning']);
				$v['money'] = sprintf('%.2f', $v['money']);
				$v['freegame'] = '否';
				if (floatval($v['gameroundrunning']) == 0) {
					$v['freegame'] = '是';
				}
				$v['lastmoney'] = sprintf('%.2f',$v['lastmoney'] );
			}
			/*原有的统计，目前页面上没有使用，保留
			$sumdata = [
                'win' => isset($res['data']['win']) ? $res['data']['win'] : 0,
                'sum' => isset($res['data']['sum']) ? $res['data']['sum'] : 0,
                'lose' => isset($res['data']['lose']) ? $res['data']['lose'] : 0,
                'escape' => isset($res['data']['escape']) ? $res['data']['escape'] : 0,
                'totaltax' => isset($res['data']['totaltax']) ? $res['data']['totaltax'] : 0,
            ];*/
			unset($v);
		}
            
		return $list;
	}
}
