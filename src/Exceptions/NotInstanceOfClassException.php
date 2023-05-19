<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class NotInstanceOfClassException extends ErrorArgsException
{
    protected string $error = 'Given object is not instance of %s';

    public function __construct(string $className)
    {
        parent::__construct($className);
    }
}