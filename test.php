<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swpider\Queue;
use Swpider\Database;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DomCrawler\Crawler;

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


class Process
{
    public $index = 0;
    protected $_worker;


    public function __construct()
    {
        echo 'from parent:';
        $this->showIndex();
        $this->createProcess();
        $this->waitProcess();
        echo 'child exit!'.PHP_EOL;
        //$this->showIndex();
        //$this->pipe();
    }

    public function pipe()
    {
        $data = $this->_worker->read();
        echo 'Pipe read :'.PHP_EOL . $data . PHP_EOL;
        swoole_event_exit();
        //swoole_event_del($this->_worker->pipe);
    }

    public function createProcess()
    {
        $worker = new swoole_process([$this, 'worker'], false, 2);
        $worker->start();

        $this->_worker = $worker;
        swoole_event_add($this->_worker->pipe,[$this, 'pipe']);
    }

    public function worker(swoole_process $work)
    {
        $index = 1;
        while($index < 5){
            $work->write("I'm Children : " . $index++ . PHP_EOL);
            sleep(1);
        }
    }

    public function showIndex()
    {
        echo $this->index . PHP_EOL;
    }

    public function waitProcess()
    {
        $ret = swoole_process::wait();
        //var_dump($ret);
    }
}

new Process();

