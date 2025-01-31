<?php

namespace NiceModules\ORM\Exceptions;

class IncompleteIndexException extends ErrorArgsException
{
    protected string $error = 'Indexes of model %s are incomplete.';

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        parent::__construct($className);
    }
}