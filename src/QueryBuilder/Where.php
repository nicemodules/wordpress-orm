<?php

namespace NiceModules\ORM\QueryBuilder;

use NiceModules\ORM\QueryBuilder;

class Where implements Condition
{
    /**
     * @var Condition[]
     */
    protected array $conditions = [];

    protected QueryBuilder $queryBuilder;

    /**
     * @param QueryBuilder $queryBuilder
     */

    protected string $operator;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @param string $property
     * @param numeric|string $value
     * @param string $comparison
     * @param string $operator
     * @return Where
     */
    public function addCondition(string $property, $value, string $comparison = '=', string $operator = 'AND')
    {
        $this->conditions[] = new WhereCondition($this->queryBuilder, $property, $value, $comparison, $operator);
        return $this;
    }

    /**
     * @param Where $builder
     * @param string $operator
     * @return Where
     */
    public function addWhere(Where $builder, string $operator = 'AND')
    {
        $this->conditions[] = $builder;
        $builder->setOperator($operator);

        return $this;
    }

    /**
     * @return string
     * @throws PropelException
     */
    public function build(): string
    {
        $query = '';

        if ($this->conditions) {
            $query .= $this->buildQueries($this->conditions);
        }

        return "($query)";
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @param string $operator
     */
    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    /**
     * @param Condition[] $conditions
     * @return string
     */
    protected function buildQueries(array $conditions): string
    {
        $queries = [];

        foreach ($conditions as $condition) {
            $query = $condition->build();
            if ($queries) {
                $query = $condition->getOperator() . ' ' . $query;
            }

            $queries[] = $query;
        }

        return implode(' ', $queries);
    }


}