<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use NiceModules\ORM\Manager;
use NiceModules\ORM\Mapper;
use ReflectionException;

/**
 * @Table(column_order={"ID"})
 */
abstract class BaseModel
{
    /**
     * @Column(type="int", length="10", null="NOT NULL", primary=true)
     */
    protected ?int $ID;

    protected string $hash;

    /**
     * BaseModel constructor.
     */
    public function __construct()
    {
        $this->hash = spl_object_hash($this);
    }

    /**
     * Perform a manual clone of this object.
     */
    public function __clone()
    {
        $class_name = get_called_class();
        $object = new $class_name;

        $columns = Mapper::instance($class_name)->getColumns();

        foreach (array_keys($columns) as $property) {
            if($property == 'ID'){
                continue;
            }
            $object->set($property, $this->getRaw($property));
        }
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        if (isset($this->ID)) {
            return $this->ID;
        }
        
        return null;
    }
    
    public function hasId(){
        if(!isset($this->ID) || (isset($this->ID) && empty($this->ID))){
            return false;
        }
        return true;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return mixed
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function getTableName(): string
    {
        return Mapper::instance(get_called_class())->getTableName();
    }

    /**
     * @return Column[]
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getColumns(): array
    {
        $columns = Mapper::instance(get_called_class())->getColumns();
        
        if(!$this->hasId()){
            unset($columns['ID']);
        }
        
        return $columns;
    }

    /**
     * @return array
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getColumnNames(): array
    {
        return array_keys($this->getColumns());
    }

    /**
     * @return mixed
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getPlaceholders()
    {
        $placeholders = Mapper::instance(get_called_class())->getPlaceholders();

        if(!$this->hasId()){
            unset($placeholders['ID']);
        }
        
        return $placeholders;
    }

    /**
     * Return keyed values from this object as per the schema.
     * @return array
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getAllValues(): array
    {
        $values = [];
        foreach (array_keys($this->getColumns()) as $property) {
            $values[$property] = $this->get($property);
        }
        return $values;
    }

    /**
     * Return unkeyed values from this object as per the schema (no ID).
     * @return array
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getAllUnkeyedValues(): array
    {
        return array_map(function ($key) {
            return $this->get($key);
        }, array_keys($this->getColumns()));
    }

    /**
     * Get the raw, underlying value of a property (don't perform a JOIN or lazy
     * loaded database query).
     *
     * @param $property
     *
     * @return mixed
     */
    final public function getRaw($property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        }
        
        return null;
    }

    /**
     * Generic getter.
     *
     * @param string $property
     *
     * @return mixed
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    final public function get(string $property)
    {
        // Check to see if the property exists on the model.
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
        }

        // If this property is a ManyToOne, check to see if it's an object and lazy
        // load it if not.
        $column = Mapper::instance(get_called_class())->getColumn($property);

        if (isset($column->many_to_one) && isset($this->$property) && !is_object($this->$property)) {
            // Lazy load.
            $orm = Manager::getManager();
            $object_repository = $orm->getRepository($column->many_to_one->modelName);
            $object = $object_repository->findBy([$column->many_to_one->propertyName => $this->$property]);

            if ($object) {
                $this->$property = $object;
            }
        }

        // Return the value of the field.
        if (isset($this->$property)) {
            return $this->$property;
        }

        return null;
    }

    /**
     * Get multiple values from this object given an array of properties.
     *
     * @param $columns
     * @return array
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    final public function getMultiple($columns): array
    {
        $results = [];

        if (is_array($columns)) {
            foreach ($columns as $column) {
                $results[$column] = $this->get($column);
            }
        }

        return $results;
    }

    /**
     * Generic setter.
     *
     * @param $column
     * @param $value
     *
     * @return bool
     * @throws PropertyDoesNotExistException
     */
    final public function set($column, $value): bool
    {
        // Check to see if the property exists on the model.
        if (!property_exists($this, $column)) {
            throw new PropertyDoesNotExistException($column, get_called_class());
        }

        // Update the model with the value.
        $this->$column = $value;

        return true;
    }
}