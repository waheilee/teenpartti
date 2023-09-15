<?php

namespace app\api\controller;

Class CheckSignBase extends \app\api\controller\ApiBase {
    
    /**
     * 获取提交的参数数据
     * @var type 
     */
    private $paramData = null;

    /**
     * 提交的签名值
     * @var type 
     */
    private $signValue = '';
    
    /**
     * 签名超时时间
     * 默认600秒 10分钟
     */
    protected $sign_passtime = 600;
    
    
    protected $apikey = "zWnTJ63Lpwz5GL6Co79X5vQO40Thztal";
    
    protected function _initialize() {
        try {
            header("Access-Control-Allow-Origin:*");
            header('Access-Control-Allow-Methods:POST');
            header('Access-Control-Allow-Headers:x-requested-with, content-type');
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                return true;
            }
            //签名验证
            if (!empty($_POST)) {
                $this->signValue = $_POST['sign']; //取出签名
                $this->paramData = $this->getPostData();
                $check_res = $this->checkSign();
                if (!$check_res['status']) {
                    throw new \Exception($check_res['msg']);
                }
            } else if (!empty($_GET)) {
//                $this->signValue = $_GET['sign']; //取出签名
                $this->paramData = $this->getRequestData();
//                $check_res = $this->checkSign();
//                if (!$check_res['status']) {
//                    throw new \Exception($check_res['msg']);
//                }
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    public function __construct() {
        parent::__construct();
        $this->_initialize();
    }
    /**
     * 获取提交过来的参数数据 (数组)       
     * @return type
     */
    private function getRequestData() {
        $data = $_GET;
        unset($data['token']);
        unset($data['sign']);
        $data['api_key'] = $this->apikey;
        return $data;
    }

    /**
     * 获取POST提交的数据。
     * @return type
     */
    protected function getPostData() {
        $data = array();
        if (isset($_POST)) {
            $post_data = $_POST;
            $data = !empty($post_data) ? $post_data : array();
            unset($data['sign']);
            $data['api_key'] = $this->apikey; //对应平台的api_key
        } else {
            return $this->failJSON('post data is empty');
        }
        return $data;
    }
    
    /**
     * 格式化参数格式化成url参数(字典排序后重组)
     */
    private function toUrlParams() {
        $buff = '';
        foreach ($this->paramData as $k => $v) {
            if ($v != '' && !is_array($v)) {
                if (preg_match('/[\x80-\xff]./', $v))
                    $buff .= $k . '=' . urldecode($v) . '&';
                else
                    $buff .= $k . '=' . $v . '&';
            }
        }
        $buff = trim($buff, '&');
        return $buff;
    }
    
    /**
     * 生成签名     
     */
    private function makeSign() {
//        return strtoupper(md5($this->paramData['t'] . $this->apikey));
        ksort($this->paramData);
//        dump($this->paramData);
        $string = $this->toUrlParams();
//        dump($string);
        $string = md5($string);
        //签名2：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
    
    /**
     *
     * 检测签名
     */
    protected function checkSign() {
        if (!isset($this->paramData['t'])) {
            return $this->failData('签名参数有误');
        }
        if ((time() - $this->paramData['t']) > $this->sign_passtime) {
            return $this->failData('签名超时');
        }
        $sign = $this->makeSign();
        if ($this->signValue === $sign) {
            return $this->successData();
        } else {
            return $this->failData('签名数据有误！'.$sign);
        }
    }

}