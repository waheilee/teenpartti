<?php

namespace app\admin\lib;

use app\admin\lib\BaseLib;

Class HttpLib extends BaseLib {
    
    /**
     * http请求
     * @param type $request_url 接口地址
     * @param type $params  接口参数
     * @return type  
     * @throws \Exception
     */

    function http($url, $param = null, $method = 'POST', $http_head = []) {
        try {
            $opts = array(
                CURLOPT_TIMEOUT => 360,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            );
            // http请求头
            if (!empty($http_head)) {
                $opts[CURLOPT_HTTPHEADER] = $http_head;
            }
            
            /* 根据请求类型设置特定参数 */
            if (strtoupper($method) == 'POST' && !is_null($param)) {
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $param;
                if (is_string($param)) { //发送JSON数据
                    $opts[CURLOPT_HTTPHEADER] = array(
                        'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                        'Content-Length: ' . strlen($param),
                    );
                }
            } else if (strtoupper($method) == 'GET' && !is_null($param)) {
                $buff = '';
                foreach ($param as $k => $v) {
                    if ($v != '' && !is_array($v)) {
                        if (preg_match('/[\x80-\xff]./', $v))
                            $buff .= $k . '=' . urldecode($v) . '&';
                        else
                            $buff .= $k . '=' . $v . '&';
                    }
                }
                $url = $url . '?' . $buff;
            }
            $opts[CURLOPT_URL] = $url;
            /* 初始化并执行curl请求 */
            $ch = curl_init();
            curl_setopt_array($ch, $opts);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error) {           
                throw new \Exception($error);
            }
            if (is_string($data)) {
                $data = json_decode($data, 256);
            }
            if (empty($data)) {
                throw new \Exception('Missing Response');
            }
            return $this->successData($data);
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
        
    }
}

