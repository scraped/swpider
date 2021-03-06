<?php

namespace Swpider;

use Illuminate\Support\Arr;
use Swpider\Event\SpiderEvent;
use Swpider\Event\SpiderResponseEvent;
use Swpider\Event\SpiderStartEvent;
use Symfony\Component\DomCrawler\Crawler;


class Worker
{
    const MAX_WAIT = 10;
    const MAX_EXCEPTION = 10;

    const S_READY = 'ready';
    const S_WAIT = 'wait';
    const S_DONE = 'done';
    const S_SLEEP = 'sleep';
    const S_EXCEPT = 'except';
    const S_REQUEST = 'request';
    const S_QUIT = 'quit';

    const SLEEP = 200;

    private $master;
    private $process;
    private $spider;
    private $exceptions = 0;
    private $running = true;
    private $time;
    private $stat;
    private $statistics = [
        'request' => 0,
        'success' => 0,
        'fail' => 0,
    ];

    public function __construct(Swpider $master)
    {
        $this->master = $master;
        $this->spider = $master->getSpider();
        $this->spider->setWorker($this);
    }


    //爬虫进程逻辑
    public function start(\swoole_process $worker)
    {
        $this->process = $worker;
        $this->time = time();

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
        //设置请求客户端
        Request::init([
            'cookies' => $this->spider->getCookies(),
        ]);

        $this->dispatch('spider.start', new SpiderStartEvent($this->master, $this));

        $this->setStat(self::S_READY,[
            'pid' => $this->getPid(),
            'started_at' => $this->time,
        ]);



        //操作队列
        $this->handleQueue();

    }


    //操作队列
    protected function handleQueue()
    {
        //从队列取任务, 如果长时间没有任务，则考虑关闭该进程
        while(1){

            if($this->master->getInput()->getOption('test')){
                Log::debug("Test Mode");
                $this->spider->testJob();
                break;
            }

            $this->setStat(self::S_WAIT);

            //todo: 队列监听退出机制
            $job = Queue::getUrl(1);

            if($job){
                //解析任务
                $this->resolverJob($job);
            }

            //记录当前进程的状态
            $this->setStat(null, $this->checkProcessStatus());

            //检查主进程状态
            $this->checkMaster();


            //是否单次模式
            if($this->master->getInput()->getOption('single') || ! $this->running ){
                Cache::delWorker($this->getPid());
                break;
            }
            //不要太快，休息，休息一下
            usleep(self::SLEEP);
        }
    }


    public function testJob($job, callable $callback = null)
    {
        $data = [
            'type' => $job['type']
        ];

        if(preg_match('#^https?\:\/\/#', $job['url'])){
            $url = $job['url'];
        }else{
            $url = rtrim($this->spider->domain, ' /') . '/' . ltrim($job['url'], ' /');
        }

        try{
            Log::debug("Requesting: {$job['url']}");

            $time_start = microtime(true);
            $response = Request::get($url);

            Log::debug("Requested: {$url}");
        }catch(\Exception $e){
            $data['stat'] = 'requested failed';
            return $data;
        }


        //解析网页内容
        $rules = $this->spider->getRules();
        $content = $response->getBody()->getContents();
        $data['response'] = $response;
        $data['response_body'] = $content;


        //内容转码
        if($this->spider->from_encode !== $this->spider->to_encode){
            $content = @mb_convert_encoding($content, strtoupper($this->spider->to_encode), strtoupper($this->spider->from_encode));
        }

        foreach($rules['url'] as $name=>$option){
            $regex = $option['regex'];
            //解析可用链接
            if(preg_match_all("#{$regex}#iu", $content, $matches)){
                $data['urls'] = $matches[0];
            }
        }

        $fields = Arr::get($rules,'url.'.$job['type'].'.fields', false);

        //解析内容字段
        if(!empty($fields)){
            Log::debug("resolver response content ...");


            $crawler = new Crawler($content);
            foreach($fields as $field){
                Log::debug("resolver field $field ...");

                $rule = $rules['fields'][$field];

                $re = $crawler->filter($rule['selector']);

                Log::debug("field mapped: " . $re->count());

                $value = null;
                if($re->count() > 0){
                    if(Arr::get($rule, 'multi', false)){
                        $value = [];
                        $re->each(function($node) use ($rule, &$value){
                            $value[] = isset($rule['group']) ? $this->resolverGroup($node, $rule['group']) : $this->getValue($node, $rule);
                        });
                    }else{
                        $value = isset($rule['group']) ? $this->resolverGroup($re, $rule['group']) : $this->getValue($re, $rule);
                    }
                }
                $data['data'][$field] = $value;
            }

            Log::debug("resolver response done!");
        }

        $runtime = microtime(true) - $time_start;
        Log::debug("Job done at " . date('Y-m-d H:i:s') . ", spend time: $runtime");

        $data['time'] = $runtime;

        call_user_func($callback, $data);
    }


