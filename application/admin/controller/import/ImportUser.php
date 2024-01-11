<?php

namespace app\admin\controller\import;

use app\admin\controller\Main;
use app\model\AccountDB;
use think\Env;
use think\Loader;
use PHPExcel;


class ImportUser extends Main
{

    public function importUser()
    {
        return $this->fetch('import/import_user');
    }

    function inserExcel()
    {

//        Loader::import('PHPExcel.PHPExcel');
//        Loader::import('PHPExcel.Classes.PHPExcel.IOFactory.PHPExcel_IOFactory');
//        Loader::import('PHPExcel.Classes.PHPExcel.Reader.Excel5');
        //获取表单上传文件
        $file = request()->file('excel');

        $info = $file->validate(['ext' => 'xlsx'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        //上传验证后缀名,以及上传之后移动的地址
        if ($info) {
//            echo $info->getFilename();
            $excel = new PHPExcel();

            $exclePath = $info->getSaveName();  //获取文件名
            $file_name = ROOT_PATH . 'public' . DS . 'uploads' . DS . $exclePath;   //上传文件的地址
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
            echo "<pre>";
            $excel_array = $obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);

            $errorInfo = [];
            $success_num = 0;
            $error_num=0;
            foreach ($excel_array as $k => $v) {

                $regIpIn = (string)$v[0];
                $regPhone = (int)$v[1];
                $password = (string)$v[2];
                $inviteCode = (string)$v[3];

                $userAccountDB = new AccountDB();
                $sqlExec = "exec P_Test_Accounts_Bind_Insert '$regIpIn','$regPhone','$password', '$inviteCode'";
//                $sql = "{CALL [dbo].[P_Test_Accounts_Bind_Insert] (?, ?, ?, ?)}";
//                $data = [
//                    $regIpIn,
//                    $regPhone,
//                    $password,
//                    $inviteCode
//                ];
//                $res = $userAccountDB->getTableEXEC($sql,$data);
                $res = $userAccountDB->getTableQuery($sqlExec);
                $flattenedArray = call_user_func_array('array_merge', $res);

                if ($flattenedArray[0]['info'] == 1){
                    $errorInfo[] = [
                        '电话号码' => $regPhone,
                        '消息' => 'ip重复',
                    ];
                    $error_num +=1;
                }elseif($flattenedArray[0]['info'] == 2){
                    $errorInfo[] = [
                        '电话号码' => $regPhone,
                        '消息' => '电话号码重复',
                    ];
                    $error_num +=1;
                }elseif($flattenedArray[0]['info'] == 3){
                    $errorInfo[] = [
                        '电话号码' => $regPhone,
                        '消息' => '用户名重复',
                    ];
                    $error_num +=1;
                }else{
                    $success_num+= 1;
                }
            }
dump('成功'.$success_num);
dump('失败'.$error_num);
print_r($errorInfo);
//            $this->apiReturn(0, $errorInfo, '操作成功。成功：' . $success_num . ',失败：' . $error_num);

        } else {
            echo $file->getError();
        }
    }
}