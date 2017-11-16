<?php

namespace Swpider;

use Swoole\Http\Client;
use Swpider\Queue;
use Swpider\Swpider;

abstract class Spider
{
    const URL_LIST = 1;
    const URL_CONTENT = 2;

    const FORMAT_HTML = 1;
    const FORMAT_JSON = 2;
    const FORMAT_JSONP = 3;
    const FORMAT_TEXT = 4;

    public $cmd;
    //爬虫名
    public $name = 'swpider';
    //进程数
    public $task_num = 1;



    //数据库设置
    protected $db;
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
    protected $queue;
    protected $queue_host = '127.0.0.1';
    protected $queue_port = 11300;
    protected $queue_timeout = 1;
    protected $queue_name = 'swpider';


    public function __construct(Swpider $swpider)
    {
        $this->cmd = $swpider;
    }

    //爬虫规则
    protected $rules = [
        'url' => [
            [
                'name' => 'test',                       //规则名称，唯一，用于队列命名
                'regex' => '',                          //链接格式
                'group_regex' => '',                    //链接分组格式，比如一个正文分为几页，那么所有匹配视为一组链接,
                'type' => self::URL_LIST,               //链接类型
                'content_type' => self::FORMAT_HTML,    //内容类型
                'https' => false,                       //是否需要HTTPS
                'fields' => [],                         //分析字段，对应为下面的fields数组的key值
            ]
        ],
        'fields' => [
            'title' => [
                'default' => null,                        //如果分析失败，设置的对应值，默认为NULL
                'selector'=> '',                          //选择器
            ]
        ]
    ];

    protected $indexes = [];


    //连接队列
    public  function createQueue()
    {
        $config = [
            'name' => isset($this->queue_name) ? $this->queue_name : $this->name,
            'host' => $this->queue_host,
            'port' => $this->queue_port,
            'timeout' => $this->queue_timeout,
        ];
        Queue::connect($config);
    }

    //连接数据库
    public  function createDatabase()
    {
        $config = [
            'host'      => $this->db_host,
            'port'      => $this->db_port,
            'database'  => $this->db_name,
            'username'  => $this->db_user,
            'password'  => $this->db_password,
            'charset'   => $this->db_charset,
            'collation' => $this->db_collation,
            'prefix'    => $this->db_prefix,
            'timezone'  => $this->db_timezone,
            'strict'    => $this->db_strict,
        ];
        Database::connect($config);
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getRules()
    {
        return $this->rules;
    }


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