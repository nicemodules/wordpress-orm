<?php

namespace NiceModules\ORM\QueryBuilder;

use http\Encoding\Stream\Inflate;
use NiceModules\ORM\QueryBuilder;
use Throwable;

class Where implements Condition
{
    /**
     * @var Condition[]
     */
    protected array $conditions = [];

    protected QueryBuilder $queryBuilder;

    protected string $operator;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @param string $modelClass
     * @param string $property
     * @param numeric|string $value
     * @param string $comparison
     * @param string $operator
     * @return Where
     * @throws Throwable
     */
    public function addCondition(string $modelClass, string $property, $value, string $comparison = '=', string $operator = 'AND')
    {
        $this->conditions[] = new WhereCondition($this->queryBuilder, $modelClass, $property, $value, $comparison, $operator);
        return $this;
    }

    /**
     * @param string $modelClass
     * @param string $property
     * @param string $joinModelClass
     * @param string $joinProperty
     * @param string $comparison
     * @param string $operator
     * @return $this
     * @throws Throwable
     */
    public function addJoinCondition(string $modelClass, string $property, string $joinModelClass, string $joinProperty, string $comparison = '=', string $operator = 'AND')
    {
        $this->conditions[] = new WhereJoinCondition($this->queryBuilder, $modelClass, $property, $joinModelClass, $joinProperty, $comparison, $operator);
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