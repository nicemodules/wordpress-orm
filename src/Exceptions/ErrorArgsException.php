<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Allows creating child exception classes with predefined messages.
 */
class ErrorArgsException extends Exception
{
    protected string $error = 'Unknown exception';
    

    public function __construct(...$messageArgs)
    {
        parent::__construct(call_user_func_array([$this, 'getErrorMessage'], $messageArgs));
    }

    /**
     * @param ...$messageArgs
     * @return string
     */
    public function getErrorMessage(...$messageArgs): string
    {
        return vsprintf($this->error, $messageArgs);
    }
}