<?php
namespace pushservice\google;

/**
 * Curl类
 *
 * @desc curl Class support post&get
 * @package \Ananzu\Util
 * @date 2016-05-18
 */
class Curl
{
    const REQUEST_TIMEOUT = 30;                                             //超时时间


    protected $_ch = null;                                                  //curl的句柄
    protected $param_type = 0;                                              //参数类型

    protected $_header = [];                                                //curl的句柄
    protected static $_instance = null;

    protected function __construct()
    {
    }

    protected static function instance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * set header
     * @date 2019-11-22
     * @param array $header required 请求header
     * @return Curl|null
     */
    public static function setHeader($header)
    {
        self::instance()->_header = $header;
        return self::instance();
    }

    /**
     * 传参方式
     * @date 2019-11-22
     * @param int $type requrie 传参方式 0:默认 1:raw
     * @return Curl|null
     */
    public static function setParamType($type)
    {
        self::instance()->param_type = $type;
        return self::instance();
    }

    /**
     * GET request
     * @date 2019-11-22
     * @param string $url required 请求的url
     * @param array $params required 请求的参数
     * @param int $timeout 请求的超时时间
     * @return array
     * @throws \Exception
     */
    public static function get($url, $params = array(), $timeout = self::REQUEST_TIMEOUT)
    {
        return self::instance()->request($url, 'GET', $params, $timeout);
    }

    /**
     * POST request
     * @date 2019-11-22
     * @param string $url required 请求的url
     * @param array $params required 请求的参数
     * @param int $timeout 请求的超时时间
     * @return array
     * @throws \Exception
     */
    public static function post($url, $params = array(), $timeout = self::REQUEST_TIMEOUT)
    {
        return self::instance()->request($url, 'POST', $params, $timeout);
    }

    /**
     * request 请求的方法
     * @date 2019-11-22
     * @param string $url required 请求的url
     * @param string $method required 请求的method
     * @param array $params required 请求的参数
     * @param int $timeout 请求的超时时间
     * @demo Curl::request('http://asd.com.cn',['name'=>123,'age'=>88],Curl::CURL_REQUEST_POST)
     * @return array
     * @throws \Exception
     */
    protected static function request($url, $method, $params = array(), $timeout = self::REQUEST_TIMEOUT)
    {
        $model = self::instance();
        $model->_ch = curl_init();
        $model->_setParams($url, $params, $method);
        curl_setopt($model->_ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($model->_ch, CURLOPT_HTTPHEADER, $model->_header);
        curl_setopt($model->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($model->_ch, CURLOPT_HEADER, 0);
        $aRes = curl_exec($model->_ch);
        if ($error = curl_errno($model->_ch)) {
            throw new \Exception(curl_error($model->_ch), $error);
        }
        curl_close($model->_ch);
        return $aRes;
    }

    /**
     * ssl处理 如果是https，则设置相关的配置信息
     * @date 2016-05-18
     * @param string $url required 请求的url
     * @return void
     */
    protected function _setSsl($url)
    {
        if (true === strstr($url, 'https://', true)) {
            curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($this->_ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($this->_ch, CURLOPT_DNS_USE_GLOBAL_CACHE, 0);
            curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, 1);
        }
    }

    /**
     * curl请求方式和POST|GET请求数据处理
     * @date 2016-05-18
     * @param string $url required 请求的url
     * @param array $data required 请求的参数
     * @param string $method required 请求的方式 POST|GET
     * @return bool
     */
    protected function _setParams($url, $data, $method)
    {
        $this->_setSsl($url);
        switch ($method) {
            case 'POST':
                if ($this->param_type == 1) {
                    $_postData = is_array($data) ? json_encode($data) : $data;
                } else {
                    $_postData = is_array($data) ? http_build_query($data) : $data;
                }
                curl_setopt($this->_ch, CURLOPT_POST, true);
                curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $_postData);
                curl_setopt($this->_ch, CURLOPT_URL, $url);
                break;
            case 'GET':
                $_getData = is_array($data) ? http_build_query($data) : $data;
                $uri = preg_match('/\?/', $url) ? '&' . $_getData : '?' . $_getData;
                curl_setopt($this->_ch, CURLOPT_URL, $url . $uri);
                break;
            default:
                return false;
        }
    }
}
