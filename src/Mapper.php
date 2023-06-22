<?php

namespace NiceModules\ORM;

use Doctrine\Common\Annotations\AnnotationReader;
use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\ManyToOne;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\AllowDropIsFalseException;
use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
use NiceModules\ORM\Exceptions\IncompleteIndexException;
use NiceModules\ORM\Exceptions\IncompleteManyToOneException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class Mapper
{
    /**
     * @var Mapper[]
     */
    private static array $instances = [];

    /**
     * @var AnnotationReader
     */
    private AnnotationReader $reader;

    private string $class;
    private Table $table;
    /**
     * All defined columns
     * @var Column[]
     */
    private array $columns = [];

    /**
     * Columns that are allowed to insert or update
     * @var Column[]
     */
    private array $updateColumns = [];
    private array $schemas = [];
    private array $placeholders = [];
    private array $primaryKeys = [];
    /**
     * @var ManyToOne[]
     */
    private array $foreignKeys = [];
    private bool $validated = true;

    /**
     * Initializes a non static copy of itself when called. Subsequent calls
     * return the same object (fake dependency injection/service).
     *
     * @param $modelClassName
     * @return Mapper
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public static function instance($modelClassName): Mapper
    {
        // Initialize the service if it's not already set.
        if (!isset(self::$instances[$modelClassName])) {
            $instance = new static();
            self::$instances[$modelClassName] = $instance;
            $instance->class = $modelClassName;
            $instance->map();
        }

        // Return the instance of searched mapper.
        return self::$instances[$modelClassName];
    }

    /**
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        $prefix = Manager::instance()->getAdapter()->getPrefix();

        if (isset($this->table->prefix)) {
            $prefix .= $this->table->prefix . '_';
        }

        return $prefix;
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @return array
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * @return array
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    /**
     * @param $property
     * @return ?string
     */
    public function getPlaceholder($property): ?string
    {
        if (isset($this->placeholders[$property])) {
            return $this->placeholders[$property];
        }

        return null;
    }

    /**
     * @param $name
     * @return Column
     * @throws PropertyDoesNotExistException
     */
    public function getColumn($name): Column
    {
        if (!isset($this->columns[$name])) {
            throw new PropertyDoesNotExistException($name, $this->class);
        }

        return $this->columns[$name];
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasColumn($name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @return array
     */
    public function getTableColumnNames(): array
    {
        $columnNames = $this->getColumnNames();

        $tableColumnNames = [];
        foreach ($columnNames as $columnName) {
            $tableColumnNames[$columnName] = $this->getTableName() . '.' . $columnName;
        }

        return $tableColumnNames;
    }

    public function getTableColumnName($name): string
    {
        $tableColumnNames = $this->getTableColumnNames();

        if (!isset($tableColumnNames[$name])) {
            throw new PropertyDoesNotExistException($name, $this->class);
        }

        return $tableColumnNames[$name];
    }

    public function getTableColumnNameProperty($tableColumnName): string
    {
        $tableColumnNames = $this->getTableColumnNames();

        if (!in_array($tableColumnName, $tableColumnNames)) {
            throw new PropertyDoesNotExistException($tableColumnName, $this->class);
        }

        return array_search($tableColumnName, $tableColumnNames);
    }

    public function hasTableColumnName($name): bool
    {
        $tableColumnNames = $this->getTableColumnNames();

        return in_array($name, $tableColumnNames);
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->getPrefix() . $this->getTable()->name;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return string[]
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return array
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    /**
     * @return array
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function getForeignKey($name): ManyToOne
    {
        if (!isset($this->foreignKeys[$name])) {
            throw new PropertyDoesNotExistException($name, $this->class);
        }

        return $this->foreignKeys[$name];
    }

    /**
     * Compares a database table schema to the model schema (as defined in th
     * annotations). If there are any differences, the database schema is modified to
     * match the model.
     *
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function updateSchema()
    {
        Manager::instance()->getAdapter()->updateSchema($this);
    }

    /**
     * @throws AllowDropIsFalseException
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function dropTable()
    {
        // Are we allowed to update the schema of this model in the db?
        if (!$this->table->allow_schema_update) {
            throw new AllowSchemaUpdateIsFalseException($this->class);
        }

        // Additional protection before drop
        if (!$this->table->allow_drop) {
            throw new AllowDropIsFalseException($this->class);
        }

        // Drop the table.
        $sql = "DROP TABLE IF EXISTS " . $this->getPrefix() . $this->table->name;
        Manager::instance()->getAdapter()->execute($sql);
    }

    /**
     * @return Column[]
     */
    public function getUpdateColumns(): array
    {
        return $this->updateColumns;
    }

    public function isTextProperty($property): bool
    {
        $column = $this->getColumn($property);
        if (in_array($column->type, [
            'varchar',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
        ])) {
            return true;
        }

        return false;
    }

    /**
     * Inherited model properties needs to be sorted in schema
     */
    protected function sort(&$array)
    {
        if (isset($this->table->column_order)) {
            $tmp = $array;
            $array = [];

            foreach ($this->table->column_order as $name) {
                if (isset($tmp[$name])) {
                    $array[$name] = $tmp[$name];
                    unset($tmp[$name]);
                }
            }
            $array = array_merge($array, $tmp);
        }
    }

    /**
     * Returns an instance of the annotation reader (caches within this request).
     *
     * @return AnnotationReader
     */
    private function getReader(): AnnotationReader
    {
        // If the annotation reader isn't set, create it.
        if (!isset($this->reader)) {
            $this->reader = new AnnotationReader();
        }

        return $this->reader;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param $propertyName
     * @return Column|null
     * @throws ReflectionException
     */
    private function getPropertyAnnotations(ReflectionClass $reflectionClass, $propertyName): ?Column
    {
        $property = $reflectionClass->getProperty($propertyName);
        return $this->getReader()->getPropertyAnnotation($property, Column::class);
    }

    /**
     * Process the class annotations, adding an entry the $this->model, $this->schema, $this->placeholder array.
     *
     * @return void
     * @throws IncompleteIndexException
     * @throws IncompleteManyToOneException
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    private function map(): void
    {
        $reflection_class = new ReflectionClass($this->class);

        // Get the annotation reader instance.
        /** @var Table $classAnnotations */
        $this->table = $this->getReader()->getClassAnnotation($reflection_class, Table::class);

        $this->validateModel($this->table);

        // Loop through the class properties.
        foreach ($reflection_class->getProperties() as $property) {
            // Get the annotations of this property.
            $column = $this->getPropertyAnnotations($reflection_class, $property->name);

            // Silently ignore properties that do not have the ORM column type annotation.
            if (isset($column->type)) {
                // Register annotation 
                $this->columns[$property->name] = $column;

                if ($column->allow_update) {
                    $this->updateColumns[$property->name] = $column;
                }

                if (isset($column->primary)) {
                    $this->primaryKeys[] = $property->name;
                }

                $this->addSchemaString($property, $column);

                if ($column->allow_update) {
                    $this->addPlaceholder($property, $column);
                }
            }
        }

        $this->sort($this->schemas);
        $this->sort($this->columns);
        $this->sort($this->updateColumns);
        $this->sort($this->placeholders);
    }

    /**
     * @param ReflectionProperty $property
     * @param Column $column
     * @throws UnknownColumnTypeException
     * @throws IncompleteManyToOneException
     */
    private function addSchemaString(ReflectionProperty $property, Column $column)
    {
        $column_type = strtolower($column->type);

        // Test the ORM column type
        if (!in_array($column_type, [
            'datetime',
            'timestamp',
            'tinyint',
            'smallint',
            'int',
            'bigint',
            'varchar',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
            'float',
            'decimal',
            'boolean',
        ])
        ) {
            throw new UnknownColumnTypeException($column_type, $this->class);
        }

        // Build the rest of the schema partial.
        $schema_string = $property->name . ' ' . $column_type;

        if (isset($column->length)) {
            $schema_string .= '(' . $column->length . ')';
        }

        if (isset($column->null)) {
            $schema_string .= ' ' . $column->null;
        }

        if (isset($column->default)) {
            $schema_string .= ' DEFAULT ' . $column->default;
        }

        if ((isset($column->primary) && $column->primary) || (isset($column->auto_incremet) && $column->auto_incremet)) {
            $schema_string .= ' auto_increment';
        }

        if (isset($column->many_to_one)) {
            if (!isset($column->many_to_one->modelName) || !isset($column->many_to_one->propertyName)) {
                throw new IncompleteManyToOneException($this->class, $property->name);
            }

            $this->foreignKeys[$property->name] = $column->many_to_one;
        }

        $this->schemas[$property->name] = $schema_string;
    }

    /**
     * @param ReflectionProperty $property
     * @param Column $column
     */
    private function addPlaceholder(ReflectionProperty $property, Column $column)
    {
        $column_type = strtolower($column->type);

        // Add the schema to the mapper array for this class.
        $placeholder_values_type = '%s';  // Initially assume column is string type.

        if (in_array($column_type, [  // If the column is a decimal type.
            'int',
            'tinyint',
            'smallint',
            'bigint',
        ])) {
            $placeholder_values_type = '%d';
        }

        if (in_array($column_type, [  // If the column is a float type.
            'float',
            'decimal',
        ])) {
            $placeholder_values_type = '%f';
        }

        $this->placeholders[$property->name] = $placeholder_values_type;
    }

    /**
     * @param Table $table
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws IncompleteIndexException
     */
    private function validateModel(Table $table)
    {
        // Validate type
        if (!isset($table->type)) {
            $this->validated = false;

            throw new RequiredAnnotationMissingException('type', $this->class);
        }

        // Validate table
        if (!isset($table->name)) {
            $this->validated = false;
            throw new RequiredAnnotationMissingException('name', $this->class);
        }

        // Validate allow_schema_update
        if (!isset($table->allow_schema_update)) {
            $this->validated = false;
            throw new RequiredAnnotationMissingException('allow_schema_update', $this->class);
        }

        // Validate repository
        if (isset($table->repository)) {
            if (!class_exists($table->repository)) {
                throw new RepositoryClassNotDefinedException($table->repository, $this->class);
            }
        }

        // Validate indexes
        if (isset($table->indexes)) {
            foreach ($table->indexes as $index) {
                if (!isset($index->name) || !isset($index->columns)) {
                    throw new IncompleteIndexException($this->class);
                }
            }
        }
    }
}
