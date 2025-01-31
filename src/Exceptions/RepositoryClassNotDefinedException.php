<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class RepositoryClassNotDefined
 *
 * @package NiceModules\ORM
 */
class RepositoryClassNotDefinedException extends ErrorArgsException
{
    protected string $error = 'Repository class %s does not exist on model %s.';

    /**
     * @param string $repositoryClassName
     * @param string $modelClassName
     */
    public function __construct(string $repositoryClassName, string $modelClassName)
    {
        parent::__construct($repositoryClassName, $modelClassName);
    }
}