<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class RequiredAnnotationMissingException
 *
 * @package NiceModules\ORM
 */
class RequiredAnnotationMissingException extends ErrorArgsException
{
    protected string $error = 'The annotation %s does not exist on the model %s.';

    /**
     * @param string $modelClassName
     */
    public function __construct(string $missingAnnotation, string $modelClassName)
    {
        parent::__construct($missingAnnotation, $modelClassName);
    }
}
