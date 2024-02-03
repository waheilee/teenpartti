<?php

namespace app\admin\controller\Export;

use app\admin\controller\Main;
use app\model\DataChangelogsDB;
use app\model\GameOCDB;
use think\Exception;
use XLSXWriter;
class RedPackLogExport extends Main
{

    public function export()
    {
        $activityId = input('activityId', '');
        $roleId = input('roleid', '');
        $start = input('start', '');
        $end = input('end', '');
        $limit = input('limit', 100000000);
        $writer = new XLSXWriter();
        $changeLogDB = new DataChangelogsDB();
        $where = '1=1';
        if (!empty($activityId)) {
            $where .= ' and ActivityId=' . "'$activityId'";
        }
        if (!empty($roleId)) {
            $where .= ' and RoleId=' . $roleId;
        }

        if (!empty($start) && !empty($end)) {
            $startDate = strtotime($start . '00:00:00');
            $endDate = strtotime($end . '23:59:59');
            $where .= ' and AddTime>=' . "'$startDate'" . ' and AddTime<=' . "'$endDate'";

        }
        $lists = $changeLogDB->getTableObject('T_RedPackHistory')
            ->where($where)
            ->limit($limit)
            ->select();

        $rows = $this->getExcelData($lists);

        $writer->writeSheet($rows);
        $filename = lang('红包雨日志') . '-' . date('YmdHis');

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
            lang('红包雨ID'),
            lang('领取金额'),
            lang('时间'),


        ];

        foreach ($sourceData as $k) {
            $item = [];
            $item['RoleId'] = $k['RoleId'] ?? "";
            $item['ActivityId'] = $k['ActivityId'];
            $item['Money'] = FormatMoney($k['Money']);
            $item['AddTime'] = date('Y-m-d H:i:s', $k['AddTime']);
            $data[] = $item;
        }

        return $data;
    }

}