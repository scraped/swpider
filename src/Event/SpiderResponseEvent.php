<?php

namespace Swpider\Event;

use Swpider\Swpider;
use Swpider\Worker;
use Symfony\Component\Console\Event\ConsoleEvent;


/**
 * Class SpiderResponseEvent
 * @package Swpider\Event
 */
class SpiderResponseEvent extends ConsoleEvent
{
    private $process;
    private $response;
    private $data;


    public function __construct(Swpider $command, Worker $process, $response, $data)
    {
        parent::__construct($command, $command->getInput(), $command->getOutput());
        $this->process = $process;
        $this->response = $response;
        $this->data = $data;
    }

    public function getProcess()
    {
        return $this->process;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getData()
    {
        return $this->data;
    }

}