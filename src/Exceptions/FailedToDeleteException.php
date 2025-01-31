<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class FailedToInsertException
 *
 * @package NiceModules\ORM
 */
class FailedToDeleteException extends ErrorArgsException
{
    protected string $error = 'Failed to delete one or more records in the database.';
}