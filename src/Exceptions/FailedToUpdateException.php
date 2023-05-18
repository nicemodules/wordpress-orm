<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class FailedToUpdateException extends ErrorArgsException
{
    protected string $error = 'Failed to update one or more records in the database.';
}