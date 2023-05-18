<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class NoQueryException
 *
 * @package NiceModules\ORM
 */
class NoQueryException extends ErrorArgsException
{
    protected string $error = 'No query was built. Run ->buildQuery() first.';

}