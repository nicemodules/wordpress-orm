<?php

namespace NiceModules\ORM\Exceptions;

use Exception;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class FailedToUpdateException extends ErrorArgsException
{
    protected static string $error = 'Failed to update one or more records in the database.';
}