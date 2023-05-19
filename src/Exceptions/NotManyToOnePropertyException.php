<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class NotManyToOnePropertyException extends ErrorArgsException
{
    protected string $error = '%s is not many-to-one property';

    public function __construct(string $propertyName)
    {
        parent::__construct($propertyName);
    }
}