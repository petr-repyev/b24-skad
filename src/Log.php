<?php

namespace Scad;

class Log  extends \Slim\LogWriter
{
    const EMERGENCY = 1;
    const ALERT     = 2;
    const CRITICAL  = 3;
    const FATAL     = 3; //DEPRECATED @todo: replace with CRITICAL
    const ERROR     = 4;
    const WARN      = 5;
    const NOTICE    = 6;
    const INFO      = 7;
    const DEBUG     = 8;

    protected $levels = array(
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT     => 'ALERT',
        self::CRITICAL  => 'CRITICAL',
        self::ERROR     => 'ERROR',
        self::WARN      => 'WARNING',
        self::NOTICE    => 'NOTICE',
        self::INFO      => 'INFO',
        self::DEBUG     => 'DEBUG'
    );

    public function write($message, $level = null)
    {
    	$message = sprintf("[%s] - %s %s", 
    		date("d.m.Y H:i:s"),
            $this->levels[$level],
    		$message
    	);

        return fwrite($this->resource, (string) $message . PHP_EOL);
    }
}