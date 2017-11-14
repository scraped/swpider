<?php

namespace Swpider\Spiders;

use Swpider\Spider;
use Swpider\Log;

class Test extends Spider
{
    public $name = 'luoo';

    protected $start_points = [
        'http://dev.luoo.net/music/'
    ];

    protected $rules = [
        'fields' => [
            'title' => [
                'type' => 'css',
                'selector' => 'h1.vol > span.vol-title',
                'value' => '@class',
                'multi' => false
            ]
        ],
        'url' => [
            'name' => 'vol',
            'regex' => "http://dev.luoo.net/tag/?p=\d+",
            'fields' => ['title'],
        ],
    ];


    public function onStart()
    {
        Log::info("Spider $this->name start at ".date("Y-m-d H:i:s"));
    }

}


