<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swpider\Queue;
use Swpider\Database;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DomCrawler\Crawler;
use Swpider\Request;


Request::send('get','http://dev.luoo.net',[],function($err, $client){
    var_dump($client->body);
});

//$html = <<<'HTML'
//<!DOCTYPE html>
//<html>
//    <body>
//        <p class="message">Hello World!</p>
//        <p>Hello Crawler!</p>
//    </body>
//</html>
//HTML;
//
//$crawler = new Crawler($html);
//
//
//
//$css = new CssSelectorConverter();
//$xpath = $css->toXPath("p.message+p");
//
//
//$re = $crawler->filter('p.message+p');
//$re = $crawler->filterXPath("//p[@class='message']//@class");
//
////var_dump($re->text());
//
//
//Queue::connect();
//$re = Queue::putInTube('test','test');


//var_dump($re);


class DB
{
    public static $name = 'db';
}



class Process
{
    public $index = 0;
    protected $_worker = [];


    public function __construct()
    {
        echo 'from parent:';
        $this->showIndex();
        $this->createProcess();
        //$this->out();
        $this->waitProcess();
        echo 'child exit!'.PHP_EOL;
        //$this->showIndex();
    }

    public function out($worker = null)
    {
        while($data = $this->_worker[0]->read()){
            echo 'Pipe read :'.PHP_EOL . $data . PHP_EOL;
        }

        //swoole_event_exit();
        //swoole_event_del($this->_worker->pipe);
    }

    public function createProcess()
    {
        for($i = 0 ; $i < 5; $i++){
            $worker = new swoole_process([$this, 'worker'], false, 1);
            $this->_worker[] = $i;
            $worker->start();
        }

        foreach($this->_worker as $p){
            //swoole_event_add($p->pipe,[$this, 'out']);
        }

    }

    public function worker(swoole_process $work)
    {
        unset($this->_worker);
        DB::$name = 'child';
        echo "worker $work->pid" .PHP_EOL;
        //var_dump($this->_worker);
        var_dump(DB::$name);
    }

    public function showIndex()
    {
        echo $this->index . PHP_EOL;
    }

    public function waitProcess()
    {
        while($ret = swoole_process::wait()){
            echo "worker ".$ret['pid']." exit".PHP_EOL;
        }
        //var_dump($this->_worker);
        var_dump(DB::$name);
    }
}

//new Process();

