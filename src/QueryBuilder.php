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

    /**
     * @var Mapper[]
     */
    private array $joins = [];
    /**
     * @var Where[]
     */
    private array $leftJoinsWhere = [];
    private array $ids = [];
    private string $query;
    private string $whereQuery;
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
    private ?I18nService $i18nService;
    private ?Mapper $i18nMapper = null;

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

        $this->i18nService = Manager::instance()->getI18nService();

        if ($this->mapper->getTable()->i18n && $this->i18nService && $this->i18nService->needTranslation()) {
            $i18nModelClass = $this->mapper->getClass() . 'I18n';
            $this->i18nMapper = Mapper::instance($i18nModelClass);
        }
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

    public function join(string $manyToOnePropertyName): QueryBuilder
    {
        $manyToOne = $this->mapper->getForeignKey($manyToOnePropertyName);

        $this->joins[$manyToOnePropertyName] = $manyToOne->modelName;

        $this->getWhere()->addJoinCondition(
            $this->mapper->getClass(),
            $manyToOnePropertyName,
            $manyToOne->modelName,
            'ID'
        );

        return $this;
    }

    public function leftJoin(string $manyToOneProperty): QueryBuilder
    {
        $manyToOne = $this->mapper->getForeignKey($manyToOneProperty);
        $where = new Where($this);
        $where->addJoinCondition($this->mapper->getClass(), 'ID', $manyToOne->modelName, $manyToOneProperty);
        $this->leftJoinsWhere[$manyToOneProperty] = $where;
        return $this;
    }

    public function getLeftJoinWhere($property): Where
    {
        if (!isset($this->leftJoinsWhere[$property])) {
            throw new PropertyDoesNotExistException($property, $this->mapper->getClass());
        }
        return $this->leftJoinsWhere[$property];
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
        $this->orderBy[] = $property . " " . $direction . " ";

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

    private function getColumnQuery(): string
    {
        $columns = $this->mapper->getTableColumnNames();

        if ($this->i18nMapper) {
            $columns = array_merge(array_values($columns), array_values($this->i18nMapper->getTableColumnNames()));
        }

        foreach ($this->joins as $modelName) {
            $columns = array_merge(
                array_values($columns),
                array_values(Mapper::instance($modelName)->getTableColumnNames())
            );
        }

        foreach ($columns as $k=>$column){
            $columns[$k]  = "$column as '$column'";
        }
        
        return implode(', ', $columns);
    }

    private function getTableQuery(): string
    {
        $tables[] = $this->mapper->getTableName();

        foreach ($this->joins as $joinModel) {
            $tables[] = Mapper::instance($joinModel)->getTableName();
        }

        $i18nLeftJoinQuery = '';
        if ($this->i18nMapper) {
            $where = new Where($this);
            $where->addJoinCondition($this->mapper->getClass(), 'ID', $this->i18nMapper->getClass(), 'object_id');
            $where->addCondition($this->i18nMapper->getClass(), 'language', $this->i18nService->getLanguage());
            $i18nLeftJoinQuery = ' LEFT OUTER JOIN ' . $this->i18nMapper->getTableName() . ' ON ' . $where->build();
        }

        $leftJoinsQueries = [];
        foreach ($this->leftJoinsWhere as $property => $where) {
            $manyToOne = $this->mapper->getForeignKey($property);
            $leftJoinsQueries[] = 'LEFT OUTER JOIN ' . Mapper::instance($manyToOne->modelName)->getTableName(
                ) . ' ON ' . $where->build();
        }

        return 'FROM (' . implode(', ', $tables) . ')' . $i18nLeftJoinQuery . ' ' . implode(' ', $leftJoinsQueries);
    }


    private function getWhereQuery(): string
    {
        $where = '';

        if ($this->where !== null) {
            $where .= 'WHERE ' . $this->where->build();
        }

        return $where;
    }

    private function getOrderQuery()
    {
        $orderQuery = '';

        if ($this->orderBy) {
            $orderQuery .= 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        return $orderQuery;
    }

    private function getLimitQuery()
    {
        $query = '';
        if ($this->limit) {
            $query .= $this->limit;
        }
        return $query;
    }

    public function getCount()
    {
        if ($this->count === null) {
            $query = 'SELECT COUNT(*) as number_of_rows ' . $this->getTableQuery() .
                ' ' . $this->getWhereQuery();

            $this->count = Manager::instance()->getAdapter()->fetchValue($query, $this->whereValues);
        }

        return $this->count;
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
                $object = $this->getObject($this->mapper->getClass(), $row);
                $object->initialize();
                $this->ids[] = $object->getId();
                $this->result[] = $object;

                $this->setRelatedObjects($object, $row);
                $this->setI18n($object, $row);
            }
        }

        foreach ($this->result as $object) {
            Manager::instance()->track($object);
        }

        return $this->result;
    }

    protected function getObject(string $model, stdClass $row): BaseModel
    {
        $mapper = Mapper::instance($model);

        /** @var BaseModel $object */
        $object = new $model();

        foreach ($row as $tableColumn => $value) {
            // if result has custom columns ignore them here for now
            if ($mapper->hasTableColumnName($tableColumn)) {
                $property = $mapper->getTableColumnNameProperty($tableColumn);
                $object->set($property, $value);
            }
        }

        return $object;
    }

    private function setRelatedObjects(BaseModel $object, stdClass $row)
    {
        foreach ($this->joins as $property => $modelName) {
            $manyToOne = $this->mapper->getForeignKey($property);
            $relatedObject = $this->getObject($manyToOne->modelName, $row);
            $object->setObjectRelatedBy($property, $relatedObject);
            Manager::instance()->track($relatedObject);
        }
    }

    private function setI18n(BaseModel $object, stdClass $row)
    {
        if ($this->i18nMapper) {
            $i18nObject = $this->getObject($this->i18nMapper->getClass(), $row);
            $object->setI18n($i18nObject);
            Manager::instance()->track($i18nObject);
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
