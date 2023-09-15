<?php
namespace app\model;

class GiftCardReceive extends BaseModel
{
    protected $table = 'giftcardreceive';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
