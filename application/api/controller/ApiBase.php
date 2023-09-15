<?php

namespace app\api\controller;

use think\Controller;

Class ApiBase extends Controller {
    
    public function __construct() {
        parent::__construct();
    }

    /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message, $options = 256) {
        $jsonData = array('status' => false, 'msg' => $message);
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success", $options = 256) {
        $jsonData = array('status' => true, 'data' => $data, 'msg' => $msg);
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }
    
    /**
     * 返回错误信息。
     * @param type $message     
     */
    protected function failData($message = null) {
        return array('status' => false, 'msg' => $message);
    }

    /**
     * 返回成功信息
     * @param type $data
     */
    protected function successData($data = NULL) {
        return array('status' => true, 'data' => $data);
    }
    
    /**
     * 获取POST值
     * @param type $name 变量名
     * @param type $default 默认值
     * @param type $filter 过滤方法
     * @return type
     */
    protected function _post($name, $default = '', $filter = '') {
        return request()->param($name, $default, $filter);
    }

    /**
     * 获取GET值
     * @param type $name
     * @param type $default 默认值
     * @param type $filter 过滤方法
     * @return type
     */
    protected function _get($name, $default = '', $filter = '') {
        return request()->param($name, $default, $filter);
    }
}