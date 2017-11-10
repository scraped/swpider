<?php

namespace Swpider;

use Closure;
use Exception;

/**
 * 请求封装类
 * @package Swpider
 */
class Request
{
    //DNS 缓存
    public static $domain_dns = [];
    //Cookie 缓存
    public static $domain_cookies = [];
    //头部 缓存
    public static $domain_headers = [];
    //user_agent
    public static $user_agents = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36',
    ];
    public static $accept = [
        'html'=>'text/html,application/xhtml+xml,application/xml',
    ];
    //ip 名单
    public static $ips = [];
    //超时时间 5秒
    public static $timeout = 5.0;
    //重试次数 3次
    public static $retry = 2;
    //重试次数 3次
    public static $retry_interval = 0;
    //重试队列
    protected static $retry_queue = [];


    public static function send(string $method,string $url,array $params,callable $callback, \Swoole\Http\Client $client = null)
    {
        $url_parse = parse_url($url);
        $host = $url_parse['host'];
        $args = func_get_args();

        if(!isset(self::$domain_dns[$host])){
            \Swoole\Async::dnsLookup($host, function($domainName, $ip) use ($args){
                self::$domain_dns[$domainName] = $ip;
                call_user_func_array([__CLASS__,'send'],$args);
            });
        }else{
            $ip = self::$domain_dns[$host];
            $client = new \swoole_http_client($ip, self::switchPort($url_parse), $url_parse['scheme'] == 'https');
            $headers = [
                'Host' => $host,
                'User-Agent' => self::$user_agents[rand(0, count(self::$user_agents)-1)],
                'Accept' => self::$accept['html'],
                'Accept-Encoding' => 'gzip, deflate',
            ];
            $config = [
                'timeout'=>self::$timeout,
            ];

            if(isset(self::$domain_headers[$host])){
                $headers += self::$domain_headers[$host];
            }

            if(isset($params['header'])){
                $headers += $params['header'];
            }

            if(isset(self::$domain_cookies[$host])){
                $client->setCookies(self::$domain_cookies[$host]);
            }

            if(isset($params['body']) && !in_array(strtolower($method),['get','head'])){
                $client->setData($params['body']);
            }

            $client->set($config);
            $client->setHeaders($headers);
            $client->setMethod($method);

            if(!isset(self::$retry_queue[$host])){
                self::$retry_queue[$host] = 0;
            }

            $client->execute(self::convertPath($url_parse),function ($client) use ($args) {
                $client->close();

                $host = $client->requestHeaders['Host'];
                $callback = $args[3];

                if($client->errCode === 0){
                    //更新cookies
                    if(isset($cli->cookies)){
                        self::$domain_cookies[$host] = $client->cookies;
                    }
                    call_user_func($callback,null,$client);
                    unset(self::$retry_queue[$host]);

                }else{
                    //重试请求
                    if(self::$retry_queue[$host] >= self::$retry){
                        unset(self::$retry_queue[$host]);
                        call_user_func($callback,new Exception('Request Failed!'),$client);
                    }else{
                        sleep(self::$retry_interval);
                        self::$retry_queue[$host]++;
                        call_user_func_array([__CLASS__,'send'],$args);
                    }
                }
            });

        }
    }

    public static function get($url, array $header = [], callable $callback)
    {
        self::send('GET', $url, ['header'=>$header], $callback);
    }

    public static function post($url, array $data = [], array $header = [], callable $callback)
    {
        $params = [
            'header' => $header,
            'body'   => $data,
        ];
        self::send('POST', $url, $params, $callback);
    }


    private static function switchPort($url_parse)
    {
        if(isset($url_parse['port'])){
            return intval($url_parse['port']);
        }
        if($url_parse['scheme'] === 'https'){
            return 443;
        }

        return 80;
    }

    private static function convertPath($url_parse)
    {
        $path = empty($url_parse['path']) ? '/' : $url_parse['path'];
        if(!empty($url_parse['query'])){
            $path .= "?" . $url_parse['query'];
        }

        return $path;
    }




}