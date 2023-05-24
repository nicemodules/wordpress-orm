<?php

namespace NiceModules\ORM;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Models\BaseModel;
use NiceModules\ORM\QueryBuilder\Where;
use NiceModules\ORM\Repositories\BaseRepository;
use ReflectionException;

class QueryBuilder
{

    private string $query;

    private ?Where $where;

    private array $whereValues = [];

    private array $order_by = [];

    private string $limit;

    private ?array $rawResult = null;

    /**
     * The query result.
     * @var BaseModel[]|null
     */
    private ?array $result = null;

    /**
     * Reference to the repository.
     * @var BaseRepository
     */
    private BaseRepository $repository;

    /**
     * QueryBuilder constructor.
     */
    public function __construct(BaseRepository $repository)
    {
        // Set some default values.
        $this->where = null;
        $this->order_by = [];
        $this->limit = '';

        // And store the sent repository.
        $this->repository = $repository;
    }

    /**
     * Add a WHERE clause to the query.
     *
     * @param string $property
     * @param $value
     * @param string $comparison
     * @param string $operator
     *
     * @return $this
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     */
    public function where(string $property, $value, string $comparison = '=', string $operator = 'AND')
    {
        // Check the property exists.
        if (!in_array($property, $this->repository->getObjectProperties())) {
            throw new PropertyDoesNotExistException($property, $this->repository->getObjectClass());
        }

        // Check the operator is valid.
        if (!in_array($comparison, [
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
            throw new InvalidOperatorException($comparison);
        }

        if ($this->where === null) {
            $this->where = new Where($this);
        }

        $this->where->addCondition($property, $value, $comparison, $operator);

        return $this;
    }

    /**
     * Set the ORDER BY clause.
     *
     * @param $property
     * @param $operator
     *
     * @return $this
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     */
    public function orderBy($property, $operator)
    {
        // Check the property exists.
        if (!in_array($property, $this->repository->getObjectProperties()) && $property != 'ID') {
            throw new PropertyDoesNotExistException($property, $this->repository->getObjectClass());
        }

        // Check the operator is valid.
        if (!in_array($operator, [
            'ASC',
            'DESC',
        ])
        ) {
            throw new InvalidOperatorException($operator);
        }

        // Save it
        $this->order_by[] = $property . " " . $operator . " ";

        return $this;
    }

    /**
     * Set the limit clause.
     *
     * @param $count
     * @param int $offset
     *
     * @return $this
     */
    public function limit($count, $offset = 0)
    {
        // Ignore if not valid.
        if (is_numeric($offset) && is_numeric($count) && $offset >= 0 && $count > 0) {
            $this->limit = "LIMIT " . $count . " OFFSET " . $offset . "
      ";
        }

        return $this;
    }

    /**
     * Build the query and process using adapter prepare.
     * @return $this
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function buildQuery()
    {
        $values = [];

        $this->query = "SELECT * FROM " . Mapper::instance($this->repository->getObjectClass())->getTableName() . " ";

        // Combine the WHERE clauses and add to the SQL statement.
        if ($this->where !== null) {
            $this->query .= 'WHERE ' . $this->where->build() . PHP_EOL;
        }

        // Add the ORDER BY clause.
        if ($this->order_by) {
            $this->query .= "ORDER BY " . implode(', ', $this->order_by);
        }

        // Add the LIMIT clause.
        if ($this->limit) {
            $this->query .= $this->limit;
        }

        return $this;
    }


    public function getRawResult(): array
    {
        if ($this->rawResult === null) {
            if ($result = Manager::instance()->getAdapter()->fetch($this->query, $this->whereValues)) {
                $this->rawResult = $result;
            } else {
                $this->rawResult = [];
            }
        }

        return $this->rawResult;
    }

    /**
     * Get an array of model objects.
     *
     * @return BaseModel[]
     */
    public function getResult(): array
    {
        $objectClassname = $this->repository->getObjectClass();
        $this->result = [];

        foreach ($this->getRawResult() as $row) {
            $object = new $objectClassname();

            array_walk($row, function ($value, $property) use (&$object) {
                // if query has custom columns ignore them
                if (property_exists($object, $property)) {
                    $object->set($property, $value);
                }
            });

            $object->initialize();
            Manager::instance()->track($object);
            $this->result[] = $object;
        }

        return $this->result;
    }

    /**
     * @return BaseModel|null
     */
    public function getSingleResult(): ?BaseModel
    {
        $result = $this->getResult();

        if (isset($result[0])) {
            return $result[0];
        }

        return null;
    }

    /**
     * @return BaseModel|null
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function getResultById(): ?BaseModel
    {
        $result = [];

        foreach ($this->getResult() as $object){
            $result[$object->get('ID')] = $object ;
        }

        return $result;
    }


    /**
     * @return array
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function getResultArray(): array
    {
        $result = [];

        foreach ($this->getResult() as $object){
            $result[] = $object->getAllValues() ;
        }

        return $result;
    }

    /**
     * @return array
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function getResultArrayById(): array
    {
        $result = [];

        foreach ($this->getResult() as $object){
            $result[$object->get('ID')] = $object->getAllValues() ;
        }

        return $result;
    }
    

    /**
     * @return Where|null
     */
    public function getWhere(): ?Where
    {
        if ($this->where == null) {
            $this->where = new Where($this);
        }

        return $this->where;
    }

    /**
     * @return BaseRepository
     */
    public function getRepository(): BaseRepository
    {
        return $this->repository;
    }

    public function addWhereValue($value)
    {
        $this->whereValues[] = $value;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function getWhereValues(): array
    {
        return $this->whereValues;
    }
}
