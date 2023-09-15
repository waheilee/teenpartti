<?php
namespace app\model;

use think\db;
class SysConfig extends BaseModel
{
    protected $table = 'sysconfig';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->GameOC;
        }
        Parent::__construct($connstr);
    }



}
