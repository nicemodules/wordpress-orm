<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class InvalidOperatorException
 *
 * @package NiceModules\ORM
 */
class InvalidOperatorException extends ErrorArgsException
{
    protected static string $error = 'Operator %s is not valid.';

    /**
     * @param string $operator
     */
    public function __construct(string $operator)
    {
        parent::__construct($operator);
    }
    
}