    //执行队列任务
    protected function resolverJob($job)
    {

        if(preg_match('#^https?\:\/\/#', $job['url'])){
            $url = $job['url'];
        }else{
            $url = rtrim($this->spider->domain, ' /') . '/' . ltrim($job['url'], ' /');
        }

        $this->statistics['request'] ++ ;
        $this->setStat(self::S_REQUEST, [
            'url' => $url,
        ]);

        try{
            Log::debug("Requesting: {$job['url']}");

            $time_start = microtime(true);
            $response = Request::get($url);

            Log::debug("Requested: {$url}");
        }catch(\Exception $e){
            if($this->needRetry($e)){
                Queue::releaseUrl($job);
            }else{
                Queue::buryUrl($job);
            }
            //更新缓存
            Cache::setUrl($job['url'], Cache::URL_ERROR);
            $this->statistics['fail'] ++ ;
            return false;
        }



        //解析网页内容
        $rules = $this->spider->getRules();
        $content = $response->getBody()->getContents();
        $is_successful = true;


        //验证返回内容是否合格
        switch($stat = $this->spider->verifyResponse($response, $content)){
            case Spider::RES_LOGIN :
                $this->dispatch('spider.login', new SpiderEvent($this->master, $this));
                return false;
        }


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
                        continue;
                    }
                    Log::debug("put url: $url");
                    //加入队列
                    Queue::addUrl($url, $name, Arr::get($option, 'priority', 100));
                    //写入缓存
                    Cache::setUrl($url,Cache::URL_READY);
                }
            }
        }


        $fields = Arr::get($rules,'url.'.$job['type'].'.fields', false);

        //解析内容字段
        if($job['type'] !== 'index' && !empty($fields)){
            Log::debug("resolver response content ...");

            $data = [
                'type' => $job['type']
            ];
            $crawler = new Crawler($content);
            foreach($fields as $field){
                Log::debug("resolver field $field ...");

                $rule = $rules['fields'][$field];

                $re = $crawler->filter($rule['selector']);

                Log::debug("field mapped: " . $re->count());

                $value = null;
                if($re->count() > 0){
                    if(Arr::get($rule, 'multi', false)){
                        $value = [];
                        $re->each(function($node) use ($rule, &$value){
                            $value[] = isset($rule['group']) ? $this->resolverGroup($node, $rule['group']) : $this->getValue($node, $rule);
                        });
                    }else{
                        $value = isset($rule['group']) ? $this->resolverGroup($re, $rule['group']) : $this->getValue($re, $rule);
                    }
                }
                $data['data'][$field] = $value;
            }

            //需要验证采集数据，对异常页面进行采样供分析
            if(! $this->validateValue($rules, $fields, $data)){

                $is_successful = false;

                $this->logResponseException($url, $response, $content, $data);

                if(++$this->exceptions > self::MAX_EXCEPTION){
                    $this->running = false;
                }
            }


            $this->dispatch('spider.response', new SpiderResponseEvent($this->master, $this, $response, $data));

            Log::debug("resolver response done!");
        }

        if ($is_successful){
            //移出队列
            Queue::deleteUrl($job);
            //更新缓存
            Cache::setUrl($job['url'], Cache::URL_LOADED);
            $this->statistics['success'] ++ ;
        }else{
            Queue::releaseUrl($job);
            Cache::setUrl($job['url'], Cache::URL_EXCEPT);
            $this->statistics['fail'] ++ ;
        }

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
     *
     * @param Crawler $node
     * @param $rule
     * @return null|string
     */
    protected function getValue(Crawler $node, $rule)
    {
        $getter = $rule['getter'];

        if($node->count() === 0){
            return null;
        }

        if(strpos($getter, '@') === 0){
            return $node->attr(substr($getter,1));
        }

        return $node->text();
    }

    /**
     * 验证采集数据准确性
     * @param $rules
     * @param $fields
     * @param $data
     * @return bool
     */
    protected function validateValue($rules, $fields, $data)
    {
        foreach($fields as $field){
            $rule = $rules['fields'][$field];
            $value = Arr::get($data['data'], $field);

            if(Arr::get($rule, 'optional', false)){
                continue;
            }

            foreach(Arr::get($rule, 'group', []) as $key => $item){
                if(Arr::get($item, 'optional', false) ){
                    continue;
                }

                if(Arr::get($rule, 'multi', false) && !empty($value)){
                    foreach($value as $sub){
                        if(! isset($sub[$key])){
                            return false;
                        }
                    }
                }else{
                    if(! isset($value[$key])){
                        return false;
                    }
                }
            }

            if(empty($value)){
                return false;
            }
        }

        return true;
    }

    /**
     * 获取分组信息
     * @param Crawler $node
     * @param $group
     * @return array
     */
    protected function resolverGroup(Crawler $node, $group)
    {
        $data = [];
        foreach($group as $field => $rule){
            $new_node = $node;
            if(isset($rule['selector'])){
                $new_node = $node->filter($rule['selector']);
            }

            $data[$field] = $this->getValue($new_node, $rule);
        }

        return $data;
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



    protected  function logResponseException($url, $response, $body, $data)
    {
        $request = Request::client();
        $filename = rtrim($this->spider->log_path, ' /') . '/'
            . $this->spider->name . '_'
            . date('Y_m_d_H_i_s_')
            . md5($url)
            . '.log';

        $content[] = 'Request' . PHP_EOL . "=============================";
        $content[] = "url: $url";
        $content[] = json_encode($request->getConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $content[] = 'Date' . PHP_EOL . "=============================";
        $content[] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $content[] = "Response" . PHP_EOL . "=============================";
        $content[] = json_encode($response->getHeaders(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $content[] = $body;

        @file_put_contents($filename, implode("\r\n", $content));
    }


    protected function setStat($stat = null, $options = [])
    {
        if(isset($stat)){
            $this->stat = $stat;
            $options = array_merge($options, [
                'stat' => $stat
            ]);
        }

        Cache::setWorker($this->getPid(),$options);
    }

    protected function checkProcessStatus()
    {
        $usage = getrusage();
        $mem = memory_get_usage();

        return [
            'statistics' => $this->statistics,
            'usage' => $usage,
            'memory' => $mem,
        ];
    }


    public function getProcess()
    {
        return $this->process;
    }


    public function getPid()
    {
        return $this->process->pid;
    }
}