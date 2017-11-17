<?php

namespace Swpider;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\MySqlConnection;


class Database
{


    private static $_db;

    protected static $config;


    public static function connect(array $config)
    {
        if(isset(self::$_db)){
            self::$_db->disconnect();
        }
        $connector = new MySqlConnector();
        self::$_db = new MySqlConnection($connector->connect($config), $config['database'], $config['prefix'], $config);
    }


    public static function db()
    {
        return self::$_db;
    }


    public static function __callStatic($name, $arguments)
    {
        if(isset(self::$_db) && method_exists(self::$_db, $name)){
            return call_user_func_array([self::$_db, $name], $arguments);
        }

        throw new \BadMethodCallException("class ".__CLASS__ ." static method $name unsupported");
    }

}