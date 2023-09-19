<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/7/31
 * Time: 19:25
 */
namespace socket;

class MySocket {
    private $socket;
    private $address;
    private $port;

    public function __construct($address,$service_port){
        $this->address = $address;
        $this->port = $service_port;

        $this->socket = $this->ConnectServer($this->address,$this->port);
    }

    /**
     * @param $head 请求头
     * @param $body 参数
     */
    public function request($head,$body){
        $in = $head.$body;
        $in_len = strlen($in);
        socket_write($this->socket, $in, $in_len);
    }

    /**
     * 请求返回
     */
    public function response(){
        $buff = $this->ReadData($this->socket);
        $this->close();
        return $buff;
    }
    /**
     * 返回错误信息
     * @return int
     */
    public function error(){
        return socket_last_error($this->socket);
    }
    /**
     * 关闭连接
     */
    public function close(){
        socket_close($this->socket);
    }

    function ConnectServer($address, $port) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo "socket_create() failed. address: $address, port: $port, Reason: " . socket_strerror(socket_last_error()) . "\n";
        }
        //socket_set_timeout($socket, 1, 0);
        //echo "Attempting to connect to '$address' on port '$service_port'...";

        $result = socket_connect($socket, $address, $port);
        if ($result === false) {
            //echo "socket_connect() failed. address: $address, port: $port, Reason: " . socket_strerror(socket_last_error($socket)) . "\n";
        }
        return $socket;
    }

    function ReadData($socket) {

        //echo "ReadData";
        $buff = "";
        $out = "";

        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 10, "usec" => 0));

        while ($out = socket_read($socket, 1024, PHP_BINARY_READ)) {

            //echo "data: ". $out;
            $buff .= $out;

        }

        //socket_close($socket);
        return $buff;
    }
}