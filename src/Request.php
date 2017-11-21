<?php

namespace Swpider;

use Closure;
use Exception;
use GuzzleHttp\Client;


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
    const TIMEOUT = 2;
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


    private static $_client;


    public static function client()
    {
        if(!isset(self::$_client)){
            self::$_client = new Client([
                'timeout' => self::TIMEOUT,
            ]);
        }

        return self::$_client;
    }


    public static function request($method, $url, $option = [])
    {
        return self::client()->request($method, $url, $option);
    }





}