<?php
namespace app\model;

use think\db;
class RobotNum extends BaseModel
{
    protected $table = 'couponchangelogs a, (select accountid,accountname from [cd_account].dbo.t_accounts) b';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
}
