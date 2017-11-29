<?php

namespace Swpider\Spiders;

use Swpider\Database;
use Swpider\Spider;
use Swpider\Log;
use Illuminate\Support\Arr;

class Xiami extends Spider
{
    public $name = 'xiami';

    public $task_num = 4;

    public $queue_name = 'xiami';

    protected $db_name = 'swpider';
    protected $db_user = 'root';
    protected $db_password = '123456';

    protected $indexes = [
        //'http://www.xiami.com/artist/index/c/1/type/0',
        'http://www.xiami.com/artist/album-dz264c5b'
    ];
    public $domain = "http://www.xiami.com";
    public $strict = true;
    public $log_path = ROOT_PATH.'/samplings/';

    protected $rules = [
        'fields' => [
            'artist' => [
                'type' => 'css',
                'selector' => '#artists .info > p > strong > a',
                'group' => [
                    'name' => [
                        'getter' => 'text'
                    ],
                    'href' => [
                        'getter' => '@href'
                    ]
                ],
                'multi' => true,
            ],
            'artist_detail_album' => [
                'type' => 'css',
                'selector' => '.albumThread_list div.info > .detail > p.name > a',
                'group' => [
                    'name' => [
                        'selector' => 'strong',
                        'getter' => 'text'
                    ],
                    'href' => [
                        'getter' => '@href'
                    ]
                ],
                'multi' => true,
            ],
            'artist_album_artist' => [
                'type' => 'css',
                'selector' => '#artist_profile > .content > p > a',
                'group' => [
                    'name' => [
                        'getter' => 'text'
                    ],
                    'href' => [
                        'getter' => '@href'
                    ]
                ],
            ],
        ],
        'url' => [
            'artist_albums_list' => [
                'regex' => "\/artist\/album\-[^\.\n\"\?]*\?d=\&p=\&c=\&page=\d+",
                'reentry' => 1,
                'fields' => ['artist_detail_album', 'artist_album_artist'],
                'priority' => 90,
            ],

        ],
    ];

    public $auths = [
        'default' => [
            ''
        ]
    ];


    public $cookies = [
        'default' => 'gid=150935778212494; _unsign_token=b0b42855116f846ff9eb88c3fba77af9; UM_distinctid=15f6cbc0aef713-0b052fc662a29b-18396d56-13c680-15f6cbc0af0b44; cna=eLLGEIY9ujgCAXF3RVrBXTcH; bdshare_firstime=1509357804488; XMPLAYER_url=/song/playlist/id/45118/object_name/default/object_id/0; XMPLAYER_addSongsToggler=0; XMPLAYER_volumeValue=1; __guestplay=NDUxMTgsMg%3D%3D; XMPLAYER_isOpen=0; CNZZDATA921634=cnzz_eid%3D436256939-1509354111-%26ntime%3D1509586311; CNZZDATA2629111=cnzz_eid%3D1942348430-1509357591-%26ntime%3D1509584392; _xiamitoken=92322347873af6dd0ce7fb950e173262; isg=AkpKIZv_JJZVfqvf_CvbTt7YmzYsk883jTwjLNSDSh0oh-5BvcjspPG14cWg; login_method=emaillogin; member_auth=2TudHIcZ7Gg20feVTNgzJXcW4LbcGTLUxNlViLYlvwZwcYwJYYaoxquXRA1L3imqfvORwT09; user=666369%22%E6%9B%BE%E5%B0%91%22images%2Favatar_new%2F13%2F32%2F666369%2F666369_1305105802_1.jpg%220%226923%22%3Ca+href%3D%27http%3A%2F%2Fwww.xiami.com%2Fwebsitehelp%23help9_3%27+%3ELv7%3C%2Fa%3E%2232%2229%2216135%22fbfba9713b%221511236945',
    ];

    public function __construct($swpider)
    {
        parent::__construct($swpider);

        $this->bind('spider.ready', [$this, 'onReady']);
        $this->bind('spider.start', [$this, 'onStart']);
        $this->bind('spider.response', [$this, 'onResponse']);
    }




    public function onReady()
    {
        Log::debug('xiami spider ready!');
    }

    public function onStart()
    {
        Log::debug("spider $this->name start at ".date("Y-m-d H:i:s"));
    }


    public function onResponse($event)
    {
        $data = $event->getData();

        switch ($data['type']){
            case 'artist_list':

                $artists = $data['data']['artist'];

                if(empty($artists)){
                    return false;
                }

                foreach($artists as $artist){
                    $id = str_replace("/artist/", '', $artist['href']);
                    $name = preg_replace("#\([^\(]*\)#ui", '', $artist['name']);

                    $insert_data = [
                        'xiami_id' => $id,
                        'name' => $name,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];

                    Database::table('xiami_artist')->updateOrInsert([
                        'xiami_id' => $id,
                    ],$insert_data);
                }
                break;
            case 'artist_albums_list':
                $albums = $data['data']['artist_detail_album'];

                if(empty($albums)){
                    return false;
                }

                $artist = $data['data']['artist_album_artist'];
                $artist_id = str_replace("/artist/", '', $artist['href']);
                $artist_name = preg_replace("#\([^\(]*\)#ui", '', $artist['name']);

                foreach($albums as $album){
                    $id = str_replace("/album/", '', $album['href']);
                    $name = $album['name'];

                    $insert_data = [
                        'xiami_id' => $id,
                        'name' => $name,
                        'artist_id' => $artist_id,
                        'artist_name' => $artist_name,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];

                    Database::table('xiami_album')->updateOrInsert([
                        'xiami_id' => $id,
                    ],$insert_data);
                }
                break;

        }
    }


    public function getStat()
    {
        $artists = Database::table('xiami_artist')->count();
        $albums = Database::table('xiami_album')->count();

        return [
            'artists' => $artists,
            'album' => $albums,
        ];
    }

    public function testJob()
    {
        $this->worker->testJob([
            'type' => 'artist_albums_list',
            'url' => 'http://www.xiami.com/artist/album-dz264c5b'
        ], function($data){
            var_dump($data['data']);
        });
    }

    public function buildJob()
    {
        $artists = Database::table('xiami_artist')->get()->all();

        if(empty($artists)){
            return null;
        }

        return array_map(function($item){

            return [
                'url' => "/artist/album-" . $item->xiami_id,
                'type' => 'artist_albums_list',
                'pri' => 100,
            ];

        }, $artists);

    }



    public function verifyResponse($response, $content)
    {
        return self::RES_NORMAL;
    }


    public function login()
    {

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



    public function getCookies($key = 'default')
    {
        return $this->cookies[$key];
    }





}


