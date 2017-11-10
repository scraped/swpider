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
        $connector = new MySqlConnector();
        self::$_db = new MySqlConnection($connector->connect($config), $config['database'], $config['prefix'], $config);
    }


    protected static function db()
    {
        return self::$_db;
    }



}