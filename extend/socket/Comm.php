<?php

namespace socket;
//这个文件定义了消息包头，基本不用动

//服务器编号
define('REQ_HOTAL', 1); //大厅
define('REQ_ROOM', 2); //房间
define('REQ_DC', 3); //数据中心
define('REQ_OW', 4); //官网
define('REQ_OM', 5); //运维平台
define('REQ_AI', 6); //通行证认证
define('REQ_WEB', 7); //Web认证
define('REQ_LOGON', 8); //登录服务器
define('REQ_BANK', 9); //银行服务器
define('COMM_HEAD_LEN', 12); //消息头长度

//帐号服务器地址
define('AS_SERVER_IP', config('account_socket'));
define('AS_SERVERPORT', 18900);

//数据中心地址
define('DC_SERVER_IP', config('socketip'));
define('DC_SERVERPORT', 18600);

//订单服务器地址
define('OS_SERVER_IP', config('socketip'));
define('OS_SERVERPORT', 18800);

class Comm
{
    function getSocketInstance($str = 'DC') {
        //获取两个MySocket对象
        require_once 'SvrPtl/MySocket.php';
        if ($str == 'DC') {
            //if(!($_SESSION['dcSocket'] instanceof MySocket )){
            return new MySocket(DC_SERVER_IP, DC_SERVERPORT);
            //}
        } else {
            //if(!($_SESSION['asSocket'] instanceof MySocket )){
            return new MySocket(AS_SERVER_IP, AS_SERVERPORT);
        }
    }

    function fitStr(&$str) {
        if (strpos($str, 0) != FALSE) {
            $str = substr($str, 0, strpos($str, 0)); //取到0结束
        }
        //$str = trim($str);
        $str = preg_replace("/[\\n\\t\\r]/", "", $str);
        $str = iconv('GBK', 'UTF-8', $str);
    }

    /**
     *
     * @param $ValueH32
     * @param $ValueL32 无符号整形 的补码 相当于直接截取了 64位的低32位。
     * 此时合并的话 高32左移32位， 同 无符号整形为正数 等于拼接
     * @return mixed
     */
    function MakeINT64Value($ValueH32, $ValueL32) {
        $iTempL32 = (float)sprintf('%u', ($ValueL32 & 0xFFFFFFFF));
        $iTempI64 = ($ValueH32 * pow(2, 32)) + $iTempL32;

        return $iTempI64;
    }

    /**
     * @param int $cmd 消息号
     * @param int $len 数据长度
     * @param int $ret 返回标志,路径等
     * @param int $src 源地址
     * @param int $dest 目的地址
     * @return false|string
     */
    function MakeSendHead($cmd, $len, $ret = 0, $src = REQ_OM, $dest = REQ_DC) {
        $symbol = 0x55; // 0x55为标志 十进制8'5
        //$src = REQ_OM;		// 从哪里来
        //$dest = REQ_DC;		// 到哪里去
        //$ret = 0;		// 返回标志、路径等
        //$type = CMD_MD_TEST_REQ;		// 请求类型
        //$len = 0;		// 数据长度
        $total = 0; // 总报文数
        $number = 0; // 当前编号

        return pack('C4S4', $symbol, $src, $dest, $ret, $cmd, $len, $total, $number);
    }
}


