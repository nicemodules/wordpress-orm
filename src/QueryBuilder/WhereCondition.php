<?php

namespace NiceModules\ORM\QueryBuilder;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\QueryBuilder;

class WhereCondition
{
    protected string $property;
    protected $value;
    protected string $compsrsion;
    protected string $operator;
    protected QueryBuilder $queryBuilder;

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $property
     * @param $value
     * @param string $comparison
     * @param string $operator
     * @throws InvalidOperatorException
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $property,
        $value,
        string $comparison = '=',
        string $operator = 'AND'
    ) {
        $this->property = $property;
        $this->value = $value;
        $this->compsrsion = $comparison;
        $this->queryBuilder = $queryBuilder;
        $this->operator = $operator;
        
        // Check the property exists.
        if (!in_array($property, $this->queryBuilder->getRepository()->getObjectProperties())) {
            throw new PropertyDoesNotExistException($property, $this->queryBuilder->getRepository()->getObjectClass());
        }
        
        // Check the comparison is valid.
        if (!in_array($this->compsrsion, [
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
            throw new InvalidOperatorException($this->compsrsion);
        }

        // Check the comparison is valid.
        if (!in_array($operator, [
           'AND', 'OR'
        ])
        ) {
            throw new InvalidOperatorException($this->operator);
        }
    }

    /**
     * @return string
     * @throws PropelException
     */
    public function build(): string
    {
        if (in_array($this->compsrsion, ['IN', 'NOT IN'])) {
            return $this->property . ' ' . $this->compsrsion . ' ' . $this->getValues($this->property, $this->value);
        } else {
            return $this->property . ' ' . $this->compsrsion . ' ' . $this->getValue($this->property, $this->value);
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
     */
    protected function getValue(string $property, $value): string
    {
        $this->queryBuilder->addWhereValue($value);
        
        return $this->queryBuilder->getRepository()->getMapper()->getPlaceholder($property);
    }


    /**
     * @param string $property
     * @param array $values
     * @return string
     */
    protected function getValues(string $property, array $values): string
    {
        $placeholders = [];
        
        foreach($values as $value){
            $this->queryBuilder->addWhereValue($value);
            $placeholders[] = $this->queryBuilder->getRepository()->getMapper()->getPlaceholder($property);
        }
        
        return '('.implode(', ', $placeholders).')';
    }
}