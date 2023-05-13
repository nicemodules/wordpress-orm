<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class AllowSchemaUpdateIsFalseException
 *
 * @package NiceModules\ORM
 */
class AllowSchemaUpdateIsFalseException extends ErrorArgsException
{
    protected string $error = 'Refused to drop table for model %s. allow_schema_update is FALSE.';

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        parent::__construct($className);
    }
}