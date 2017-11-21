<?php

namespace Swpider\Event;

use Swpider\Swpider;
use Symfony\Component\Console\Event\ConsoleEvent;



class SpiderEvent extends ConsoleEvent
{
    public function __construct(Swpider $command)
    {
        parent::__construct($command, $command->getInput(), $command->getOutput());
    }
}