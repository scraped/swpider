<?php

namespace Swpider\Spiders;

use Swpider\Spider;
use Swpider\Log;
use Swpider\Swpider;

class Xiami extends Spider
{
    public $name = 'xiami';

    public $task_num = 2;

    public $queue_name = 'xiami';

    protected $db_name = 'swpider';
    protected $db_user = 'root';
    protected $db_password = '123456';

    protected $indexes = [
        'http://www.xiami.com/artist/index/c/1/type/0'
    ];

    protected $rules = [
        'fields' => [
            'artist' => [
                'type' => 'css',
                'selector' => '#artists .info > p > strong > a',
                'group' => [
                    'name' => [
                        'value' => 'text'
                    ],
                    'href' => [
                        'value' => '@href'
                    ]
                ],
                'multi' => true
            ]
        ],
        'url' => [
            'artist_list' => [
                'regex' => "http:\/\/www.xiami.com/artist/index/c/\d+\/type\/\d+/class/\d+/page/\d+",
                'reentry' => 86400,
                'fields' => ['artist'],
            ]

        ],
    ];

    protected $auths = [
        'default' => [
            ''
        ]
    ];


    protected $cookies = [
        'default' => 'gid=150935778212494; _unsign_token=b0b42855116f846ff9eb88c3fba77af9; UM_distinctid=15f6cbc0aef713-0b052fc662a29b-18396d56-13c680-15f6cbc0af0b44; cna=eLLGEIY9ujgCAXF3RVrBXTcH; bdshare_firstime=1509357804488; XMPLAYER_url=/song/playlist/id/45118/object_name/default/object_id/0; XMPLAYER_addSongsToggler=0; XMPLAYER_volumeValue=1; __guestplay=NDUxMTgsMg%3D%3D; XMPLAYER_isOpen=0; CNZZDATA921634=cnzz_eid%3D436256939-1509354111-%26ntime%3D1509586311; CNZZDATA2629111=cnzz_eid%3D1942348430-1509357591-%26ntime%3D1509584392; _xiamitoken=92322347873af6dd0ce7fb950e173262; isg=AkpKIZv_JJZVfqvf_CvbTt7YmzYsk883jTwjLNSDSh0oh-5BvcjspPG14cWg; login_method=emaillogin; member_auth=2TudHIcZ7Gg20feVTNgzJXcW4LbcGTLUxNlViLYlvwZwcYwJYYaoxquXRA1L3imqfvORwT09; user=666369%22%E6%9B%BE%E5%B0%91%22images%2Favatar_new%2F13%2F32%2F666369%2F666369_1305105802_1.jpg%220%226923%22%3Ca+href%3D%27http%3A%2F%2Fwww.xiami.com%2Fwebsitehelp%23help9_3%27+%3ELv7%3C%2Fa%3E%2232%2229%2216135%22fbfba9713b%221511236945',
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


    /**
     * 验证请求返回内容
     * @param $response
     * @return bool
     */
    public function verifyResponse($response)
    {
        return true;
    }


    /**
     * 是否需要登录
     * @param $response
     * @return bool
     */
    public function isNeedLogin($response)
    {
        return false;
    }




}


