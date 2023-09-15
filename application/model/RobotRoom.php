<?php
namespace app\model;

use think\db;
class RobotRoom extends BaseModel
{
    protected $table = '[om_masterdb].[dbo].[t_roomrobot] a, (select roomid,roomname,nullity from [om_masterdb].[dbo].[t_gameroominfo]) b';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getRobotRoom($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = '')
	{
		$strFields = "a.roomid,roomname,maxcount,robotwinweighted,robotwinmoney,servicetables";
		
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
            
		return $list;
	}
}
