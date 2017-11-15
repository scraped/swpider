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
    protected $works = [];

    protected $jobs = [
        'test' => Spiders\Test::class,
    ];


    protected function configure()
    {
        $this->setName('run')
            ->setDescription('start a spider job')
            ->addArgument('job', InputArgument::REQUIRED, 'spider job');

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

        $this->resolverJob();
    }



    protected function resolverJob()
    {
        $job = $this->input->getArgument('job');

        if(! isset($this->jobs[$job])){
            $this->logger->error("job $job not found!");
            exit(1);
        }

        $this->spider = new $this->jobs[$job]($this);

        $this->initMaster();
    }

    //初始化主进程
    protected function initMaster()
    {
        $this->spider->createQueue();
        $this->spider->createDatabase();

        swoole_set_process_name(sprintf('Spider:%s', $this->spider->name));
        $this->mpid = posix_getpid();

        //将索引地址写入请求队列
        foreach($this->spider->getIndexes() as $url){
            Queue::addIndex($this->spider->name,$url);
        }

        //开启爬虫子进程
        for($i = 0; $i < $this->spider->task_num; $i++){
            $this->createWorker();
        }

        //开始观察子进程
        $this->createWatcher();

        //开始子进程监控
        $this->waitWorkers();
    }



    protected  function createWorker($index = null)
    {
        $worker = new \swoole_process([$this, 'worker'], true);
        $pid = $worker->start();
    }


    protected function worker()
    {

    }


}
