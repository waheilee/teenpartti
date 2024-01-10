<?php

namespace app\admin\controller\import;

use app\admin\controller\Main;
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
//        Loader::import('PHPExcel.Classes.PHPExcel');
//        Loader::import('PHPExcel.Classes.PHPExcel.IOFactory.PHPExcel_IOFactory');
//        Loader::import('PHPExcel.Classes.PHPExcel.Reader.Excel5');
        //获取表单上传文件
        $file = request()->file('excel');

        $info = $file->validate(['ext' => 'xlsx'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        //上传验证后缀名,以及上传之后移动的地址
        if ($info) {
//            echo $info->getFilename();
            $excel = new PHPExcel();
            dump($excel);die();
            $exclePath = $info->getSaveName();  //获取文件名
            $file_name = ROOT_PATH . 'public' . DS . 'uploads' . DS . $exclePath;   //上传文件的地址
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
            echo "<pre>";
            $excel_array = $obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);
            dump($excel_array);die();
            $city = [];
            foreach ($excel_array as $k => $v) {
                $city[$k]['Id'] = $v[0];
                $city[$k]['code'] = $v[1];
                $city[$k]['path'] = $v[2];
                $city[$k]['pcode'] = $v[3];
                $city[$k]['name'] = $v[4];
            }
            Db::name('city')->insertAll($city); //批量插入数据
        } else {
            echo $file->getError();
        }
    }
}