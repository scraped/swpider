<?php

namespace Swpider;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Psr\Log\LogLevel;


class Swpider extends Command
{
    protected $logger;
    protected $verbosityLevelMap;
    protected $job;
    protected $spider;
    protected $input;
    protected $output;

    //子进程
    protected $mpid = 0;
    private $workers = [];

    protected $spiders = [
        'test' => Spiders\Test::class,
    ];


    protected function configure()
    {
        $this->setName('run')
            ->setDescription('start a spider job')
            ->addArgument('spider', InputArgument::REQUIRED, 'spider job');

        $this->verbosityLevelMap = array(
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO   => OutputInterface::VERBOSITY_NORMAL,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->logger = new ConsoleLogger($output, $this->verbosityLevelMap);

        $this->setupSpider();
    }



    protected function setupSpider()
    {
        $spider = $this->input->getArgument('spider');

        if(! isset($this->spiders[$spider])){
            $this->logger->error("Spider $spider not found!");
            exit(1);
        }

        $this->spider = new $this->spiders[$spider]($this);

        $this->initMaster();
    }

    //初始化主进程
    protected function initMaster()
    {
        $this->logger->info("master started!");
        $this->spider->createQueue();

        swoole_set_process_name(sprintf('Spider:%s', $this->spider->name));
        $this->mpid = posix_getpid();

        //将索引地址写入请求队列
        foreach($this->spider->getIndexes() as $url){
            Queue::addIndex($url);
        }

        //开启爬虫子进程
        for($i = 0; $i < $this->spider->task_num; $i++){
            $this->createWorker();
        }

        //开始观察子进程
        //$this->createWatcher();

        //开始子进程监控
        $this->wait();
    }



    protected function createWorker()
    {
        $worker = new \swoole_process([$this, 'worker']);
        $pid = $worker->start();
        $this->workers[$pid] = $worker;
    }


    //子进程逻辑
    public function worker()
    {
        $this->spider->onStart();
        //清空子进程的进程数组
        unset($this->workers);
        //建立新的连接，避免多进程间相互抢占主进程的连接
        $this->spider->createQueue();
        //建立数据库连接
        $this->spider->createDatabase();


        //操作队列
        $this->handleQueue();

    }

    //操作队列
    protected function handleQueue()
    {
        //从队列取任务
        while($job = Queue::getUrl()){

            $this->resolverJob($job);
        }
    }


    //执行队列任务
    protected function resolverJob($job)
    {
        $this->logger->info("Request url: {$job['url']}");
        Request::get($job['url'], [], function($err, $client) use ($job){

            if(isset($err)){
                Queue::releaseUrl($job);
                throw $err;
            }

            if($job['type'] == 'index'){

            }


            Queue::deleteUrl($job);
        });
    }



    //检查主进程是否已结束
    protected function watchMaster(\swoole_process &$worker)
    {
        if(! \swoole_process::kill($this->mpid, 0)){
            $worker->exit(0);
            $this->logger->notice("Master process exited! Process {$worker['pid']} quit now.");
        }
    }


    protected function wait()
    {
        while(1){
            if(count($this->workers)){
                $ret = \swoole_process::wait();
                if($ret){
                    //新建进程，保证进程数
                    //$this->createWorker();
                }
            }else{
                break;
            }
        }
    }


}
