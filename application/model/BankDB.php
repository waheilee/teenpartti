<?php
namespace app\model;
class BankDB extends BaseModel
{
    protected $table = 'userbank';

    const DRAWBACK_STATUS_WAIT_PAY = 0; // 未付款
    const DRAWBACK_STATUS_AUDIT_PASS = 1; // 审核通过
    const DRAWBACK_STATUS_REFUSE_AND_RETURN_COIN = 2; // 拒绝并退还金币
    const DRAWBACK_STATUS_THIRD_PARTY_HANDLE_FAILED = 3; // 第三方失败
    const DRAWBACK_STATUS_THIRD_PARTY_HANDLING = 4; // 第三方处理中
    const DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN = 5; // 处理失败并退还金币
    const DRAWBACK_STATUS_ORDER_COMPLETED = 100; // 订单完成

    /**
     * UserBank constructor.
     * @param string $connstr 连接的数据库
     * @param $tableName      连接的数据表
     */
    public function __construct($connstr = '') {
        if (empty($connstr)) {
            $connstr = $this->BankDB;
        }
        Parent::__construct($connstr);
    }
    public function TUserDrawBack(){
        $this->table='userdrawback';
        return $this;
    }
    
    
    /**
     * 统计某天提现
     * @return int
     */
    public function CountTotalDrawBackByDay($day)
    {
        $start_day = date('Y-m-d 00:00:00', strtotime($day));
        $end_day = date('Y-m-d 00:00:00', strtotime("+1 day", strtotime($day)));
        $table = "UserDrawBack";
        $fields = " ISNULL(sum(iMoney), 0) as count ";
        $filter = ' where IsDrawback = ' . $this::DRAWBACK_STATUS_ORDER_COMPLETED
                . ' AND UpdateTime >= \'' . $start_day . '\' and UpdateTime <  \'' . $end_day . '\'';
        $data = $this->DBQuery($table, $fields, $filter);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }
}
