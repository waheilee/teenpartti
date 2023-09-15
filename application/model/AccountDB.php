<?php

namespace app\model;

class AccountDB extends BaseModel
{
    protected $table = 'T_Accounts';


    public function __construct($connstr = '')
    {
        if (empty($connstr)) {
            $connstr = $this->AccountDB;
        }
        Parent::__construct($connstr);
    }

    public function TAccounts()
    {
        $this->table = 'T_Accounts';
//        $this->table=$this->TAccounts;
        return $this;
    }

    /**
     * 获取某日注册用户数
     */
    public function GetDailyRegistrCountByDay($day)
    {
        $table = 'T_Accounts';
        $start = date('Y-m-d 00:00:00', strtotime($day));
        $end = date('Y-m-d 23:59:59', strtotime($day));
        $sqlQuery = "SELECT COUNT(AccountID) as count FROM " . $table . " WHERE RegisterTime >= '" . $start . "' and RegisterTime < '" . $end . "'";
        $data = $this->connection->query($sqlQuery);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    /**
     * 获取某日注册用户数 不含游客
     */
    public function GetDailyRegistrCountByDay2($day)
    {
        $table = 'T_Accounts';
        $start = date('Y-m-d 00:00:00', strtotime($day));
        $end = date('Y-m-d 23:59:59', strtotime($day));
        $sqlQuery = "SELECT COUNT(AccountID) as count FROM " . $table . " WHERE GmType not in(0) and RegisterTime >= '" . $start . "' and RegisterTime < '" . $end . "'";
        $data = $this->connection->query($sqlQuery);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    public function GetCountryByRoleID(&$RoleID)
    {
        $this->table = 'T_Accounts';
        $result= $this->GetRow("AccountID=$RoleID",  'countryCode');
        if (empty($result)) return ['status'=>0];
        $result['status']=1;
        return $result;
    }

}
