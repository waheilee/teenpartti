<?php

namespace app\admin\controller\Export;

use app\admin\controller\Main;
use app\model\DataChangelogsDB;
use XLSXWriter;

class ColorMoneyLogExport extends Main
{
    public function export()
    {
        $roleId = input('roleid', '');
        $start = input('start', '');
        $end = input('end', '');
        $changeType = input('ChangeType', -1);
        $moneyKey = input('MoneyKey', -1);
        $limit = input('limit', 100000000);
        $writer = new XLSXWriter();
        $m = new DataChangelogsDB();
        $list = $m->getTableObject('T_ColorMoneyLog')
            ->where(function ($q) use ($roleId) {
                if (!empty($roleId)) {
                    $q->where('RoleId', $roleId);
                }

            })
            ->where(function ($q) use ($changeType) {
                if ($changeType >= 0) {
                    $q->where('ChangeType', $changeType);
                }

            })
            ->where(function ($q) use ($moneyKey) {
                if ($moneyKey > 0) {
                    $q->where('MoneyKey', $moneyKey);
                }
            })
            ->where(function ($q) use ($start, $end) {

                if (!empty($start) && !empty($end)) {
                    $q->where('AddTime', 'between time', [$start, $end]);
                }
            })
            ->limit($limit)
            ->select();

        $rows = $this->getExcelData($list);

        $writer->writeSheet($rows);
        $filename = lang('彩金日志') . '-' . date('YmdHis');

        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename("$filename.xlsx") . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

// 将 Excel 文件输出到浏览器
        $writer->writeToStdOut();
        exit();
    }

    public function getExcelData($sourceData)
    {

        $data[] = [
            lang('用户ID'),
            lang('修改类型'),
            lang('彩金类型'),
            lang('修改金额'),
            lang('改变后金额'),
            lang('时间'),

        ];

        foreach ($sourceData as $k) {
            $item = [];
            $item['RoleId'] = $k['RoleId'] ?? "";
            $item['ChangeType'] = $this->getChangeType($k['ChangeType']);
            $item['MoneyKey'] = $this->getMoneyKey($k['MoneyKey']);
            $item['ChangeMoney'] = FormatMoney($k['ChangeMoney']);
            $item['LastMoney'] = FormatMoney($k['LastMoney']);
            $item['AddTime'] = date('Y-m-d H:i:s', strtotime($k['AddTime']));
            $data[] = $item;
        }

        return $data;
    }

    public function getChangeType($type)
    {
        switch ($type) {
            case 0:
                return '首充添加';
            case 1:
                return '后台添加';
            case 2:
                return '完成打码';
            case 3:
                return '提现清空首充';
            case 4:
                return '进行游戏变化';
            case 5:
                return '打码需求变化';
            default :
                return '无修改类型';
        }
    }

    public function getMoneyKey($key)
    {
        switch ($key) {
            case 10210:
                return '首存彩金A';
            case 10211:
                return '手动彩金B';
            case 10212:
                return '手动彩金 冻结部分C';
            case 90001:
                return '打码需求变化';
            default:
                return '无彩金类型';
        }
    }
}