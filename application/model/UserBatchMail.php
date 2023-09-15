<?php
namespace app\model;

use think\db;
class UserBatchMail extends BaseModel
{
    protected $table = 'userbatchmail';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->GameOC;
        }
        Parent::__construct($connstr);
    }



}
