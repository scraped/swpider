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



class Swpider extends Command
{
    //主进程id
    public $pid = 0;

    public $version = '0.1';

    protected $logger;
    protected $job;
    protected $spider;
    protected $input;
    protected $output;
    protected $dispatcher;


    //进程池
    private $workers = [];


    protected $spiders = [
        'test' => Spiders\Test::class,
        'xiami' => Spiders\Xiami::class,
    ];


    protected function configure()
    {
        $this->setName('run')
            ->setDescription('start a spider job')
            ->addOption('daemon','d', InputOption::VALUE_NONE, 'set daemon mode')
            ->addOption('build','b', InputOption::VALUE_NONE, 'create job build process')
            ->addOption('test','t', InputOption::VALUE_NONE, 'test mode')
            ->addOption('ui','u', InputOption::VALUE_NONE, 'display panel')
            ->addOption('single','s', InputOption::VALUE_NONE, 'set single mode')
            ->addArgument('spider', InputArgument::REQUIRED, 'spider job');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->dispatcher = new EventDispatcher();
        Log::init($output);

        $this->setupSpider();
    }



    protected function setupSpider()
    {
        $spider = $this->input->getArgument('spider');

        if(! isset($this->spiders[$spider])){
            Log::error("Spider $spider not found!");
            exit(1);
        }

        $this->spider = new $this->spiders[$spider]($this);

        if($this->input->getOption('daemon')){
            \swoole_process::daemon();
        }
        $this->initMaster();
    }

    //初始化主进程
    protected function initMaster()
    {
        $this->pid = posix_getpid();
        swoole_set_process_name(sprintf('spider master:%s', $this->spider->name));

        Log::debug("master start at " . date("Y-m-d H:i:s"));
        Log::debug("master pid is {$this->pid}");


        Log::debug("connecting queue...");
        Queue::connect($this->spider->getQueueConfig());

        //建立redis链接
        Cache::connect($this->spider->getRedisConfig());
        Cache::delWorkers();

        $this->dispatcher->dispatch('spider.ready', new SpiderEvent($this));

        //将索引地址写入请求队列
        foreach($this->spider->getIndexes() as $url){
            Log::debug("push url：{$url}");
            Queue::addIndex($url);
        }

        if($this->input->getOption('test')) {
            $this->createWorker();
            $this->wait();
            exit(0);
        }

        //开启任务生成器
        if($this->input->getOption('build')) {
            $this->createJobBuilder();
        }

        //开启爬虫进程
        for($i = 0; $i < $this->spider->task_num; $i++){
            $this->createWorker();
        }

        if($this->input->getOption('ui')) {
            //开始观察进程
            $this->createWatcher();
        }

        //开始子进程监控
        $this->wait();
    }



    protected function createWorker()
    {
        $worker = new \swoole_process([new Worker($this), 'start']);
        $pid = $worker->start();
        $this->workers[$pid] = $worker;
        Cache::addWorker($pid);
    }

    protected function createWatcher()
    {
        $worker = new \swoole_process([new Watcher($this), 'start']);
        $worker->start();
    }

    protected function createJobBuilder()
    {
        $worker = new \swoole_process([new JobBuilder($this), 'start']);
        $worker->start();
    }



    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function getSpider()
    {
        return $this->spider;
    }

    public function getWorkers()
    {
        return $this->workers;
    }

    public function clearWorkers()
    {
        unset($this->workers);
        return $this;
    }


    protected function wait()
    {
        while(1){
            if(count($this->workers)){
                $ret = \swoole_process::wait();
                if($ret){
                    //从集合中剔除
                    unset($this->workers[$ret['pid']]);
                    //删除缓存
                    Cache::delWorker($ret['pid']);
                    //新建进程，保证进程数
                    //$this->createWorker();
                }
            }else{
                break;
            }
        }
    }


}
