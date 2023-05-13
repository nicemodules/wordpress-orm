<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class NoQueryException
 *
 * @package NiceModules\ORM
 */
class NoQueryException extends ErrorArgsException
{
    protected string $error = 'No query was built. Run ->buildQuery() first.';
    
}