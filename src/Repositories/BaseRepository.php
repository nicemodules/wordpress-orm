<?php

namespace NiceModules\ORM\Repositories;

use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use NiceModules\ORM\Mapper;
use NiceModules\ORM\Models\BaseModel;
use NiceModules\ORM\QueryBuilder;
use ReflectionException;

class BaseRepository
{

    private string $classname;

    private Mapper $mapper;

    /**
     * BaseRepository constructor.
     *
     * @param string $classname
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function __construct(string $classname)
    {
        $this->classname = $classname;
        $this->mapper = Mapper::instance($classname);
    }

    /**
     * @param string $classname
     * @return BaseRepository
     */
    public static function getInstance(string $classname)
    {
        // Get the class (as this could be a child of BaseRepository)
        $this_repository_class = get_called_class();

        // Return a new instance of the class.
        return new $this_repository_class($classname);
    }

    /**
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * Getter used in the query builder.
     * @return mixed
     */
    public function getObjectClass()
    {
        return $this->classname;
    }

    /**
     * Getter used in the query builder
     * @return mixed
     */
    public function getDBTable()
    {
        return $this->mapper->getTableName();
    }

    /**
     * Getter used in the query builder.
     * @return array
     */
    public function getObjectProperties()
    {
        return $this->mapper->getColumnNames();
    }


    /**
     * Find a single object by ID.
     *
     * @param $id
     *
     * @return null|BaseModel
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function find($id): ?BaseModel
    {
        return $this->createQueryBuilder()
            ->where('ID', $id, '=')
            ->buildQuery()
            ->getSingleResult();
    }

    /**
     * Return all objects of this type.
     *
     * @return array
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function findAll()
    {
        return $this->createQueryBuilder()
            ->orderBy('ID', 'ASC')
            ->buildQuery()
            ->getResult();
    }

    public function findIds(array $ids)
    {
        return $this->createQueryBuilder()
            ->where('ID', $ids, 'IN')
            ->orderBy('ID', 'ASC')
            ->buildQuery()
            ->getResultById();
    }

    /**
     * Returns all objects with matching property value.
     *
     * @param array $criteria
     *
     * @return array
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function findBy(array $criteria): array
    {
        $queryBuilder = $this->createQueryBuilder();
        
        foreach ($criteria as $property => $value) {
            $queryBuilder->where($property, $value, '=');
        }
        
        return $queryBuilder->orderBy('ID', 'ASC')
            ->buildQuery()
            ->getResult();
    }

    /**
     * @param array $criteria
     * @return BaseModel|null
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function findSingle(array $criteria): ?BaseModel
    {
        $queryBuilder = $this->createQueryBuilder();
        foreach ($criteria as $property => $value) {
            $queryBuilder->where($property, $value, '=');
        }
        return $queryBuilder->orderBy('ID', 'ASC')
            ->buildQuery()
            ->getSingleResult();
    }

    /**
     * @return Mapper
     */
    public function getMapper(): Mapper
    {
        return $this->mapper;
    }

}
