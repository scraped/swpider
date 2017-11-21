<?php

namespace Swpider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;
use Swpider\Event\SpiderEvent;
use Swpider\Event\SpiderResponseEvent;
use Swpider\Event\SpiderStartEvent;
use Symfony\Component\DomCrawler\Crawler;


class Worker
{
    const MAX_WAIT = 10;

    private $master;
    private $process;
    private $spider;

    public function __construct(Swpider $master)
    {
        $this->master = $master;
        $this->spider = $master->getSpider();
    }


    //爬虫进程逻辑
    public function start(\swoole_process $worker)
    {
        $this->process = $worker;

        //清空子进程的进程数组
        $this->master->clearWorkers();

        //进程命名
        swoole_set_process_name(sprintf('spider pool:%s', $this->spider->name));

        //建立新的连接，避免多进程间相互抢占主进程的连接
        Queue::connect($this->spider->getQueueConfig());
        //建立数据库连接
        Database::connect($this->spider->getDatabaseConfig());
        //建立redis链接
        Cache::connect($this->spider->getRedisConfig());

        $this->dispatch('spider.start', new SpiderStartEvent($this->master, $this));

        //操作队列
        $this->handleQueue();

    }


    //操作队列
    protected function handleQueue()
    {
        //从队列取任务, 如果长时间没有任务，则考虑关闭该进程
        while(1){

            //todo: 队列监听退出机制
            $job = Queue::getUrl(1);

            if($job){
                //解析任务
                $this->resolverJob($job);
            }

            //检查主进程状态
            $this->checkMaster();
        }
    }


    //执行队列任务
    protected function resolverJob($job)
    {
        Log::debug("Requesting: {$job['url']}");
        $time_start = microtime(true);

        try{
            $response = Request::get($job['url']);
        }catch(ClientException $e){
            if($this->needRetry($e)){
                Queue::releaseUrl($job);
            }else{
                Queue::buryUrl($job);
            }
            //更新缓存
            Cache::setUrl($job['url'], -1);
            return false;
        }

        Log::debug("Requested: {$job['url']}");

        //解析网页内容
        $rules = $this->spider->getRules();
        $content = $response->getBody()->getContents();
        //内容转码
        if($this->spider->from_encode !== $this->spider->to_encode){
            $content = @mb_convert_encoding($content, strtoupper($this->spider->to_encode), strtoupper($this->spider->from_encode));
        }

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

            $this->dispatch('spider.response', new SpiderResponseEvent($this->master, $this, $response, $data));

            //$this->spider->onResponse($response, $data);
        }


        //移出队列
        Queue::deleteUrl($job);
        //更新缓存
        Cache::setUrl($job['url'], 1);

        $runtime = microtime(true) - $time_start;
        Log::debug("Job done at " . date('Y-m-d H:i:s') . ", spend time: $runtime");

    }

    /**
     * 是否需要重新抓取
     * @param \Exception $e
     * @return bool
     */
    protected function needRetry(\Exception $e)
    {
        if(in_array($e->getCode(), ['0', '502', '503', '429'])){
            return true;
        }

        return false;
    }

    /**
     * 获取节点值
     * @param $rule
     * @param Crawler $node
     * @return null|string
     */
    protected function getValue($rule,Crawler $node)
    {
        if(strpos($rule, '@') === 0){
            return $node->attr(substr($rule,1));
        }

        return $node->text();
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

    protected function dispatch($event_name, $event)
    {
        $this->master->getDispatcher()->dispatch($event_name, $event);
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

    public function getProcess()
    {
        return $this->process;
    }
}