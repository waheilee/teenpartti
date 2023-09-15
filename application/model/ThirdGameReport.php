<?php

namespace app\model;

class ThirdGameReport extends BaseModel
{
    protected $table = 'thirdgamereport';

    public function __construct($tableName = '')
    {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->DataChangelogsDB);
    }

    public function GetGameReport()
    {
        $this->table = 'T_ThirdGameReport';
        $orderid = input('orderid', '');
        $roleid = input('roleid', '');
        $gametype = input('gametype', '');
        $start = input('start') ? input('start') : '';//开始时间
        $end = input('end') ? input('end') : '';
        $where = "";
        if ($orderid) $where .= " AND OrderId=$orderid";
        if ($roleid) $where .= " AND RoleId=$roleid";
        if ($gametype) $where .= " AND GameType=$gametype";
        if (!empty($start) && empty($end)) $where .= " AND addtime> $start";
        else if (empty($start) && !empty($end)) $where .= " AND addtime<$end";
        else if (!empty($start) && !empty($end)) $where .= " and addtime  between '$start' and '$end'";
        $result = $this->GetPage($where, "AddTime desc");
        foreach ($result['list'] as $k => &$v) {
            $v['Deposit'] = FormatMoney($v['Deposit']);
            $v['PayOut'] = FormatMoney($v['PayOut']);
            $v['Profit'] = FormatMoney($v['Profit']);
            $v['AddTime'] = substr( $v['AddTime'], 0,19);
            $v['UpdateTime'] = substr( $v['UpdateTime'], 0,19);

            if ($v['GameType'] == 10) $v['GameName'] = lang('捕鱼');
            else                 $v['GameName'] = 'IMOne';
        }
        $str_sql = "select gametype,sum(deposit) as deposit,sum(payout) as payout,sum(profit) as profit from  T_ThirdGameReport where 1=1 $where group by gametype";
        $detail = $this->getTableQuery($str_sql);
        $zero = ['gametype' => 10, 'deposit' => 0, 'payout' => 0, 'profit' => 0];
        $one = $zero;
        $one['gametype'] = 20;
        if (empty($detail)) $detail = [$zero, $one];
        else {
            foreach ($detail as $k => &$v) {
                $v['deposit'] = FormatMoney($v['deposit']);
                $v['payout'] = FormatMoney($v['payout']);
                $v['profit'] = -FormatMoney($v['profit']);
            }
            if (count($detail) < 2) {
                if ($detail[0]['gametype'] == 10) $detail[] = $one;
                else                    $detail[] = $zero;
            }
        }
        $gametype = [];
        foreach ($detail as $key => $row) $gametype[$key] = $row['gametype'];
        array_multisort($gametype, SORT_ASC, $detail);
        $result['other'] = $detail;
        unset($v);
        return $result;
    }

    public function AddRecord($data)
    {
        $count = $this->getCount(['OrderId' => $data['OrderId']]);
        $result = false;
        if ($count > 0) {
            $result = $this->updateByWhere(['OrderId' => $data['OrderId']], $data);
        } else {
            $result = $this->add($data);
        }

    }


}
