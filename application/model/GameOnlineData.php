<?php
namespace app\model;

use think\db;
class GameOnlineData extends BaseModel
{
    protected $table = '[OM_GameOC].[dbo].[Proc_RoomOnlineData_Select]';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
}
