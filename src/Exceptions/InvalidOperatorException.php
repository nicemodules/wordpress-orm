<?php

namespace NiceModules\ORM\Exceptions;

/**
 * Class InvalidOperatorException
 *
 * @package NiceModules\ORM
 */
class InvalidOperatorException extends ErrorArgsException
{
    protected string $error = 'Operator %s is not valid.';

    /**
     * @param string $operator
     */
    public function __construct(string $operator)
    {
        parent::__construct($operator);
    }

}