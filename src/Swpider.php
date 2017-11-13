<?php

namespace Swpider;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Psr\Log\LogLevel;


class Swpider extends Command
{
    protected $logger;
    protected $verbosityLevelMap;
    protected $job;
    protected $spider;
    protected $input;
    protected $output;


    protected $jobs = [
        'test' => Spider::class,
    ];


    protected function configure()
    {
        $this->setName('run')
            ->setDescription('start a spider job')
            ->addArgument('job', InputArgument::REQUIRED, 'spider job');

        $this->verbosityLevelMap = array(
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO   => OutputInterface::VERBOSITY_NORMAL,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->logger = new ConsoleLogger($output, $this->verbosityLevelMap);

        $this->resolverJob();
    }



    protected function resolverJob()
    {
        $job = $this->input->getArgument('job');

        if(! file_exists($job)){
            $this->logger->error("file $job not found!");
            exit(1);
        }





    }


}
