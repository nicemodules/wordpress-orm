<?php

namespace NiceModules\ORM;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\NoQueryException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Repositories\BaseRepository;

class QueryBuilder
{

    private $where;

    private $order_by;

    private $limit;

    /**
     * The query result.
     * @var array
     */
    private array $result;

    /**
     * Reference to the repository.
     * @var BaseRepository
     */
    private $repository;

    /**
     * QueryBuilder constructor.
     */
    public function __construct(BaseRepository $repository)
    {
        // Set some default values.
        $this->where = [];
        $this->order_by;
        $this->limit;

        // And store the sent repository.
        $this->repository = $repository;
    }

    /**
     * Add a WHERE clause to the query.
     *
     * @param $property
     * @param $value
     * @param $operator
     *
     * @return $this
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     */
    public function where($property, $value, $operator)
    {
        // Check the property exists.
        if (!in_array($property, $this->repository->getObjectProperties()) && $property != 'ID') {
            throw new PropertyDoesNotExistException($property, $this->repository->getObjectClass());
        }

        // Check the operator is valid.
        if (!in_array($operator, [
            '<',
            '<=',
            '=',
            '!=',
            '>',
            '>=',
            'IN',
            'NOT IN'
        ])
        ) {
            throw new InvalidOperatorException($operator);
        }

        // Add the entry.
        $this->where[] = [
            'property' => $property,
            'operator' => $operator,
            'value' => $value,
            'placeholder' => $this->repository->getObjectPropertyPlaceholders()[$property]
        ];

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
        $this->order_by = "ORDER BY " . $property . " " . $operator . "
    ";

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
     * @throws \ReflectionException
     */
    public function buildQuery()
    {
        $values = [];

        $sql = "SELECT * FROM " . Mapper::instance($this->repository->getObjectClass())->getTableName() . " ";

        // Combine the WHERE clauses and add to the SQL statement.
        if (count($this->where)) {
            $sql .= "WHERE
      ";

            $combined_where = [];
            foreach ($this->where as $where) {
                // Operators is not "IN" or "NOT IN"
                if ($where['operator'] != 'IN' && $where['operator'] != 'NOT IN') {
                    $combined_where[] = $where['property'] . " " . $where['operator'] . " " . $where['placeholder'] . "
          ";
                    $values[] = $where['value'];
                } // Operator is "IN" or "NOT IN"
                else {
                    // Fail silently.
                    if (is_array($where['value'])) {
                        $combined_where[] = $where['property'] . " " . $where['operator'] . " (" . implode(
                                ", ",
                                array_pad(
                                    [],
                                    count(
                                        $where['value']
                                    ),
                                    $where['placeholder']
                                )
                            ) . ")
          ";

                        $values = array_merge($values, $where['value']);
                    }
                }
            }

            $sql .= implode(' AND ', $combined_where);  // @todo - should allow more than AND in future.
        }

        // Add the ORDER BY clause.
        if ($this->order_by) {
            $sql .= $this->order_by;
        }

        // Add the LIMIT clause.
        if ($this->limit) {
            $sql .= $this->limit;
        }
        
        $this->result = Manager::instance()->getAdapter()->fetch($sql, $values);
        
        return $this;
    }
    
    
    public function getRawResult(): array
    {
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
    public function getResults($always_array = false)
    {
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
        } else {
            throw new NoQueryException();
        }
    }
}
