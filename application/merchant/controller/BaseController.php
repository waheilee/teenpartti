<?php

namespace app\merchant\controller;

class BaseController extends Main {
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 返回错误信息。
     * @param type $message     
     */
    protected function failData($message = null, $code = 1) {
        return array('code'=>$code, 'status' => false, 'msg' => $message);
    }

    /**
     * 返回成功信息
     * @param type $data
     */
    protected function successData($data = NULL, $msg = "success", $code = 0) {
        return array('code'=>$code, 'status' => true, 'data' => $data, 'msg'=>$msg);
    }
    
    /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message, $options = true, $code = 1) {
        $jsonData = array('code'=>$code, 'status' => false, 'msg' => $message);
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success", $options = 256, $code = 0) {
        $jsonData = array('code'=>$code, 'status' => true, 'data' => $data, 'msg' => $msg);
        $json = json_encode($jsonData, $options);
        echo $json;
        exit;
    }
    
    
    protected function toDataGrid($count=0, $data=[], $code=0, $msg='') {
        return json(array('data'=>$data, 'count'=>$count, 'code'=>$code, 'msg'=>$msg));
    }
    
    /**
     * 获取当前页码
     * @return type
     */
    protected function getPageIndex() {
        $pageIndex = $this->_get('page');
        if (empty($pageIndex))
            $pageIndex = $this->_post('page');
        if (empty($pageIndex))
            $pageIndex = -1;
        return $pageIndex;
    }

    /**
     * 获取每页显示数量
     * @return type`
     */
    protected function getPageLimit() {
        $pageLimit = $this->_get('limit');
        if (empty($pageLimit)){
            $pageLimit = $this->_post('limit');
        }
        if (empty($pageLimit)){
            $pageLimit = 10;
        }
        if ($pageLimit > 50){
            $pageLimit = 50;
        }
        return $pageLimit;
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
