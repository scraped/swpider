<?php

namespace Swpider;

abstract class Spider
{
    const URL_LIST = 1;
    const URL_CONTENT = 2;

    const FORMAT_HTML = 1;
    const FORMAT_JSON = 2;
    const FORMAT_JSONP = 3;
    const FORMAT_TEXT = 4;


    const RES_NORMAL = 1;           //正常状态
    const RES_LOGIN = 2;            //需要登录
    const RES_VERIFY = 3;           //需要验证请求
    const RES_PROXY = 4;            //需要代理
    const RES_EXCEPT = -1;          //其他异常



    public $cmd;
    //爬虫名
    public $name = 'swpider';
    //进程数
    public $task_num = 1;

    //设置返回编码
    public $from_encode = 'utf-8';
    public $to_encode = 'utf-8';

    //设置域名
    public $domain = '';


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


    //redis设置
    protected $redis;
    protected $redis_host = '127.0.0.1';
    protected $redis_port = '6379';
    protected $redis_prefix = '';
    protected $redis_scheme = 'tcp';


    protected $worker;


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
    public  function getQueueConfig()
    {
        $config = [
            'name' => isset($this->queue_name) ? $this->queue_name : $this->name,
            'host' => $this->queue_host,
            'port' => $this->queue_port,
            'timeout' => $this->queue_timeout,
        ];

        return $config;
    }

    //连接数据库
    public  function getDatabaseConfig()
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

        return $config;
    }


    public function getRedisConfig()
    {
        $config = [
            'scheme' => $this->redis_scheme,
            'host' => $this->redis_host,
            'port' => $this->redis_port,
            'prefix' => $this->redis_prefix ? : $this->name,
        ];

        return $config;
    }

    public function setWorker($worker)
    {
        $this->worker = $worker;
    }

    /**
     * 验证请求返回内容
     * @param $response
     * @param $content
     * @return bool
     */
    public function verifyResponse($response, $content)
    {
        return true;
    }


    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function getStat()
    {
        return [];
    }


    public function bind($event, $action)
    {
        $this->cmd->getDispatcher()->addListener($event, $action);
    }

}