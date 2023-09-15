<?php
namespace app\model;

class ActivityRecord extends BaseModel
{
    protected $table = 'T_ActivityCollectRecord';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }

    public function getActivityReceiveSum(){
    	$this->table = 'View_ActivityReceiveSum';
    	return $this;
    }

    public function getActivityChannelReceiveSum(){
    	$this->table = 'View_ActivityChannelReceiveSum';
    	return $this;
    }
}