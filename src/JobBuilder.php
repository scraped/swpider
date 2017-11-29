<?php

namespace Swpider;

use Illuminate\Support\Arr;
use Swpider\Event\SpiderEvent;
use Swpider\Event\SpiderResponseEvent;
use Swpider\Event\SpiderStartEvent;
use Symfony\Component\DomCrawler\Crawler;


class JobBuilder
{


    private $master;
    private $process;
    private $spider;
    private $time;


    public function __construct(Swpider $master)
    {
        $this->master = $master;
        $this->spider = $master->getSpider();
    }


    //爬虫进程逻辑
    public function start(\swoole_process $worker)
    {
        $this->process = $worker;
        $this->time = time();

        //清空子进程的进程数组
        $this->master->clearWorkers();

        //建立新的连接，避免多进程间相互抢占主进程的连接
        Queue::connect($this->spider->getQueueConfig());
        //建立数据库连接
        Database::connect($this->spider->getDatabaseConfig());


        $this->build();
    }


    protected function build()
    {
        if(! method_exists($this->spider, 'buildJob')){
            $this->process->exit(0);
            return false;
        }

        while(1){
            $jobs = $this->spider->buildJob();

            if(is_null($jobs)){
                break;
            }

            foreach($jobs as $job){
                Queue::addUrl($job['url'], $job['type'], $job['pri']);
            }

            $this->checkMaster();

            sleep(1);
        }
    }


    /**
     * 监控主进程状态
     */
    protected function checkMaster()
    {
        if(!\swoole_process::kill($this->master->pid,0)){
            Log::debug("Master process exited, Children process {$this->process->pid} exit at " . date('Y-m-d H:i:s'));
            $this->process->exit(0);
        }
    }


    protected function isEnableUrl($url, $reentry = false)
    {
        //不存在链接集合中，或者已过了重入时间间隔且已经请求过
        $data = Cache::getUrl($url);

        return ! $data ||
            ( $reentry !== false
                && $data['status'] !== 0
                && time() - $data['last'] > $reentry );

    }

}