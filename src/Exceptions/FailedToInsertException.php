<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class FailedToInsertException extends ErrorArgsException
{
    protected string $error = 'Failed to insert one or more records into the database.';
}