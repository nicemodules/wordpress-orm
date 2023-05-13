<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class PropertyDoesNotExistException
 *
 * @package NiceModules\ORM
 */
class PropertyDoesNotExistException extends ErrorArgsException
{
    protected static string $error = 'Property %s does not exist in model %s.';

    /**
     * @param string $propertyName
     * @param string $modelClassName
     */
    public function __construct(string $propertyName, string $modelClassName)
    {
        parent::__construct($propertyName, $modelClassName);
    }
}