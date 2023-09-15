<?php
namespace app\model;

use think\db;
class SystemMail extends BaseModel
{
    protected $table = 'systemmail';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
