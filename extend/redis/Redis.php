<?php
namespace redis;

use think\Cache;

//定义redis操作
class Redis {
    //存放实例
    private static $_instance = null;

    //私有化构造方法、
    private function __construct(){

    }
    //私有化克隆方法
    private function __clone(){

    }

    //公有化获取实例方法
    public static function getInstance(){
        if (!(self::$_instance instanceof Redis)){
            self::$_instance = new Redis();
        }
        return self::$_instance;
    }

    //加锁
    public static function lock($key, $val = 1, $expire = 10) {
        return Cache::store('redis')->handler()->set($key, $val, ['nx', 'ex' => $expire]);
    }

    //设置缓存
    public static function set($key, $value = 1, $expire = 0) {
        return Cache::store('redis')->set($key, $value, $expire);
    }

    //读取缓存
    public static function get($key) {
        return Cache::store('redis')->get($key);
    }

    //判断缓存
    public static function has($key) {
        return Cache::store('redis')->has($key);
    }

    //自增
    public static function inc($key, $step = 1) {
        return Cache::store('redis')->inc($key, $step);
    }

    //自减
    public static function dec($key, $step = 1) {
        return Cache::store('redis')->dec($key, $step);
    }

    //删除缓存
    public static function rm($key) {
        return Cache::store('redis')->rm($key);
    }


    //加入队列左部
    public static function lpush($key, $value) {
        return Cache::store('redis')->handler()->lpush($key, $value);
    }

    //加入队列右部
    public static function rpush($key, $value) {
        return Cache::store('redis')->handler()->rpush($key, $value);
    }

    //弹出队列左部
    public static function lpop($key) {
        return Cache::store('redis')->handler()->lpop($key);
    }

    //阻塞弹出队列左部
    public static function blPop($key,$count=0) {
        return Cache::store('redis')->handler()->blPop($key,$count);
    }

    //阻塞弹出队列右部
    public static function brPop($key,$count=0) {
        return Cache::store('redis')->handler()->brPop($key,$count);
    }

    //弹出队列右部
    public static function rpop($key) {
        return Cache::store('redis')->handler()->rpop($key);
    }

    public static function handler() {
        return Cache::store('redis')->handler();
    }
}