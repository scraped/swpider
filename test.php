<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swpider\Queue;
use Swpider\Database;

Queue::connect([
    'host'=>'127.0.0.1',
    'port'=>'11300'
]);

Database::connect([
    'host'=>'127.0.0.1',
    'port'=>'3306',
    'database'=>'luoo',
    'username'  => 'root',
    'password'  => '123456',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
    'timezone'  => '+00:00',
    'strict'    => false,
]);


$re = Queue::listTubes();
var_dump($re);