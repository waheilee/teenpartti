<?php
//����ļ���װ���������ߣ��ڷ���ʱ����Ҫ�õ�
namespace socket;
class PHPStream
{
    var $len;
    var $data;

    /**
     * 构造函数
     */
    function __construct() {
        //echo "PHPStream<br />";
        $this->len = 0;
        $this->data = '';
    }

    /**
     * 析构函数 生命周期结束时候 重置data len
     */
    function __destruct() {
        $this->len = 0;
        $this->data = '';
    }

    /**
     * 无符号 char 1 bit  0 ~ 2^8-1
     * @param $num
     */
    function WriteUChar($num) {
        $this->len += 1;
        $this->data .= pack('C', $num);
    }

    /**
     * 有符号 char 1 bit  -2^7 ~ 2^7-1
     * @param $num
     */
    function WriteChar($num) {
        $this->len += 1;
        $this->data .= pack('c', $num);
    }

    /**
     * 无符号 short 2 bit  0~65535
     * @param $num
     */
    function WriteUShort($num) {
        $this->len += 2;
        $this->data .= pack('S', $num);
    }

    /**
     * 有符号 short 2 bit -32768~+32767
     * @param $num
     */
    function WriteShort($num) {
        //echo "Short " . $num . "<br />";
        $this->len += 2;
        $this->data .= pack('s', $num);
    }

    /**
     * unsigned long 4 bit
     * 0~4294967295（0~(2^32-1)
     * @param $num
     */
    function WriteULong($num) {
        //echo "ULong " . $num . "<br />";
        $this->len += 4;
        $this->data .= pack('L', $num);
    }

    /**
     * signed long
     * -2147483648~2147483647（-2^31~(2^31-1)）
     * @param $num
     */
    function WriteLong($num) {
        //echo "Long " . $num . "<br />";
        $this->len += 4;
        $this->data .= pack('l', $num);
    }


    /**
     * 以NULL 字节填充字符串
     * @param $str
     * @param $len
     */
    function WriteString($str, $len) {
        $this->len += $len;
        $this->data .= pack('a' . $len, $str);
    }

    /**
     * signed long long
     * 8个字节长度数字
     * @param $num
     */
    function WriteINT64($num) {
        $num = floatval($num);
        $numL32 = $num & 0xFFFFFFFF;
        $numH32 = floor($num / pow(2, 32));
        $this->len += 4;
        $this->data .= pack('L', $numL32);
        $this->len += 4;
        $this->data .= pack('L', $numH32);
    }

    /**
     * unsigned long long
     * 8个字节长度数字 无符号
     * @param $num
     */
    function WriteUINT64($num) {
        $num = floatval($num);
        $numL32 = $num & 0xFFFFFFFF;
        $numH32 = floor($num / pow(2, 32));

        $this->len += 4;
        $this->data .= pack('l', $numL32);
        $this->len += 4;
        $this->data .= pack('l', $numH32);
    }
}
