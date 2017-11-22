<?php

namespace Swpider;

use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;


/**
 * 请求封装类
 * @package Swpider
 *
 * @method static get(string $uri, array $options = [])
 * @method static head(string $uri, array $options = [])
 * @method static put(string $uri, array $options = [])
 * @method static post(string $uri, array $options = [])
 * @method static patch(string $uri, array $options = [])
 * @method static delete(string $uri, array $options = [])
 */
class Request
{
    const TIMEOUT = 5;
    //DNS 缓存
    public static $domain_dns = [];
    //Cookie 缓存
    public static $domain_cookies = [];
    //头部 缓存
    public static $domain_headers = [];
    //user_agent
    public static $user_agents = [
        'pc' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36',
    ];
    public static $accept = [
        'html'=>'text/html,application/xhtml+xml,application/xml',
    ];
    //ip 名单
    public static $ips = [];

    //重试次数 3次
    public static $retry = 2;
    //重试次数 3次
    public static $retry_interval = 0;
    //重试队列
    protected static $retry_queue = [];

    private static $_config;

    private static $_client;

    private static $_cookie;
    private static $_cookie_modify = false;

    public static function init($config = [])
    {
        self::$_config = $config;

        if(isset($config['cookies'])){
            self::setCookie($config['cookies']);
        }
    }

    public static function client()
    {
        if(!isset(self::$_client)){
            self::$_client = new Client([
                'timeout' => self::TIMEOUT,
                'cookies' => isset(self::$_cookie),
            ]);
        }

        return self::$_client;
    }


    public static function setCookie($str_cookie)
    {
        self::$_cookie = new CookieJar(SetCookie::fromString($str_cookie));
        self::$_cookie_modify = true;
    }


    public static function request($method, $url, $option = [])
    {
        if(isset(self::$_cookie) && self::$_cookie_modify && !isset($option['cookies'])){
            $option['cookies'] = self::$_cookie;
            self::$_cookie_modify = false;
        }
        return self::client()->request($method, $url, $option);
    }


    public static function __callStatic($name, $arguments)
    {
        if (count($arguments) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $arguments[0];
        $opts = isset($arguments[1]) ? $arguments[1] : [];

        return self::request($name, $uri, $opts);
    }


}