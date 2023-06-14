<?php

namespace Mink\WebdriverClassDriver;

use Exception;
use Throwable;

class ErrorException extends Exception
{
    public function __construct(Throwable $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
    }
}
