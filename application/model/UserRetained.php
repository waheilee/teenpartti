<?php

namespace app\model;

class UserRetained extends BaseModel
{
    protected $table = 'accounts';

    public function __construct($connstr = '')
    {
        if (empty($connstr)) $connstr = $this->AccountDB;
        Parent::__construct($connstr);
    }

    public function getUserRetained($strwhere, $strartdate = '', $enddate = '', $OrderField, $PageIndex, $pagesize, $ordertype, $tblName = '')
    {
        $strFields = "accountid,accountname,lastloginip,lastlogintime,registertime,devicetype,countrycode";
        if ($tblName == '') {
            $tableName = $this->table;
        } else
            $tableName = $tblName;
        //传入开始与结束时间，生成统计数组
        $Live_array = $this->setNewCountArray($strartdate, $enddate);
        //取所有
        //$Accounts = $this->getQueryAll($tableName,$strFields,$strwhere);
        $Accounts = $this->getListAll($strwhere, $strFields, 'registertime desc');

        for ($i = 0; $i < count($Live_array); $i++) {
            for ($j = 0; $j < count($Accounts); $j++) {
                $ddate = date('Y-m-d', strtotime($Accounts[$j]['registertime']));
                if ($Live_array[$i]['days'] == $ddate) {
                    //print_r($Accounts[$j]);
                    $Live_array[$i] = $this->setLiveDays($Live_array[$i], $Accounts[$j]);
                }
            }
        }
        $list['list'] = $Live_array;
        $list['count'] = 0;
        $list['totalsum'] = 0;
        return $list;
    }

    protected function setLiveDays($Live_array, $account)
    {
        $i = 0;  //判断留存：1天3天5天7天15天30天
        $j = 1;
        $days = $Live_array['days'];
        $anzhuang = $Live_array['anzhuang'];
        //unset($Live_array['days']);
        //unset($Live_array['anzhuang']);
        $diff = strtotime($account['registertime']) - strtotime($account['lastlogintime']);
        $live_days = abs(round($diff / 86400));
        if ($live_days >= 0) $Live_array['firstday'] += 1; //1天
        if ($live_days >= 1) $Live_array['1days'] += 1;         //2天
        if ($live_days >= 3) $Live_array['3days'] += 1;//3天
        if ($live_days >= 5) $Live_array['5days'] += 1; //5天
        if ($live_days >= 7) $Live_array['7days'] += 1; //7天
        if ($live_days >= 15) $Live_array['15days'] += 1;//15天
        if ($live_days >= 30) $Live_array['30days'] += 1;//30天

        $Live_array['anzhuang'] = $Live_array['firstday'];
        return $Live_array;
    }

    protected function setNewCountArray($strartdate, $enddate)
    {
        $diff = strtotime($strartdate) - strtotime($enddate);
        $days = abs(round($diff / 86400));
        $commodity = array();
        if ($days > 0) {
            for ($i = 0; $i <= $days; $i++) {
                $commodity[$i]['days'] = date('Y-m-d', strtotime("$strartdate +$i day"));
                $commodity[$i]['anzhuang'] = 0;
                $commodity[$i]['firstday'] = 0;
                $commodity[$i]['1days'] = 0;
                $commodity[$i]['3days'] = 0;
                $commodity[$i]['5days'] = 0;
                $commodity[$i]['7days'] = 0;
                $commodity[$i]['15days'] = 0;
                $commodity[$i]['30days'] = 0;
            }
        } else {
            $commodity[0]['days'] = $strartdate;
            $commodity[0]['anzhuang'] = 0;
            $commodity[0]['firstday'] = 0;
            $commodity[0]['1days'] = 0;
            $commodity[0]['3days'] = 0;
            $commodity[0]['5days'] = 0;
            $commodity[0]['7days'] = 0;
            $commodity[0]['15days'] = 0;
            $commodity[0]['30days'] = 0;

        }
        return $commodity;
    }
}
