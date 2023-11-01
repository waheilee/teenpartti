<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------


//解析js escape编码的数据
if (!function_exists('js_unescape')) {
    function js_unescape($str)
    {
        $ret = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            if ($str[$i] == '%' && $str[$i + 1] == 'u') {
                $val = hexdec(substr($str, $i + 2, 4));
                if ($val < 0x7f) $ret .= chr($val);
                else if ($val < 0x800) $ret .= chr(0xc0 | ($val >> 6)) . chr(0x80 | ($val & 0x3f));
                else $ret .= chr(0xe0 | ($val >> 12)) . chr(0x80 | (($val >> 6) & 0x3f)) . chr(0x80 | ($val & 0x3f));
                $i += 5;
            } else if ($str[$i] == '%') {
                $ret .= urldecode(substr($str, $i, 3));
                $i += 2;
            } else $ret .= $str[$i];
        }
        return $ret;
    }
}


if (!function_exists('configTime')) {
    function sysTime()
    {   

        //return time()-9000;
        //$time_zone = config('default_timezone');
       // $shicha    =0;// config('time_diff')[$time_zone] ?? 0;
        return time();
    }
}

//判断ip合法性
if (!function_exists('checkIp')) {
    function checkIp($ip)
    {
        $arr = explode('.', $ip);
        if (count($arr) != 4) {
            return false;
        } else {
            for ($i = 0; $i < 4; $i++) {
                if (($arr[$i] < '0') || ($arr[$i] > '255')) {
                    return false;
                }
            }
        }
        return true;
    }
}


//记录log
if (!function_exists('save_log')) {
    function save_log($path, $content, $mode = 'day')
    {
        $path = strval($path);
        $path = str_replace('\\', '/', trim($path, '/'));

        $content = strval($content);

        if (!$path || !$content) {
            return false;
        }
        $mode = in_array($mode, array('day', 'month', 'year')) ? $mode : 'day';
        $tempPath = config('log_dir') . '/' . $path . '/';

        if ($mode == 'day') {
            $tempPath .= date('Y') . '/' . date('m') . '/';
            $fileName = date('d') . '.log';
        } elseif ($mode == 'month') {
            $tempPath .= date('Y') . '/';
            $fileName = date('m') . '.log';
        } else {
            $fileName = date('Y') . 'log';
        }

        if (!file_exists($tempPath)) {
            if (!mkdir($tempPath, 0777, true)) {
                return false;
            }
        }

        $file = $tempPath . $fileName;
        $content = date('Y-m-d H:i:s') . '#' . $content . "\r\n";
        $res = @file_put_contents($file, $content, FILE_APPEND);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('__')) {

    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return mixed
     */
    function __($name, $vars = [], $lang = '')
    {
        if (is_numeric($name) || !$name)
            return $name;
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return \think\Lang::get($name, $vars, $lang);
    }

}

//生成盐
if (!function_exists('generateSalt')) {
    function generateSalt()
    {
        $str = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';
        $salt = strtolower(substr(str_shuffle($str), 10, 6));
        return $salt;
    }
}

//生成随机数,用于生成salt
if (!function_exists('random_str')) {
    function random_str($length)
    {
        //生成一个包含  小写英文字母, 数字 的数组
        $arr = array_merge(range(0, 9), range('a', 'z'));
        $str = '';
        $arr_len = count($arr);
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $arr_len - 1);
            $str .= $arr[$rand];
        }
        return $str;
    }
}


//生成随机数,用于生成salt
if (!function_exists('geneal_random_num')) {
    function geneal_random_num($length)
    {
        //生成一个包含  小写英文字母, 数字 的数组
        $arr = array_merge(range(0, 9));
        $str = '';
        $arr_len = count($arr);
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $arr_len - 1);
            $str .= $arr[$rand];
        }
        return $str;
    }
}

if (!function_exists('p')) {
    function p($data)
    {
        // 定义样式
        $str = '<pre style="display: block;padding: 9.5px;margin: 44px 0 0 0;font-size: 13px;line-height: 1.42857;color: #333;word-break: break-all;word-wrap: break-word;background-color: #F5F5F5;border: 1px solid #CCC;border-radius: 4px;">';
        // 如果是boolean或者null直接显示文字；否则print
        if (is_bool($data)) {
            $show_data = $data ? 'true' : 'false';
        } elseif (is_null($data)) {
            $show_data = 'null';
        } else {
            $show_data = print_r($data, true);
        }
        $str .= $show_data;
        $str .= '</pre>';
        echo $str;
        die;
    }
}


