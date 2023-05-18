<?php

namespace NiceModules\ORM;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\NoQueryException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
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

    /**
     * The query result.
     * @var array
     */
    private array $result;

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
     * Build the query and process through $wpdb->prepare().
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
        $this->result = Manager::instance()->getAdapter()->fetch($this->query, $this->whereValues);

        return $this->result;
    }

    /**
     * Run the query returning either a single object or an array of objects.
     *
     * @param bool $always_array
     *
     * @return array|bool|mixed
     * @throws NoQueryException
     */
    public function getResult($always_array = false)
    {
        $this->result = Manager::instance()->getAdapter()->fetch($this->query, $this->whereValues);

        if ($this->result) {
            // Classname for this repository.
            $object_classname = $this->repository->getObjectClass();

            // Loop through the database results, building the objects.
            $objects = array_map(function ($result) use (&$object_classname) {
                // Create a new blank object.
                $object = new $object_classname();

                // Fill in all the properties.
                array_walk($result, function ($value, $property) use (&$object) {
                    $object->set($property, $value);
                });

                // Track the object.
                $em = Manager::instance();
                $em->track($object);

                // Save it.
                return $object;
            }, $this->result);

            // There were no results.
            if (!count($objects)) {
                return false;
            }

            // Return just an object if there was only one result.
            if (count($objects) == 1 && !$always_array) {
                return $objects[0];
            }

            // Otherwise, the return an array of objects.
            return $objects;
        }

        return [];
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
