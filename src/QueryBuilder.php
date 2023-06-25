<?php

namespace NiceModules\ORM;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Models\BaseModel;
use NiceModules\ORM\QueryBuilder\Where;
use NiceModules\ORM\Repositories\BaseRepository;
use ReflectionException;
use stdClass;

class QueryBuilder
{
    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    private array $joins = [];
    private array $leftJoins = [];
    /**
     * @var Where[]
     */
    private array $leftJoinWhere = [];
    private array $ids = [];
    private string $query;
    private string $whereQuery = '';
    private string $tableQuery = '';
    private ?Where $where;
    private array $whereValues = [];
    private array $orderBy = [];
    private string $limit;
    private ?int $count = null;
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
    private Mapper $mapper;

    /**
     * QueryBuilder constructor.
     */
    public function __construct(BaseRepository $repository)
    {
        // Set some default values.
        $this->where = null;
        $this->orderBy = [];
        $this->limit = '';

        // And store the sent repository.
        $this->repository = $repository;
        $this->mapper = $this->repository->getMapper();
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
        $this->getWhere()->addCondition($this->mapper->getClass(), $property, $value, $comparison, $operator);

        return $this;
    }

    public function joinObjectRelatedBy(string $manyToOnePropertyName): QueryBuilder
    {
        $manyToOne = $this->mapper->getForeignKey($manyToOnePropertyName);

        $this->join(
            $manyToOnePropertyName,
            $manyToOne->modelName,
            $manyToOne->propertyName
        );

        return $this;
    }

    public function join(
        string $property,
        string $joinModelClass,
        string $joinProperty,
        string $comparison = '=',
        string $operator = 'AND'
    ): QueryBuilder {
        $this->joins[$property][] = $joinModelClass;

        $this->getWhere()->addJoinCondition(
            $this->mapper->getClass(),
            $property,
            $joinModelClass,
            $joinProperty,
            $comparison,
            $operator
        );

        return $this;
    }

    public function leftJoinObjectRelatedBy(string $manyToOnePropertyName): QueryBuilder
    {
        $manyToOne = $this->mapper->getForeignKey($manyToOnePropertyName);

        $this->leftJoin(
            $manyToOnePropertyName,
            $manyToOne->modelName,
            $manyToOne->propertyName
        );

        return $this;
        return $this;
    }

    public function leftJoin(
        string $property,
        string $joinModelClass,
        string $joinProperty,
        string $comparison = '=',
        string $operator = 'AND'
    ): QueryBuilder {
        $this->leftJoins[$property][] = $joinModelClass;
        $where = $this->getLeftJoinWhere($property, $joinModelClass);
        $where->addJoinCondition(
            $this->mapper->getClass(),
            $property,
            $joinModelClass,
            $joinProperty,
            $comparison,
            $operator
        );

        return $this;
    }

    public function getLeftJoinWhere($property, $modelClass): Where
    {
        if (!isset($this->leftJoinWhere[$property][$modelClass])) {
            $this->leftJoinWhere[$property][$modelClass] = new Where($this);
        }

        return $this->leftJoinWhere[$property][$modelClass];
    }

    /**
     * @return Where
     */
    public function getWhere(): Where
    {
        if ($this->where === null) {
            $this->where = new Where($this);
        }

        return $this->where;
    }

