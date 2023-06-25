<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class AllowSchemaUpdateIsFalseException
 *
 * @package NiceModules\ORM
 */
class AllowTruncateIsFalseException extends ErrorArgsException
{
    protected string $error = 'Refused to truncate table for model %s. allow_truncate is FALSE.';

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        parent::__construct($className);
    }
}