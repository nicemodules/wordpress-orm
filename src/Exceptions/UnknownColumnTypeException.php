<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class UnknownColumnTypeException
 *
 * @package NiceModules\ORM
 */
class UnknownColumnTypeException extends ErrorArgsException
{
    protected static string $error = 'Property %s does not exist in model %s.';

    /**
     * @param string $columnType
     * @param string $modelClassName
     */
    public function __construct(string $columnType, string $modelClassName)
    {
        parent::__construct($columnType, $modelClassName);
    }
}
