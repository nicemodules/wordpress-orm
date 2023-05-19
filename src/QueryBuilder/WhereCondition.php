<?php

namespace NiceModules\ORM\QueryBuilder;

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
     * @param string $compsrsion
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $property,
        $value,
        string $compsrsion = '=',
        string $operator = 'AND'
    ) {
        $this->property = $property;
        $this->value = $value;
        $this->compsrsion = $compsrsion;
        $this->queryBuilder = $queryBuilder;
        $this->operator = $operator;
    }

    /**
     * @return string
     * @throws PropelException
     */
    public function build(): string
    {
        return $this->property . ' ' . $this->compsrsion . ' ' . $this->getValue($this->property, $this->value);
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

}