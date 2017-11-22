<?php

namespace Swpider;

use Redis;


class Cache
{
    const HOST = '127.0.0.1';
    const PORT = 6379;

    const URL_ERROR = -1;           //请求失败
    const URL_READY = 0;            //未处理
    const URL_LOADED = 1;           //已处理
    const URL_EXCEPT = 2;           //有异常

    private static $_client;

    protected static $config;


    public static function connect($config = null)
    {
        if(!isset(self::$_client)){
            self::$_client = new Redis();
        }

        self::$config = $config ? : self::_defaultConfig();

        try{
            if(self::$_client->ping() === "+PONG"){
                self::$_client->close();
            }
        }catch(\Exception $e){

        }

        self::$_client->connect(self::$config['host'], self::$config['port']);

        self::$_client->setOption(Redis::OPT_PREFIX, self::$config['prefix'] . ":");
    }


    public static function client()
    {
        if(is_null(self::$_client)){
            self::connect();
        }
        return self::$_client;
    }

    protected static function _defaultConfig()
    {
        return [
            'host' => self::HOST,
            'port' => self::PORT,
            'prefix' => '',
        ];
    }

    //从链接集合中获取指定url
    public static function getUrl($url)
    {
        $name = 'urls';
        $key = self::getUrlKey($url);

        if(! self::$_client->hExists($name, $key)){
            return false;
        }

        $value = self::client()->hGet($name, $key);

        return json_decode($value, true);
    }
    //写入链接集合
    public static function setUrl($url, $status = 0)
    {
        $name = 'urls';
        $key = self::getUrlKey($url);

        $data = [
            'status' => $status,
            'last' => time(),
        ];

        self::client()->hSet($name, $key, json_encode($data));
    }

    protected static function getUrlKey($url)
    {
        return 'url_' . md5($url);
    }


    public static function __callStatic($name, $arguments)
    {
        if(isset(self::$_client)){
            return call_user_func_array([self::$_client, $name], $arguments);
        }

        throw new \BadMethodCallException("class ".__CLASS__ ." static method $name unsupported");
    }

}