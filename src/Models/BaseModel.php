<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\InvalidOperatorException;
use NiceModules\ORM\Exceptions\NoQueryException;
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

    protected BaseModel $i18n;

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

    public function hasId()
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
     * getColumns and getPlaceholders are key functions for updating/inserting model DB records,
     * used in the TrackedCollection collection class.
     *
     * @return Column[]
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getUpdateColumns(): array
    {
        $columns = Mapper::instance(get_called_class())->getUpdateColumns();

        if (!$this->hasId()) {
            unset($columns['ID']);
        }

        return $columns;
    }

    public function getColumns(): array
    {
        return Mapper::instance(get_called_class())->getColumns();
    }

    /**
     * getColumns and getPlaceholders are key functions for updating/inserting model DB records,
     * used in the TrackedCollection to retrieve query placeholders.
     *
     * @return array
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public function getPlaceholders()
    {
        $placeholders = Mapper::instance(get_called_class())->getPlaceholders();

        if (!$this->hasId()) {
            unset($placeholders['ID']);
        }

        return $placeholders;
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
        return array_keys($this->getUpdateColumns());
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

        foreach ($this->relatedObjects as $property => $object) {
            $values['relatedObjects'][$property] = $object->getAllValues();
        }

        if (isset($this->i18n)) {
            $values['i18n'] = $this->i18n->getAllValues();
        }

        return $values;
    }

    /**
     * Return unkeyed values from this object as per the schema,
     * used in the TrackedCollection to retrieve query values
     * @return array
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
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
     * @param $property
     *
     * @return Object|null
     * @throws PropertyDoesNotExistException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     * @throws InvalidOperatorException
     * @throws NoQueryException
     */
    final public function getObjectRelatedBy($property)
    {
        if (isset($this->relatedObjects[$property])) {
            return $this->relatedObjects[$property];
        }

        $column = Mapper::instance(get_called_class())->getColumn($property);

        // If this property is a ManyToOne, check to see if it's an object and lazy
        // load it if not.
        if (isset($column->many_to_one)) {
            $foreignKey = $this->get($property);

            $orm = Manager::instance();
            $object_repository = $orm->getRepository($column->many_to_one->modelName);
            $object = $object_repository->findSingle([$column->many_to_one->propertyName => $foreignKey]);

            if ($object) {
                $this->relatedObjects[$property] = $object;
                return $object;
            }
        }

        return null;
    }

    final public function setObjectRelatedBy($property, $object)
    {
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
        }

        $column = Mapper::instance(get_called_class())->getColumn($property);

        if (!isset($column->many_to_one)) {
            throw new NotManyToOnePropertyException($property);
        }

        if (!$object instanceof $column->many_to_one->modelName) {
            throw new NotInstanceOfClassException($column->many_to_one->modelName);
        }

        $this->relatedObjects[$property] = $object;

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

        // Return the value of the field.
        if ($this->needTranslation($property)) {
            $i18n = $this->getOrCreateI18n();
            $defaultValue = $this->$property ?? null;

            if (Mapper::instance(get_called_class())->isTextProperty($property) && $defaultValue && !$i18n->get(
                    $property
                )) {
                $i18n->set($property, Manager::instance()->getI18nService()->translateDefaultToCurrent($defaultValue));
            }

            return $i18n->get($property);
        } elseif (isset($this->$property)) {
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
     * @param $property
     * @param $value
     *
     * @return bool
     * @throws PropertyDoesNotExistException
     */
    final public function set($property, $value): bool
    {
        // Check to see if the property exists on the model.
        if (!property_exists($this, $property)) {
            throw new PropertyDoesNotExistException($property, get_called_class());
        }

        if ($this->needTranslation($property)) {
            $i18n = $this->getOrCreateI18n();
            $i18n->set($property, $value);

            if (Mapper::instance(get_called_class())->isTextProperty($property)) {
                if (!isset($this->$property) || ($value && empty($this->$property))) {
                    // Automatically set the object default language translated value if needed
                    $this->$property = Manager::instance()->getI18nService()->translateCurrentToDefault($value);
                } elseif (empty($value)) {
                    $this->$property = $value;
                }
            }
        } else {
            // Just update the model with the value, if no need translation
            $this->$property = $value;
        }

        return true;
    }

    /**
     *  This function is executed right before write object to database
     *  Use for override
     */
    public function executeBeforeSave()
    {
    }

    /**
     *  This function is executed right after load object from database
     *  Use for override
     */
    public function initialize()
    {
    }

    /**
     * @param BaseModel $i18n
     */
    public function setI18n(BaseModel $i18n): void
    {
        $this->i18n = $i18n;
    }

    /**
     * @return BaseModel|null
     */
    public function getI18n(): ?BaseModel
    {
        if (!isset($this->i18n)) {
            return null;
        }

        return $this->i18n;
    }


    protected function getOrCreateI18n(): BaseModel
    {
        if (!isset($this->i18n)) {
            $i8nClassName = get_called_class() . 'I18n';
            $this->i18n = new $i8nClassName();

            if ($this->getId()) {
                $this->i18n->set('object_id', $this->getId());
                Manager::instance()->persist($this->i18n);
            }

            $this->i18n->set('language', Manager::instance()->getI18nService()->getLanguage());
        }

        return $this->getI18n();
    }

    public function needTranslation($property): bool
    {
        $column = Mapper::instance(get_called_class())->getColumn($property);
            
        return $column->i18n && Manager::instance()->getI18nService() && Manager::instance()->getI18nService()->needTranslation() ;
    }
}