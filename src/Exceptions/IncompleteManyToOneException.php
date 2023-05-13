<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

class IncompleteManyToOneException extends ErrorArgsException
{
     protected string $error = 'Many to one of model %s property %s are incomplete.';

    /**
     * @param string $className
     * @param $propertyName
     */
    public function __construct(string $className, $propertyName)
    {
        parent::__construct($className, $propertyName);
    }
}