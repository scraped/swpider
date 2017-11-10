<?php

namespace Swpider;

use Illuminate\Support\Arr;

class Queue
{

    const DEFAULT_HOST = "127.0.0.1";
    const DEFAULT_PORT = 11300;
    const DEFAULT_TIMEOUT = 1;

    private static $_queue;

    protected static $config;


    public static function connect($config = null)
    {
        if(isset(self::$_queue) && self::$_queue instanceof Queue\SwooleBeanstalk){
            self::$_queue->getConnection()->disconnect();
        }

        self::$config = $config ? : self::defaultConfig();
        self::$_queue = new Queue\SwooleBeanstalk(self::$config['host'], self::$config['port'], Arr::get($config,'timeout', self::DEFAULT_TIMEOUT));
    }


    protected static function queue()
    {
        if(is_null(self::$_queue)){
            self::connect();
        }

        return self::$_queue;
    }


    protected static function defaultConfig()
    {
        $config = [
            'host' => self::DEFAULT_HOST,
            'port' => self::DEFAULT_PORT,
            'timeout' => self::DEFAULT_TIMEOUT
        ];

        return $config;
    }


    public static function __callStatic($name, $arguments)
    {
        if(isset(self::$_queue) && method_exists(self::$_queue, $name)){
            return call_user_func_array([self::$_queue, $name], $arguments);
        }

        throw new \BadMethodCallException("class ".__CLASS__ ." static method $name unsupported");
    }

}