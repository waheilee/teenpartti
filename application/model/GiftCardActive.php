<?php
namespace app\model;

class GiftCardActive extends BaseModel
{
    protected $table = 'giftcardactive';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