if (!function_exists('array_children_count')) {
    /**
     * 子元素计数器
     * @param array $array
     * @param int $pid
     * @return array
     */
    function array_children_count($array, $pid)
    {
        $counter = [];
        foreach ($array as $item) {
            $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
            $count++;
            $counter[$item[$pid]] = $count;
        }
        return $counter;
    }
}

if (!function_exists('array2level')) {
    /**
     * 数组层级缩进转换
     * @param array $array 源数组
     * @param int $pid
     * @param int $level
     * @return array
     */
    function array2level($array, $pid = 0, $level = 1)
    {
        static $list = [];
        foreach ($array as $v) {
            if ($v['pid'] == $pid) {
                $v['level'] = $level;
                $list[] = $v;
                array2level($array, $v['id'], $level + 1);
            }
        }
        return $list;
    }
}

if (!function_exists('array2tree')) {
    /**
     * 构建层级（树状）数组
     * @param array $array 要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
     * @param string $pid_name 父级ID的字段名
     * @param string $child_key_name 子元素键名
     * @return array|bool
     */
    function array2tree(&$array, $pid_name = 'pid', $child_key_name = 'children')
    {
        $counter = array_children_count($array, $pid_name);
        if (!isset($counter[0]) || $counter[0] == 0) {
            return $array;
        }
        $tree = [];
        while (isset($counter[0]) && $counter[0] > 0) {
            $temp = array_shift($array);
            if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
                array_push($array, $temp);
            } else {
                if ($temp[$pid_name] == 0) {
                    $tree[] = $temp;
                } else {
                    $array = array_child_append($array, $temp[$pid_name], $temp, $child_key_name);
                }
            }
            $counter = array_children_count($array, $pid_name);
        }
        return $tree;
    }
}


if (!function_exists('array_child_append')) {
    /**
     * 把元素插入到对应的父元素$child_key_name字段
     * @param        $parent
     * @param        $pid
     * @param        $child
     * @param string $child_key_name 子元素键名
     * @return mixed
     */
    function array_child_append($parent, $pid, $child, $child_key_name)
    {
        foreach ($parent as &$item) {
            if ($item['id'] == $pid) {
                if (!isset($item[$child_key_name])) {
                    $item[$child_key_name] = [];
                }

                $item[$child_key_name][] = $child;
            }
        }
        return $parent;
    }
}

if (!function_exists('getClientIP')) {
    //获取客户端真实IP
    function getClientIP()
    {
        global $ip;
        if (getenv("HTTP_CLIENT_IP")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } else if (getenv("HTTP_X_FORWARDED_FOR")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("REMOTE_ADDR")) {
            $ip = getenv("REMOTE_ADDR");
        } else {
            $ip = "Unknow";
        }

        return $ip;
    }
}

if (!function_exists('getCity')) {
    /**
     * 获取 IP  地理位置
     * 淘宝IP接口
     * @Return: array
     */
    function getCity($ip = '')
    {
        if ($ip == '') {
            $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json";
            $ip = json_decode(file_get_contents($url), true);
            $data = $ip;
        } else {
            $url = "http://ip.taobao.com/service/getIpInfo.php?ip=" . $ip;
            $ip = json_decode(file_get_contents($url));
            if ((string)$ip->code == '1') {
                return false;
            }
            $data = (array)$ip->data;
        }

        return $data;
    }
}

if (!function_exists('game_split_sql')) {
    /**
     * 切分SQL文件成多个可以单独执行的sql语句
     * @param $file sql文件路径
     * @param $tablePre 表前缀
     * @param string $charset 字符集
     * @param string $defaultTablePre 默认表前缀
     * @param string $defaultCharset 默认字符集
     * @return array
     */
    function game_split_sql($file, $tablePre, $charset = 'utf8mb4', $defaultTablePre = 'game_', $defaultCharset = 'utf8mb4')
    {
        if (file_exists($file)) {
            //读取SQL文件
            $sql = file_get_contents($file);
            $sql = str_replace("\r", "\n", $sql);
            $sql = str_replace("BEGIN;\n", '', $sql);//兼容 navicat 导出的 insert 语句
            $sql = str_replace("COMMIT;\n", '', $sql);//兼容 navicat 导出的 insert 语句
            $sql = str_replace($defaultCharset, $charset, $sql);
            $sql = trim($sql);
            //替换表前缀
            $sql = str_replace(" `{$defaultTablePre}", " `{$tablePre}", $sql);
            $sqls = explode(";\n", $sql);
            return $sqls;
        }

        return [];
    }
}

