<?php

namespace Scad;

use Exception;

class ScadException extends Exception 
{
    public function __construct(string $msg = '', $code = 0, Exception $prev = null) 
    {
        parent::__construct($msg, $code, $prev);
    }
}
