<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

class IncompleteIndexException extends ErrorArgsException
{
     protected static string $error = 'Indexes of model %s are incomplete.';

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        parent::__construct($className);
    }
}