if (!function_exists('format_bytes')) {
    /**
     * 格式化字节大小
     *
     * @param number $size 字节数
     * @param string $delimiter 数字和单位分隔符
     * @return string            格式化后的带单位的大小
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
        return round($size, 2) . $delimiter . $units[$i];
    }
}

if (!function_exists('checkStr')) {
    //异常字符串处理
    function checkStr($str)
    {
        switch ($str) {
            case strspn($str, '&#;') > 0:
                $str = ltrim($str, '&#');
                $str = rtrim($str, ';');
                return $str;
            default:
                return $str;
                break;
        }
    }
}
//http://www.thinkphp.cn/topic/45957.html
if (!function_exists('http_curl')) {
    /**
     * @param string $url 请求地址
     * @param string $type 请求类型 post  get
     * @param string $arr 如果是post 传递的数据
     * @return mixed|\think\response\Json
     */
    function http_curl($url, $type = 'get', $authorization, $arr = '')
    {

        $headers = array('appkey:' . $authorization['appkey'], 'appsecret:' . $authorization['appsecret']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, $headers); //设置header
        curl_setopt($ch, CURLOPT_URL, $url);//设置访问的地址
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//获取的信息返回
        if ($type == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }
        $output = curl_exec($ch);//采集
        if (curl_error($ch)) {
            return curl_error($ch);
        }
        return $output;
    }
}

if (!function_exists('gb2312ToUtf8')) {
    /**
     * 编码转换gb2312转utf8
     * @param $str
     * @return string
     */
    function gb2312ToUtf8($str)
    {
        if (!empty($str)) {
            return iconv('GBK', 'utf-8//IGNORE', $str);
        } else {
            return $str;
        }
    }
}


if (!function_exists('FormatMoney')) {
///分转为元，小数保留2位
    function FormatMoney($param)
    {
        if (bl == 1) return $param;
        return sprintf('%.2f', floatval($param / bl));
    }
}

if (!function_exists('FormatMoneyint')) {
///分转为元，小数保留2位
    function FormatMoneyint($param)
    {
        if (bl == 1) return $param;
        return sprintf('%d', floatval($param / bl));
    }
}



if (!function_exists('ConVerMoney')) {
///分转为元，小数保留2位
    function ConVerMoney(&$value)
    {
        if (bl > 1) $value = sprintf('%.2f', ((int)$value * 1.00) / bl);
    }
}


if (!function_exists('ConVerDate')) {
    /**日期格式转换 去除毫秒 引用传递不需要赋值*/
    function ConVerDate(&$value)
    {
        if (strlen($value) > 19) $value = substr($value, 0, 19);
    }
}


if (!function_exists('arraySort')) {
    function arraySort($array, $keys, $sort = 'asc')
    {
        $newArr = $valArr = array();
        foreach ($array as $key => $value) {
            $valArr[$key] = $value[$keys];
        }

        ($sort == 'asc') ? asort($valArr) : arsort($valArr);//先利用keys对数组排序，目的是把目标数组的key排好序
        reset($valArr); //指针指向数组第一个值
        foreach ($valArr as $key => $value) {
            $newArr[$key] = $array[$key];
        }

        return array_merge($newArr);
    }
}


if (!function_exists('randnum')) {
    function randnum()
    {
        $arr = array();
        while (count($arr) < 4) {
            $arr[] = rand(1, 9);
            $arr = array_unique($arr);
        }
        return implode("", $arr);
    }
}

