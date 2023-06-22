<?php

namespace NiceModules\ORM\QueryBuilder;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use NiceModules\ORM\Mapper;
use NiceModules\ORM\QueryBuilder;
use ReflectionException;

class WhereJoinCondition implements Condition
{

    protected QueryBuilder $queryBuilder;
    protected string $modelClass;
    protected string $property;
    protected string $joinModelClass;
    protected $joinProperty;
    protected string $comparison;
    protected string $operator;


    /**
     * @param QueryBuilder $queryBuilder
     * @param string $modelClass
     * @param string $property
     * @param string $joinModelClass
     * @param string $joinProperty
     * @param string $comparison
     * @param string $operator
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $modelClass,
        string $property,
        string $joinModelClass,
        string $joinProperty,
        string $comparison = '=',
        string $operator = 'AND'
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->modelClass = $modelClass;
        $this->property = $property;
        $this->joinModelClass = $joinModelClass;
        $this->joinProperty = $joinProperty;
        $this->comparison = $comparison;
        $this->operator = $operator;


        // Check the property exists.
        if (!Mapper::instance($this->modelClass)->hasColumn($property)) {
            throw new PropertyDoesNotExistException($property, $this->modelClass);
        }

        // Check the property exists.
        if (!Mapper::instance($this->joinModelClass)->hasColumn($this->joinProperty)) {
            throw new PropertyDoesNotExistException($this->joinProperty, $this->modelClass);
        }

        // Check the comparison is valid.
        if (!in_array($this->comparison, [
            '<',
            '<=',
            '=',
            '!=',
            '>',
            '>=',
            'IN',
            'NOT IN',
            'LIKE',
            'NOT LIKE',
            'IS NULL',
            'NOT NULL',
        ])
        ) {
            throw new InvalidOperatorException($this->comparison);
        }

        // Check the comparison is valid.
        if (!in_array($operator, [
            'AND',
            'OR'
        ])
        ) {
            throw new InvalidOperatorException($this->operator);
        }
    }

    /**
     * @return string
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function build(): string
    {
        $property = Mapper::instance($this->modelClass)->getTableColumnName($this->property);
        $joinProperty = Mapper::instance($this->joinModelClass)->getTableColumnName($this->joinProperty);

        return $property . ' ' . $this->comparison . ' ' . $joinProperty;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }
}