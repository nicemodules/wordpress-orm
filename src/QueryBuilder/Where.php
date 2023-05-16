<?php

namespace NiceModules\ORM\QueryBuilder;

class Where
{
    /**
     * @var Conditons[]
     */
    protected array $conditions = [];
    /**
     * @var Where[]
     */
    protected array $builders = [];

    /**
     * @param string $column
     * @param numeric|string $value
     * @param string $comparison
     * @param string $operator
     * @return Where
     */
    public function addCondition(string $column, $value, string $comparison = '=', string $operator = 'AND')
    {
        $this->conditions[$operator][] = new Condition($column, $value, $comparison);
        return $this;
    }

    /**
     * @param Where $builder
     * @param string $operator
     * @return Where
     */
    public function addWhere(Where $builder, string $operator = 'AND')
    {
        $this->builders[$operator][] = $builder;
        return $this;
    }

    /**
     * @return string
     * @throws PropelException
     */
    public function build(): string
    {
        $query = '';

        if($this->conditions){
            $query .= $this->buildQueries($this->conditions);
        }

        if($this->builders){
            $query .= $this->buildQueries($this->builders);
        }

        return "($query)";
    }

    /**
     * @param array $conditionsByOperator
     * @return string
     */
    protected function buildQueries(array $conditionsByOperator): string
    {
        $queries = [];
        $whereOperator = '';
        
        foreach ($conditionsByOperator as $operator => $conditions) {
            
            $first = reset($conditions);
            
            if($first instanceof Where && $whereOperator === ''){
                $whereOperator = ' '.$operator. ' ';
            }
            
            $queries[$operator][] = $this->buildConditions($operator, $conditions);
        }
        
        return $whereOperator . $this->buildQuery($queries);
    }

    /**
     * @param array $queries
     * @return string
     */
    protected function buildQuery(array $queries): string
    {
        $query = '';

        if(isset( $queries['AND'])){
            $query = implode(' AND ', $queries['AND']);

            if(isset($queries['OR'])){
                $query .= ' OR ';
            }
        }

        if (isset($queries['OR'])) {
            $query .= implode(' OR ', $queries['OR']);
        }

        return $query;
    }

    /**
     * @param string $operator
     * @param Where[]|Condition[] $conditions
     * @return string
     * @throws PropelException
     */
    protected function buildConditions(string $operator, array $conditions): string
    {
        $queries = [];
        foreach ($conditions as $condition) {
            $queries[] = $condition->build();
        }
        return implode(" $operator ", $queries);

    }
}