<?php
namespace app\model;

use think\db;
class UpgradeVerConfig extends BaseModel
{
    protected $table = 'upgradeverconfig';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->GameOC;
        }
        Parent::__construct($connstr);
    }



}
