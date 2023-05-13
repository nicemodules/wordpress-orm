<?php

namespace NiceModules\ORM;

use Doctrine\Common\Annotations\AnnotationReader;
use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\AllowDropIsFalseException;
use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
use NiceModules\ORM\Exceptions\IncompleteIndexException;
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
     * @var Column[]
     */
    private array $columns = [];
    private array $schemas = [];
    private array $placeholders = [];
    private array $primaryKeys = [];
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
        global $wpdb;

        $prefix = $wpdb->prefix;

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
     * @param $name
     * @return Column|null
     */
    public function getColumn($name): ?Column
    {
        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }

        return null;
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
            // Register annotation 
            $this->columns[$property->name] = $column;
            // Silently ignore properties that do not have the ORM column type annotation.
            if (isset($column->type)) {
                if (isset($column->primary)) {
                    $this->primaryKeys[] = $property->name;
                }

                $this->addSchemaString($property, $column);
                $this->addPlaceholder($property, $column);
            }
        }
            
        $this->sortSchemas();
    }

    /**
     * @param ReflectionProperty $property
     * @param Column $column
     * @throws UnknownColumnTypeException
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
            $schema_string .= ' DEFAULT' . $column->default;
        }

        if (isset($column->default)) {
            $schema_string .= ' DEFAULT' . $column->default;
        }

        if ((isset($column->primary) && $column->primary) || (isset($column->auto_incremet) && $column->auto_incremet)) {
            $schema_string .= ' auto_increment';
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

    /**
     * Compares a database table schema to the model schema (as defined in th
     * annotations). If there are any differences, the database schema is modified to
     * match the model.
     *
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function updateSchema()
    {
        global $wpdb;

        // Are we allowed to update the schema of this model in the db?
        if (!$this->table->allow_schema_update) {
            throw new AllowSchemaUpdateIsFalseException($this->class);
        }

        // Build the SQL CREATE TABLE command for use with dbDelta.
        $table_name = $this->getPrefix() . $this->table->name;

        $charset_collate = $wpdb->get_charset_collate();
        
        $columnsSql = PHP_EOL . implode(", " . PHP_EOL, $this->schemas);

        $primaryKeysSql = '';

        if ($this->primaryKeys) {
            $primaryKeysSql = ', ' . PHP_EOL . 'PRIMARY KEY  (' . implode(',  ', $this->primaryKeys) . ')';
        }

        $indexesSql = '';

        if (isset($this->table->indexes)) {
            $indexes = [];
            foreach ($this->table->indexes as $index) {
                $indexes[] = ', ' . PHP_EOL . 'INDEX ' . $index->name . ' (' . implode(',', $index->columns) . ')';
            }

            $indexesSql = implode(', ' . PHP_EOL, $indexes);
        }

        $sql = "CREATE TABLE " . $table_name . " (" .
            $columnsSql .
            $primaryKeysSql .
            $indexesSql . PHP_EOL .
            ")" . PHP_EOL . $charset_collate . ';';


        // Use dbDelta to do all the hard work.
        // Note that dbDelta doesn't support foreign key's and require specific format of sql query (spaces and new lines)
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Inherited model properties needs to be sorted in schema
     */
    protected function sortSchemas()
    {
        if (isset($this->table->column_order)) {
            $tmp = $this->schemas;
            $this->schemas = [];

            foreach ($this->table->column_order as $name) {
                if (isset($tmp[$name])) {
                    $this->schemas[$name] = $tmp[$name];
                    unset($tmp[$name]);
                }
            }
            $this->schemas = array_merge($this->schemas, $tmp);
        }
    }

    /**
     * @throws AllowDropIsFalseException
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function dropTable()
    {
        global $wpdb;

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
        $wpdb->query($sql);
    }
}
