<?php

namespace Swpider;

use Swoole\Http\Client;

abstract class Spider
{
    const URL_LIST = 1;
    const URL_CONTENT = 2;

    const FORMAT_HTML = 1;
    const FORMAT_JSON = 2;
    const FORMAT_JSONP = 3;
    const FORMAT_TEXT = 4;


    //爬虫名
    public $name = 'swpider';
    //进程数
    protected $task_num = 1;

    //数据库设置
    protected $db_host = '127.0.0.1';
    protected $db_port = 3306;
    protected $db_name = 'swpider';
    protected $db_user = 'swpider';
    protected $db_password = 'password';
    protected $db_charset = 'utf8';
    protected $db_collation = 'utf8_unicode_ci';
    protected $db_prefix = '';
    protected $db_timezone = '+00:00';
    protected $db_strict = false;

    //队列设置
    protected $queue_host = '127.0.0.1';
    protected $queue_port = 11300;
    protected $queue_timeout = 1;
    protected $queue_name = 'swpider';

    //爬虫规则
    protected $rules = [
        'url' => [
            [
                'name' => 'test',                       //规则名称，唯一，用于队列命名
                'regex' => '',                          //链接格式
                'type' => self::URL_LIST,               //链接配置， index 索引，target 目标页
                'content_type' => self::FORMAT_HTML,
                'https' => false,
                'fields' => [],
            ]
        ],
        'fields' => [

        ]
    ];

    protected $start_points = [];


    //连接队列
    abstract public  function createQueue();

    //连接数据库
    abstract public  function createDatabase();

    //获取爬虫起点
    abstract public function getStartPoints();

    //获取爬虫匹配规则
    abstract public function getRule();

    //爬虫开始前
    public function onStart()
    {
        //
        Log::info('爬虫开始');
    }

    public function onRequest(Client $client)
    {
        //
    }

    public function onResponse(Client $client)
    {

    }


}