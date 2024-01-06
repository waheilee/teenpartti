<?php

namespace app\admin\controller\Export;

use app\admin\controller\Main;
use app\model\GameOCDB;
use think\Exception;
use XLSXWriter;
class AllUserInfoExport extends Main
{

    public function export($data)
    {
        $writer = new XLSXWriter();


        $rows = $this->getExcelData($data);

        $writer->writeSheet($rows);
        $filename = lang('用户记录') . '-' . date('YmdHis');

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
//        $data = [];
        $data[] = [
            lang('ID') => 'integer',//ID
            //lang('机器码') => 'string',//MachineCode
            // lang('手机号') => 'string',//AccountName
            lang('账号') => 'string',//AccountName
//                        '是否禁用' => "string",//Locked
            lang('昵称') => 'string',//LoginName
//                        '登陆类型' => "string",//GmType
            lang('注册时间') => "datetime",//RegisterTime
            lang('最后登录IP') => "string",//LastLoginIP
            lang('总充值') => "0.00",//TotalDeposit
            lang('总转出') => '0.00',//TotalRollOut
            lang('剩余金币') => '0.00',//Money
            lang('代理账户') => '0.00'//Money
        ];

            foreach ($sourceData as $k){
                $item = [];
                $item['ID'] = $k['AccountID'] ?? "";
                $item['AccountName'] = substr_replace($k['AccountName'], '**', -4);
                $item['LoginName'] = $k['LoginName'] ?? "";
                $item['RegisterTime'] = $k['RegisterTime'] ?? "";
                $item['LastLoginIP'] = $k['LastLoginIP'] ?? "";
                $item['TotalDeposit'] = $k['TotalDeposit'] ?? "";
                $item['TotalRollOut'] = $k['TotalRollOut'] ?? "";
                $item['Money'] = FormatMoney($k['Money']);
                $item['ProxyBonus'] = $k['ProxyBonus'] ?? "";

                //团队打码
                $data[] = $item;
            }

        return $data;

    }



}