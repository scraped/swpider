<?php

namespace Swpider;

use Illuminate\Support\Arr;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Swpider\Event\SpiderEvent;



class Watcher
{
    const SLEEP = 100;

    private $master;
    private $process;
    private $spider;
    private $outputed = false;
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

        //进程命名
        swoole_set_process_name(sprintf('spider watcher:%s', $this->spider->name));

        //建立新的连接，避免多进程间相互抢占主进程的连接
        Queue::connect($this->spider->getQueueConfig());
        //建立数据库连接
        Database::connect($this->spider->getDatabaseConfig());
        //建立redis链接
        Cache::connect($this->spider->getRedisConfig());

        $this->watch();

    }


    protected function watch()
    {
        while(1){

            if(!$this->outputed){
                $this->outputed = true;
            }else{
                Log::clear();
            }

            //获取队列状态
            $queue_stat = Queue::stats();

            //获取缓存状态
            $cache_stat = Cache::stats();

            $workers = Cache::getWorkers();

            //print_r($workers);


            //数据库状态
            //$data_stat = $this->spider->getStat();

            //$this->output($queue_stat, $cache_stat);

            $this->checkMaster();

            sleep(1);
        }
    }

    protected function output($queue_stat, $cache_stat = [], $workers = [], $extra = [])
    {
        Log::writeln("================================ Swpider ===================================");
        Log::writeln("pid: %d,\tstart at: %s,\trunning: %s", $this->master->pid, date("Y-m-d H:i:s", $this->time), $this->convertTime(time() - $this->time));
        Log::writeln("ready: %d,\treserved: %d,\tburied: %d,\tdone: %d,\turls: %d",
            $queue_stat['current-jobs-ready'],
            $queue_stat['current-jobs-reserved'],
            $queue_stat['current-jobs-buried'],
            $queue_stat['total-jobs'],
            $cache_stat['urls']);

        Log::writeln("");
        $format = "% 6s % 10s % 6s % 20s %s";     // pid, 状态， 内存， 运行时间， 当前url
        Log::writeln("\033[7m" . $format . "\033[0m", "pid", "stat", "mem", "uptime", "url");

        foreach($workers as $worker){
            $pid = $worker['pid'];
            $stat = $worker['stat'];
            $mem = round($worker['memory']/1000000,2) . 'm';
            $uptime = round(($worker['usage']['ru_utime.tv_usec'] + $worker['usage']['ru_stime.tv_usec']) /1000000, 6) . 's';
            $url = $worker['url'];

            Log::writeln($format, $pid, $stat, $mem, $uptime, $url);
        }


    }


    private function convertTime($seconds)
    {
        $re = '';
        if($seconds > 86400){
            $re = round($seconds / 86400) . 'd';
            $seconds = $seconds % 86400;
        }

        if($seconds > 3600){
            $re .= round($seconds / 3600) . 'h';
            $seconds = $seconds % 3600;
        }

        if($seconds > 60){
            $re .= round($seconds / 60) . 'm';
            $seconds = $seconds % 60;
        }

        if($seconds > 0){
            $re .= $seconds . 's';
        }

        return $re;
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

}
