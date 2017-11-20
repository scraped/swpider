<?php

namespace Swpider;


class Worker
{
    private $master;
    private $process;
    private $spider;

    public function __construct($master)
    {
        $this->master = $master;
        $this->spider = $master->spider;
    }


    //爬虫进程逻辑
    public function start(\swoole_process $worker)
    {
        $this->process = $worker;
        $this->spider->onStart();
        //清空子进程的进程数组
        unset($this->workers);

        //进程命名
        swoole_set_process_name(sprintf('spider pool:%s', $this->spider->name));

        //建立新的连接，避免多进程间相互抢占主进程的连接
        Queue::connect($this->spider->getQueueConfig());
        //建立数据库连接
        Database::connect($this->spider->getDatabaseConfig());
        //建立redis链接
        Cache::connect($this->spider->getRedisConfig());


        //操作队列
        $this->handleQueue();

    }

    //操作队列
    protected function handleQueue()
    {
        //从队列取任务, 如果长时间没有任务，则考虑关闭该进程
        while(1){

            $job = Queue::getUrl();

            if(! $job){

                if($this->job_wait < self::MAX_WAIT){
                    $this->job_wait++;
                    usleep(100);
                    continue;
                }else{
                    //超过重试次数，退出队列监听
                    break;
                }
            }

            $this->job_wait = 0;

            //解析任务
            $this->resolverJob($job);

            //检查主进程状态
            $this->checkMaster();
        }
    }


    //执行队列任务
    protected function resolverJob($job)
    {
        Log::debug("Requesting: {$job['url']}");

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

        Log::debug("Requested: {$job['url']}");


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
}