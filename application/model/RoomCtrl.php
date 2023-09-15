<?php
namespace app\model;

use think\db;
class RoomCtrl extends BaseModel
{
    protected $table = '[om_masterdb].[dbo].[t_gameroominfo] r left join [om_masterdb].[dbo].[t_gameserverinfo] s on r.serverid=s.serverid';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getRoomCtrl($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = '')
	{
		$strFields = "roomid,kindid,roomtype,roomname,maxtablecount,maxplayercount,serverip,tableschemeid, (select kindname+'('+ cast(kindid as varchar(10))+')' from [OM_MasterDB].[dbo].[T_GameKind] where kindid=R.KindID) as kindname,(SELECT schemename FROM [OM_MasterDB].[dbo].[T_GameTableScheme] where tableschemeid=R.TableSchemeId) as schemename";
		
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
            
		return $list;
	}
}
