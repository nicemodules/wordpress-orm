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
use Throwable;

class WhereCondition
{
    protected QueryBuilder $queryBuilder;
    protected string $modelClass;
    protected string $property;
    protected $value;
    protected string $comparison;
    protected string $operator;
    
    /**
     * @param QueryBuilder $queryBuilder
     * @param string $modelClass
     * @param string $property
     * @param $value
     * @param string $comparison
     * @param string $operator
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $modelClass,
        string $property,
        $value,
        string $comparison = '=',
        string $operator = 'AND'
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->modelClass = $modelClass;
        $this->property = $property;
        $this->value = $value;
        $this->comparison = $comparison;
        $this->operator = $operator;


        // Check the property exists.
        if (!Mapper::instance($this->modelClass)->hasColumn($property)) {
            throw new PropertyDoesNotExistException($property, $this->modelClass);
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
     * @throws Throwable
     */
    public function build(): string
    {
        $property = Mapper::instance($this->modelClass)->getTableColumnName($this->property);

        if (in_array($this->comparison, ['IN', 'NOT IN'])) {
            return $property . ' ' . $this->comparison . ' ' . $this->getValues($this->property, $this->value);
        } else {
            return $property . ' ' . $this->comparison . ' ' . $this->getValue($this->property, $this->value);
        }
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @param string $property
     * @param $value
     * @return string
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    protected function getValue(string $property, $value): string
    {
        $this->queryBuilder->addWhereValue($value);

        return Mapper::instance($this->modelClass)->getPlaceholder($property);
    }


    /**
     * @param string $property
     * @param array $values
     * @return string
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    protected function getValues(string $property, array $values): string
    {
        $placeholders = [];

        foreach ($values as $value) {
            $this->queryBuilder->addWhereValue($value);
            $placeholders[] = Mapper::instance($this->modelClass)->getPlaceholder($property);
        }

        return '(' . implode(', ', $placeholders) . ')';
    }
}