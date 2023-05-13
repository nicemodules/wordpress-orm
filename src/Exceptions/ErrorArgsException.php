<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Allows creating child exception classes with predefined messages.
 */
class ErrorArgsException extends Exception
{
    protected static string $error = 'Unknown exception';
    
    protected array $args = [];

    public function __construct(...$messageArgs)
    {
        parent::__construct(self::getErrorMessage($messageArgs));
    }

    /**
     * @param ...$messageArgs
     * @return string
     */
    public static function getErrorMessage(...$messageArgs): string
    {
        return vsprintf(self::$error, $messageArgs);
    }
}