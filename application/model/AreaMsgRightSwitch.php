<?php
namespace app\model;

use think\db;
class AreaMsgRightSwitch extends BaseModel
{
    protected $table = 'areamsgrightswitch';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
