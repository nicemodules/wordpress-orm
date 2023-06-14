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
    const ORDER_ASC = 'ASC';
    
    const ORDER_DESC = 'DESC';

    private string $query;

    private string $whereQuery;

    private ?Where $where;

    private array $whereValues = [];

    private array $order_by = [];

    private string $limit;

    private ?int $count = null;

    private ?array $rawResult = null;

    private array $join = [];

    private array $ids = [];

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
        if ($this->where === null) {
            $this->where = new Where($this);
        }

        $this->where->addCondition($property, $value, $comparison, $operator);

        return $this;
    }

    /**
     * Set the ORDER BY clause.
     *
     * @param string $property
     * @param string $direction
     *
     * @return $this
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     */
    public function orderBy(string $property, string $direction = self::ORDER_ASC)
    {
        // Check the property exists.
        if (!in_array($property, $this->repository->getObjectProperties()) && $property != 'ID') {
            throw new PropertyDoesNotExistException($property, $this->repository->getObjectClass());
        }

        // Check the operator is valid.
        if (!in_array($direction, [
            self::ORDER_ASC,
            self::ORDER_DESC,
        ])
        ) {
            throw new InvalidOperatorException($direction);
        }

        // Save it
        $this->order_by[] = $property . " " . $direction . " ";

        return $this;
    }

    /**
     * Set the limit clause.
     *
     * @param int $count
     * @param int $offset
     *
     * @return $this
     */
    public function limit(int $count, int $offset = 0): QueryBuilder
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
        $this->query = "SELECT * FROM " . Mapper::instance($this->repository->getObjectClass())->getTableName() . " ";

        $this->query .= $this->getWhereQuery();

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

    public function getCount()
    {
        if ($this->count === null) {
            $query = "SELECT COUNT(*) as number_of_rows FROM " . Mapper::instance(
                    $this->repository->getObjectClass()
                )->getTableName() . " ";

            $query .= $this->getWhereQuery();

            $this->count = Manager::instance()->getAdapter()->fetchValue($query, $this->whereValues);
        }

        return $this->count;
    }

    /**
     * @param array $join - list of property names to join related objects
     * @return QueryBuilder
     */
    public function setJoin(array $join): QueryBuilder
    {
        $this->join = $join;
        return $this;
    }

    /**
     * @return array
     */
    public function getIds(): array
    {
        return $this->ids;
    }


    private function getWhereQuery()
    {
        if (!isset($this->whereQuery)) {
            // Combine the WHERE clauses and add to the SQL statement.
            $this->whereQuery = '';

            if ($this->where !== null) {
                $this->whereQuery .= 'WHERE ' . $this->where->build() . PHP_EOL;
            }
        }

        return $this->whereQuery;
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
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function getResult(): array
    {
        if(!isset($this->result)){
            $objectClassname = $this->repository->getObjectClass();
            $this->result = [];

            $joinModel = [];
            $joinIds = [];

            foreach ($this->getRawResult() as $row) {
                /** @var BaseModel $object */
                $object = new $objectClassname();

                foreach ($row as $property => $value) {
                    // if query has custom columns ignore them here for now
                    if (property_exists($object, $property)) {
                        $column = Mapper::instance($objectClassname)->getColumn($property);

                        $object->set($property, $value);
         
                        if (in_array($property, $this->join)) {
                            // If property is a ManyToOne, check to see if it's an object and collect id
                            if (isset($column->many_to_one) && $object->get($property)) {
                                $model = $column->many_to_one->modelName;
                                $joinModel[$property] = $model;
                                $joinIds[$model][$object->get('ID')] = $object->get($property);
                            }
                        }
                    }
                }

                $object->initialize();
                $this->ids[] = $object->getId();
                $this->result[] = $object;
            }

            $this->join($joinModel, $joinIds);
            $this->joinI18n();
        }
        
        
        foreach ($this->result as $object){
            Manager::instance()->track($object);
        }
        
        return $this->result;
    }

    /**
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    private function join(array $joinModel, array $joinIds)
    {
        foreach ($joinModel as $property => $model) {
            $relatedObjects = Manager::instance()->getRepository($model)->findIds($joinIds[$model]);
            /** @var BaseModel $object */

            $objects = $this->getResultById();

            foreach ($joinIds[$model] as $objectId => $relatedObjectId) {
                if (isset($objects[$objectId]) && isset($relatedObjects[$relatedObjectId])) {
                    $objects[$objectId]->setObjectRelatedBy($property, $relatedObjects[$relatedObjectId]);
                }
            }
        }
    }

    /**
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    private function joinI18n()
    {
        if(!$this->ids){
            return;
        }
        
        $modelClass = $this->repository->getObjectClass();
        $mapper = Mapper::instance($modelClass);
        $table = $mapper->getTable();
        
        if ($table->i18n) {
            $columns = $mapper->getColumns();

            $i18ns = Manager::instance()
                ->getRepository($modelClass . 'I18n')
                ->createQueryBuilder()
                ->where('object_id', $this->ids, 'IN')
                ->buildQuery()
                ->getResultArray();

            $i18nsByObjectId = [];

            foreach ($i18ns as $i18n) {
                foreach ($i18n as $property => $value) {
                    if (isset($columns[$property]) && $columns[$property]->i18n) {
                        $i18nsByObjectId[$i18n['object_id']][$i18n['language']][$property] = $value;
                    }
                }
            }

            foreach ($this->getResult() as $object) {
                if (isset($i18nsByObjectId[$object->getId()])) {
                    $object->setI18n($i18nsByObjectId[$object->getId()]);
                }
            }
        }
    }

    /**
     * @return BaseModel|null
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
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
     * @return BaseModel[]
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function getResultById(): array
    {
        $result = [];

        foreach ($this->getResult() as $object) {
            $result[$object->get('ID')] = $object;
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

        foreach ($this->getResult() as $object) {
            $result[] = $object->getAllValues();
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

        foreach ($this->getResult() as $object) {
            $result[$object->get('ID')] = $object->getAllValues();
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