if (!function_exists('checkmobile')) {
    function checkmobile($mobile)
    {
        if (preg_match("/^1\d{10}$/", $mobile)) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('httppostjson')) {
    function httppostjson($url, $data)
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}

//生成短地址
if (!function_exists('getshorturl')) {
    function getshorturl($fromurl)
    {

        $host = 'https://dwz.cn';
        $path = '/admin/v2/create';
        $url = $host . $path;
        $method = 'POST';
        $content_type = 'application/json';

        // TODO: 设置Token
        $token = '9de8d743c49f0350b9d1366f2d39fde9';
        //var_dump($fromurl);

        // TODO：设置待注册长网址
        $bodys = array('Url' => $fromurl, 'TermOfValidity' => '1-year');

        // 配置headers
        $headers = array('Content-Type:' . $content_type, 'Token:' . $token);


        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($bodys));
        // 发送请求
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}


if (!function_exists('getOrderId')) {
    function getOrderId()
    {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $orderid = $yCode[intval(date('Y')) - 2020] . strtoupper(dechex(date('m')))
            . date('d') . substr(time(), -5) . substr(microtime(), 2, 5)
            . sprintf('%02d', rand(0, 99));
        return $orderid;
    }
}

if (!function_exists('create_guid')) {
    function create_guid($namespace = '')
    {
        static $guid = '';
        $uid = uniqid("", true);
        $data = $namespace;
        $data .= $_SERVER['REQUEST_TIME'];
        $data .= $_SERVER['HTTP_USER_AGENT'];
        $data .= $_SERVER['LOCAL_ADDR'];
        $data .= $_SERVER['LOCAL_PORT'];
        $data .= $_SERVER['REMOTE_ADDR'];
        $data .= $_SERVER['REMOTE_PORT'];
        $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
        $guid = '{' .
            substr($hash, 0, 8) .
            '-' .
            substr($hash, 8, 4) .
            '-' .
            substr($hash, 12, 4) .
            '-' .
            substr($hash, 16, 4) .
            '-' .
            substr($hash, 20, 12) .
            '}';
        return $guid;
    }
}

if (!function_exists('getIPcode')) {
    function getIPcode($ipaddr = '')
    {
        $code = "-";
        return $code;
        if (!empty($ipaddr)) {
            $data = redis\Redis::get($ipaddr);
            if (empty($data)) {
                $url = "http://ip-api.com/json/" . $ipaddr;
                $ip = json_decode(file_get_contents($url), true);

                if (!empty($ip)) {
                    if ($ip['status'] != 'fail') {
                        $code = $ip['countryCode'];
                        redis\Redis::set($ipaddr, $ip);
                    }
                }
            } else {
                $code = $data['countryCode'];
            }
        }
        return $code;
    }
}

if (!function_exists('IsNullOrEmpty')) {
    /**
     * 判断是否为空或null
     * @param $value
     * @return bool
     */
    function IsNullOrEmpty($value)
    {
        if (is_string($value)) {
            $value = trim($value);
            return ($value == null || strlen($value) == 0);
        }
        if (is_array($value)) {
            return (count($value) == 0);
        }
        if (empty($value)) return empty($value);

    }
}


function jsonRequest($must_fields = null)
{
    $params = json_decode(file_get_contents('php://input'), true);
    if (count($must_fields) > 0) {
        foreach ($must_fields as $field) {
            if (!isset($params[$field])) {
                $params[$field] = '';
                //return false;
                //echo "param " . $field . " is not set ";
            }
        }
    }
    return $params;
}

/*
 * 生成随机数
 */

function createRandCode($len) {
    $chars = array(
        "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
    );
    $charsLen = count($chars) - 1;
    shuffle($chars);
    $output = "";
    for ($i = 0; $i < $len; $i++) {
        $output .= $chars[mt_rand(0, $charsLen)];
    }
    return $output;
}


//http请求函数
function urlhttpRequest($url, $data = '', $headers = array(), $method = 'POST', $timeOut = 20, $agent = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);           //请求超时时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);    //链接超时时间
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data != '') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $file_contents = curl_exec($ch);
    curl_close($ch);
    //$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    save_log('tgpay','input:' . json_encode($data));
    save_log('tgpay','output:' . $file_contents);
    //这里解析
    return $file_contents;
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key  加密密钥
 * @param int $expire  过期时间 单位 秒
 * return string
 */
function think_encrypt($data, $key = '', $expire = 0) {
    $key  = md5(empty($key) ? 'crypt' : $key);
    $data = base64_encode($data);
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    $str = sprintf('%010d', $expire ? $expire + time():0);
    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)))%256);
    }
    return str_replace(array('+','/','='),array('-','_',''),base64_encode($str));
}
/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key  加密密钥
 * return string
 */
function think_decrypt($data, $key = ''){
    $key    = md5(empty($key) ? 'crypt' : $key);
    $data   = str_replace(array('-','_'),array('+','/'),$data);
    $mod4   = strlen($data) % 4;
    if ($mod4) {
       $data .= substr('====', $mod4);
    }
    $data   = base64_decode($data);
    $expire = substr($data,0,10);
    $data   = substr($data,10);
    if($expire > 0 && $expire < time()) {
        return '';
    }
    $x      = 0;
    $len    = strlen($data);
    $l      = strlen($key);
    $char   = $str = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1))<ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        }else{
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}


function getMillisecond() {
    list($microsecond , $time) = explode(' ', microtime()); //' '中间是一个空格
    return (float)sprintf('%.0f',(floatval($microsecond)+floatval($time))*1000);
}


function sortList($data,$id=0,&$arr=[])
{
    foreach($data as $v){
        if ($id == $v['ParentID']){
            $arr[]=$v['RoleID'];
            $this->sortList($data,$v['RoleID'],$arr);
        }
    }
    return $arr;
}