    /**
     * Set the ORDER BY clause.
     *
     * @param string $property
     * @param string $direction
     * @param string|null $model
     * @return $this
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     */
    public function orderBy(string $property, string $direction = self::ORDER_ASC, ?string $model = null)
    {
        if ($model === null) {
            $mapper = $this->mapper;
        } else {
            $mapper = Mapper::instance($model);
        }

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
        $this->orderBy[] = $mapper->getTableColumnName($property) . " " . $direction . " ";

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
        $this->query = 'SELECT' .
            ' ' . $this->getColumnQuery() .
            ' ' . $this->getTableQuery() .
            ' ' . $this->getWhereQuery() .
            ' ' . $this->getOrderQuery() .
            ' ' . $this->getLimitQuery();

        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper(): Mapper
    {
        return $this->mapper;
    }

    public function getCount(): int
    {
        if ($this->count === null) {
            $query = 'SELECT COUNT(*) as number_of_rows ' . $this->getTableQuery() .
                ' ' . $this->getWhereQuery();

            $this->count = Manager::instance()->getAdapter()->fetchValue($query, $this->whereValues);
        }

        return (int)$this->count;
    }

    /**
     * @return array
     */
    public function getIds(): array
    {
        return $this->ids;
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
        if (!isset($this->result)) {
            $this->result = [];

            foreach ($this->getRawResult() as $row) {
                $object = $this->getObject($this->mapper->getClass());
                $this->setRelatedObjects($object, $row);
                $this->fillObject($object, $row);

                $this->ids[] = $object->getId();
                $this->result[] = $object;

                $object->initialize();
                Manager::instance()->track($object);
            }
        }

        return $this->result;
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

    protected function getObject(string $model): BaseModel
    {
        return new $model();
    }

    protected function fillObject(BaseModel $object, stdClass $row)
    {
        $mapper = Mapper::instance($object->getClassName());

        foreach ($row as $tableColumn => $value) {
            // if result has custom columns ignore them here for now
            if ($mapper->hasTableColumnName($tableColumn)) {
                $property = $mapper->getTableColumnNameProperty($tableColumn);
                $object->set($property, $value);
            }
        }
    }


    private function getColumnQuery(): string
    {
        $columns = $this->mapper->getTableColumnNames();

        $this->addJoinColumns($columns);
        $this->addLeftJoinColumns($columns);

        foreach ($columns as $k => $column) {
            $columns[$k] = "$column as '$column'";
        }

        return implode(', ', $columns);
    }

    private function addJoinColumns(&$columns)
    {
        foreach ($this->joins as $models) {
            foreach ($models as $model) {
                $columns = array_merge(
                    array_values($columns),
                    array_values(Mapper::instance($model)->getTableColumnNames())
                );
            }
        }
    }

    private function addLeftJoinColumns(&$columns)
    {
        foreach ($this->leftJoins as $models) {
            foreach ($models as $model) {
                $columns = array_merge(
                    array_values($columns),
                    array_values(Mapper::instance($model)->getTableColumnNames())
                );
            }
        }
    }

    private function getTableQuery(): string
    {
        if (empty($this->tableQuery)) {
            $tables[] = $this->mapper->getTableName();
            $this->addJoinTables($tables);
            $this->tableQuery .= 'FROM (' . implode(', ', $tables) . ') ' . implode(' ', $this->getLeftJoinQueries());
        }

        return $this->tableQuery;
    }

    private function getLeftJoinQueries(): array
    {
        $leftJoinsQueries = [];
        foreach ($this->leftJoinWhere as $modelWheres) {
            foreach ($modelWheres as $model => $where) {
                $leftJoinsQueries[] = 'LEFT OUTER JOIN ' . Mapper::instance($model)->getTableName(
                    ) . ' ON ' . $where->build();
            }
        }
        return $leftJoinsQueries;
    }

    private function addJoinTables(array &$tables)
    {
        foreach ($this->joins as $models) {
            foreach ($models as $model) {
                $tables[] = Mapper::instance($model)->getTableName();
            }
        }
    }

    private function getWhereQuery(): string
    {
        if (empty($this->whereQuery)) {
            if ($this->where !== null) {
                $this->whereQuery .= 'WHERE ' . $this->where->build();
            }
        }

        return $this->whereQuery;
    }

    private function getOrderQuery(): string
    {
        $orderQuery = '';

        if ($this->orderBy) {
            $orderQuery .= 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        return $orderQuery;
    }

    private function getLimitQuery(): string
    {
        $query = '';
        if ($this->limit) {
            $query .= $this->limit;
        }
        return $query;
    }

    private function setRelatedObjects(BaseModel $object, stdClass $row)
    {
        foreach ($this->joins as $property => $models) {
            foreach ($models as $model) {
                $relatedObject = $this->getObject($model);
                $this->fillObject($relatedObject, $row);
                $object->setObjectRelatedBy($property, $relatedObject);
                Manager::instance()->track($relatedObject);
            }
        }

        foreach ($this->leftJoins as $property => $models) {
            foreach ($models as $model) {
                $resultIdIndex = Mapper::instance($model)->getTableColumnName('ID');

                if (empty($row->$resultIdIndex)) {
                    continue;
                }

                $relatedObject = $this->getObject($model);
                $this->fillObject($relatedObject, $row);
                $object->setObjectRelatedBy($property, $relatedObject);
                Manager::instance()->track($relatedObject);
            }
        }
    }
}
