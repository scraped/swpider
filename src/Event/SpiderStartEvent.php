<?php

namespace Swpider\Event;

use Swpider\Swpider;
use Swpider\Worker;
use Symfony\Component\Console\Event\ConsoleEvent;


/**
 * Class SpiderStartEvent
 * @package Swpider\Event
 */
class SpiderStartEvent extends ConsoleEvent
{
    private $process;

    /**
     * SpiderStartEvent constructor.
     * @param Swpider $command
     * @param Worker $process
     */
    public function __construct(Swpider $command, Worker $process)
    {
        parent::__construct($command, $command->getInput(), $command->getOutput());
        $this->process = $process;
    }

    public function getProcess()
    {
        return $this->process;
    }

}