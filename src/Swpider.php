<?php

namespace Swpider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\DomCrawler\Crawler;
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
        Log::init($output,$this->verbosityLevelMap);
        //$this->logger = new ConsoleLogger($output, $this->verbosityLevelMap);

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

        $this->initMaster();
    }

    //初始化主进程
    protected function initMaster()
    {
        Log::info("master started!");
        Queue::connect($this->spider->getQueueConfig());

        swoole_set_process_name(sprintf('Spider Master:%s', $this->spider->name));
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

        //进程命名
        swoole_set_process_name(sprintf('Spider Pool:%s', $this->spider->name));

        //建立新的连接，避免多进程间相互抢占主进程的连接
        Queue::connect($this->spider->getQueueConfig());
        //建立数据库连接
        Database::connect($this->spider->getDatabaseConfig());
        //建立redis
        Cache::connect($this->spider->getRedisConfig());


        //操作队列
        $this->handleQueue();

    }

    //操作队列
    protected function handleQueue()
    {
        //从队列取任务
        while($job = Queue::getUrl()){
            //解析任务
            $this->resolverJob($job);

        }
    }


    //执行队列任务
    protected function resolverJob($job)
    {
        Log::info("Requesting: {$job['url']}");

        $client = new Client([
            'time' => 2.0
        ]);

        try{
            $response = $client->get($job['url']);
        }catch(ClientException $e){
            if($this->needRetry($e)){
                return false;
            }

            Queue::releaseUrl($job);
            throw $e;
        }

        Log::info("Requested: {$job['url']}");


        //解析网页内容
        $rules = $this->spider->getRules();
        $content = $response->getBody()->getContents();
        foreach($rules['url'] as $name=>$option){
            $regex = $option['regex'];
            //解析可用链接
            if(preg_match_all("#{$regex}#iu", $content, $matches)){
                foreach($matches[0] as $url){
                    //检查是否可用链接
                    if(! $this->isEnableUrl($url, $option['reentry'])){
                        Log::debug("disable url: $url");
                        continue;
                    }

                    //加入队列
                    Queue::addUrl($url, $name);
                    //写入缓存
                    Cache::setUrl($url,0);
                }
            }
        }


        $fields = Arr::get($rules,'url.'.$job['type'].'.fields', false);

        //解析字段
        if($job['type'] !== 'index' && !empty($fields)){

            $data = [
                'type' => $job['type']
            ];
            $crawler = new Crawler($content);
            foreach($fields as $field){
                $rule = $rules['fields'][$field];

                $re = $crawler->filter($rule['selector']);
                $value_rule = Arr::get($rule, 'value', 'text');

                if(Arr::get($rule, 'multi', false)){
                    $value = [];
                    $re->each(function($node) use ($value_rule, &$value){
                        $value[] = $this->getValue($value_rule,$node);
                    });
                }else{
                    $value = $this->getValue($value_rule,$re);
                }

                $data['data'][$field] = $value;
            }

            $this->spider->onResponse($response, $data);
        }


        //移出队列
        Queue::deleteUrl($job);
        //更新缓存
        Cache::setUrl($job['url'], 1);

    }

    protected function needRetry()
    {
        return false;
    }

    protected function getValue($rule,Crawler $node)
    {
        if(strpos($rule, '@') === 0){
            return $node->attr(substr($rule,1));
        }

        return $node->text();
    }

    //判断是否为可用的链接
    protected function isEnableUrl($url, $reentry = false)
    {
        //不存在链接集合中，或者已过了重入时间间隔且已经请求过
        $data = Cache::getUrl($url);

        return ! $data ||
            ( $reentry !== false
                && $data['status'] !== 0
                && time() - $data['last'] > $reentry );

    }



    //检查主进程是否已结束
    protected function watchMaster(\swoole_process &$worker)
    {
        if(! \swoole_process::kill($this->mpid, 0)){
            $worker->exit(0);
            Log::notice("Master process exited! Process {$worker['pid']} quit now.");
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
