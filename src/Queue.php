<?php

namespace Swpider;

use Illuminate\Support\Arr;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;

class Queue
{

    const DEFAULT_HOST = "127.0.0.1";
    const DEFAULT_PORT = 11300;
    const DEFAULT_TIMEOUT = 1;

    const PRI_INC = 10;
    const PRI_INDEX = 100;
    const PRI_LIST = 200;
    const PRI_CONTENT = 300;

    const DELAY = 1;
    const MAX_RETRY = 5;

    private static $_queue;


    protected static $config;


    public static function connect($config = null)
    {
        if(isset(self::$_queue) && self::$_queue instanceof Queue\SwooleBeanstalk){
            self::$_queue->getConnection()->disconnect();
        }

        self::$config = $config ? : self::defaultConfig();
        self::$_queue = new Pheanstalk(self::$config['host'], self::$config['port'], Arr::get($config,'timeout', self::DEFAULT_TIMEOUT));

        if(isset($config['name'])){
            self::$_queue->watchOnly($config['name']);
            self::$_queue->useTube($config['name']);
        }

    }


    protected static function queue()
    {
        if(is_null(self::$_queue)){
            self::connect();
        }

        return self::$_queue;
    }


    protected static function defaultConfig()
    {
        $config = [
            'host' => self::DEFAULT_HOST,
            'port' => self::DEFAULT_PORT,
            'timeout' => self::DEFAULT_TIMEOUT
        ];

        return $config;
    }

    public static function addIndex($url)
    {
        $body = self::encodeData([
            'type' => 'index',
            'url' => $url,
        ]);

        $pri = 100;

        self::queue()->put($body, $pri);
    }

    public static function addUrl($url, $type, $pri = 100)
    {
        $body = self::encodeData([
            'type' => $type,
            'url' => $url,
        ]);

        $pri = 100;

        self::queue()->put($body, $pri);
    }


    public static function getUrl($timeout = null)
    {
        $job = self::queue()->reserve($timeout);

        if($job instanceof Job){
            return self::decodeJob($job);
        }else{
            return $job;
        }
    }

    public static function buryUrl($obj)
    {
        $job = self::encodeJob($obj);
        $state = self::queue()->statsJob($job);

        self::queue()->bury($job, $state->pri + self::PRI_INC*1000);
    }


    public static function releaseUrl($obj)
    {
        $job = self::encodeJob($obj);
        $state = self::queue()->statsJob($job);

        if($state->releases > self::MAX_RETRY){
            //超过最大次数，该任务设为无效
            self::queue()->bury($job, $state->pri + self::PRI_INC);
        }else{
            //释放回队列，降低优先级，延迟1秒
            self::queue()->release($job, $state->pri + self::PRI_INC, self::DELAY);
        }

    }

    public static function deleteUrl($obj)
    {
        self::queue()->delete(self::encodeJob($obj));
    }


    public static function stats()
    {
        return self::queue()->statsTube(Arr::get(self::$config, 'name', 'default'))
            ->getArrayCopy();
    }



    protected static function encodeData($data)
    {
        return json_encode($data);
    }

    protected static function decodeData($data)
    {
        return json_decode($data, true);
    }


    protected static function encodeJob($obj)
    {
        if(!isset($obj['_id'])){
            return false;
        }

        $id = $obj['_id'];
        unset($obj['_id']);

        return new Job($id, self::encodeData($obj));
    }

    protected static function decodeJob($job)
    {
        $data = self::decodeData($job->getData());
        $data['_id'] = $job->getId();

        return $data;
    }


    public static function __callStatic($name, $arguments)
    {
        if(isset(self::$_queue) && method_exists(self::$_queue, $name)){
            return call_user_func_array([self::$_queue, $name], $arguments);
        }

        throw new \BadMethodCallException("class ".__CLASS__ ." static method $name unsupported");
    }

}