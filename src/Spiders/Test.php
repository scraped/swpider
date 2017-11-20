<?php

namespace Swpider\Spiders;

use Swpider\Spider;
use Swpider\Log;
use Swpider\Swpider;

class Test extends Spider
{
    public $name = 'luoo';

    public $task_num = 5;

    public $queue_name = 'luoo';

    protected $db_name = 'swpider';
    protected $db_user = 'root';
    protected $db_password = '123456';

    protected $indexes = [
        'http://dev.luoo.net/music/'
    ];

    protected $rules = [
        'fields' => [
            'title' => [
                'type' => 'css',
                'selector' => 'h1.vol-name > span.vol-title',
                'value' => 'text',
                'multi' => false
            ]
        ],
        'url' => [
            'vol_list' => [
                'regex' => "http:\/\/dev.luoo.net\/tag\/?p=\d+",
                'reentry' => 86400,
            ],
            'vol' => [
                'regex' => "http:\/\/dev.luoo.net\/vol\/index\/\d+",
                'fields' => ['title'],
                'reentry' => 86400,
            ]

        ],
    ];

    public function __construct($swpider)
    {
        parent::__construct($swpider);

        $this->bind('spider.ready', [$this, 'onReady']);
    }


    public function bind($event, $action)
    {
        $this->cmd->getDispatcher()->addListener($event, $action);
    }

    public function onReady()
    {
        var_dump('spider ready!!!!!');
    }

    public function onStart()
    {
        Log::info("Spider $this->name start at ".date("Y-m-d H:i:s"));
    }


    public function onResponse($response, $data)
    {
        var_dump($data);
    }

}


