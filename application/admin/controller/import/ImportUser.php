<?php

namespace app\admin\controller\import;

use app\admin\controller\Main;
use app\model\AccountDB;
use PHPExcel;
use think\Controller;


class ImportUser extends Controller
{

    public function importUser()
    {
        return $this->fetch('import/import_user');
    }

    function inserExcel()
    {


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
            $error_num = 0;
            foreach ($excel_array as $k => $v) {

                $regIpIn = (string)$v[0];
                $regPhone = (int)$v[1];
                $password = (string)$v[2];
                $inviteCode = (string)$v[3];

                $userAccountDB = new AccountDB();
                $accountName = $userAccountDB->getTableObject('T_Accounts')
                    ->where('AccountName','55'.$regPhone)
                    ->find();
                if (!empty($accountName)){
                    $errorInfo[] = [
                        '电话号码' => $regPhone,
                        '消息' => '用户名重复',
                    ];
                    $error_num +=1;
                    continue;
                }
                $phone = $userAccountDB->getTableObject('T_Accounts')
                    ->where('Mobile','55'.$regPhone)
                    ->find();
                if (!empty($phone)){
                    $errorInfo[] = [
                        '电话号码' => $regPhone,
                        '消息' => '电话号码重复',
                    ];
                    $error_num +=1;
                    continue;
                }
                $sqlExec = "exec P_Test_Accounts_Bind_Insert '$regIpIn',$regPhone,'$password', '$inviteCode'";

                $userAccountDB->getTableQuery($sqlExec);
                $success_num +=1;
            }
dump('成功'.$success_num);
dump('失败'.$error_num);
print_r($errorInfo);

        } else {
            echo $file->getError();
        }
    }
}