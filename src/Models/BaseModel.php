<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\InvalidOperatorException;
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
    protected int $ID;

    protected string $hash;

    /**
     * @var BaseModel[]
     */
    protected array $relatedObjects = [];

    /**
     * BaseModel constructor.
     */
    public function __construct()
    {
        $this->hash = spl_object_hash($this);
    }

    /**
     * Perform a manual clone of this object.
     * @throws PropertyDoesNotExistException
     */
    public function __clone()
    {
        $class_name = get_called_class();
        $object = new $class_name;

        $columns = $this->getMapper()->getColumns();

        foreach (array_keys($columns) as $property) {
            if ($property == 'ID') {
                continue;
            }

            if (isset($this->$property)) {
                $object->set($property, $this->get($property));
            }
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

    public function hasId(): bool
    {
        if (!isset($this->ID) || (isset($this->ID) && empty($this->ID))) {
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
     */
    public function getTableName(): string
    {
        return $this->getMapper()->getTableName();
    }

    /**
     * getColumns and getPlaceholders are key functions for updating/inserting model DB records,
     * used in the TrackedCollection collection class.
     *
     * @return Column[]
     */
    public function getUpdateColumns(): array
    {
        $columns = $this->getMapper()->getUpdateColumns();

        if (!$this->hasId()) {
            unset($columns['ID']);
        }

        return $columns;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->getMapper()->getColumns();
    }

    /**
     * getColumns and getPlaceholders are key functions for updating/inserting model DB records,
     * used in the TrackedCollection to retrieve query placeholders.
     *
     * @return array
     */
    public function getPlaceholders(): array
    {
        $placeholders = $this->getMapper()->getPlaceholders();

        if (!$this->hasId()) {
            unset($placeholders['ID']);
        }

        return $placeholders;
    }

    /**
     * @return array
     */
    public function getColumnNames(): array
    {
        return array_keys($this->getUpdateColumns());
    }
    
    /**
     * Return keyed values from this object as per the schema.
     * @return array
     * @throws PropertyDoesNotExistException
     */
    public function getAllValues(): array
    {
        $values = [];
        foreach (array_keys($this->getColumns()) as $property) {
            $values[$property] = $this->get($property);
        }

        foreach ($this->relatedObjects as $property => $models) {
            foreach ($models as $modelName => $object) {
                $values['relatedObjects'][$property][$modelName] = $object->getAllValues();
            }
        }


        return $values;
    }

    /**
     * Return unkeyed values from this object as per the schema,
     * used in the TrackedCollection to retrieve query values
     * @return array
     * @throws PropertyDoesNotExistException
     */
    public function getAllUpdateValues(): array
    {
        return array_map(function ($key) {
            return $this->get($key);
        }, array_keys($this->getUpdateColumns()));
    }

    /**
     * Get the raw, underlying value of a property (don't perform a JOIN or lazy
     * loaded database query).
     *
     * @param string $property
     * @param string $modelClass
     * @return Object|null
     * @throws InvalidOperatorException
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getObjectRelatedBy(string $property, string $modelClass): ?BaseModel
    {
        if (isset($this->relatedObjects[$property][$modelClass])) {
            return $this->relatedObjects[$property][$modelClass];
        }
        
        if(!$this->getMapper()->hasForeignKey($property)){
            return null;
        }

        $manyToOne = $this->getMapper()->getForeignKey($property);

        
        // If this property is a ManyToOne, check to see if it's an object and lazy
        // load it if not.

        $foreignKey = $this->get($property);

        $orm = Manager::instance();
        $object_repository = $orm->getRepository($modelClass);
        $object = $object_repository->findSingle([$manyToOne->propertyName => $foreignKey]);

        if ($object) {
            $this->relatedObjects[$property][$manyToOne->modelName] = $object;
            return $object;
        }

        return null;
    }

    /**
     * @param string $property
     * @param BaseModel $object
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function setObjectRelatedBy(string $property, BaseModel $object)
    {
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
        }

        $this->relatedObjects[$property][$object->getClassName()] = $object;

        $column = Mapper::instance($this->getClassName())->getColumn($property);

        if (!isset($column->many_to_one)
            || (isset($column->many_to_one) && $column->many_to_one->modelName != $object->getClassName())) {
            return;
        }

        if (!isset($this->$property) || (isset($this->$property) && $this->$property !== $object->getId())) {
            $this->$property = $object->getId();
        }
    }

    /**
     * Generic getter.
     *
     * @param string $property
     *
     * @return mixed
     * @throws PropertyDoesNotExistException
     */
    public function get(string $property)
    {
        // Check to see if the property exists on the model.
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
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
     */
    public function getMultiple($columns): array
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
     * @param $property
     * @param $value
     *
     * @throws PropertyDoesNotExistException
     */
    public function set($property, $value): void
    {
        // Check to see if the property exists on the model.
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
        }

        // Update the model with the value.
        $this->$property = $value;
    }

    /**
     *  This function is executed right before write object to database
     *  Use for override
     */
    public function beforeSave()
    {
    }

    /**
     *  This function is executed right after write object to database
     *  Use for override
     */
    public function afterSave()
    {
    }

    /**
     *  This function is executed right after load object from database
     *  Use for override
     */
    public function initialize()
    {
    }

    public function getClassName(): string
    {
        return get_called_class();
    }
    
    public function getMapper(): mapper
    {
        return Mapper::instance($this->getClassName());
    }
}