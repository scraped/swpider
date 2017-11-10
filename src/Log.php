<?php

namespace Swpider;

use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Psr\Log\LogLevel;

/**
 * 日志封装类
 * @package Swpider
 */
class Log
{

    CONST DELETE = "\x7f";
    CONST BACKSPACE = "\x08";
    CONST LINE_HEAD = "\r";

    private static $_logger;

    private static function logger()
    {
        if(is_null(self::$_logger)){
            self::$_logger = new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG));
        }

        return self::$_logger;
    }



    public static function info($string)
    {
        self::logger()->info($string);
    }




}