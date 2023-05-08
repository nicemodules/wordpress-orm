<?php

namespace NiceModules\ORM;

use Doctrine\Common\Annotations\AnnotationReader;
use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use ReflectionClass;
use ReflectionException;

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

    /**
     * Initializes a non static copy of itself when called. Subsequent calls
     * return the same object (fake dependency injection/service).
     *
     * @param $className
     * @return Mapper
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    public static function instance($className): Mapper
    {
        // Initialize the service if it's not already set.
        if (!isset(self::$instances[$className])) {
            $instance = new static();
            self::$instances[$className] = $instance;
            $instance->class = $className;
            $instance->map();
        }

        // Return the instance of searched mapper.
        return self::$instances[$className];
    }

    /**
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        global $wpdb;

        $prefix = $wpdb->prefix;

        if (isset($this->table->prefix)) {
            $prefix .= $this->table->prefix;
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
    protected function getPropertyAnnotations(ReflectionClass $reflectionClass, $propertyName): ?Column
    {
        $property = $reflectionClass->getProperty($propertyName);
        return $this->getReader()->getPropertyAnnotation($property, Column::class);
    }

    /**
     * Process the class annotations, adding an entry the $this->model, $this->schema, $this->placeholder array.
     *
     * @return void
     * @throws ReflectionException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     */
    protected function map(): void
    {
        if (!isset($this->table)) {
            $reflection_class = new ReflectionClass($this->class);

            // Get the annotation reader instance.
            /** @var Table $classAnnotations */
            $this->table = $this->getReader()->getClassAnnotation($reflection_class, Table::class);

            $this->validateModel($this->table);

            // Loop through the class properties.
            foreach ($reflection_class->getProperties() as $property) {
                // Get the annotations of this property.
                $property_annotations = $this->getPropertyAnnotations($reflection_class, $property->name);
                $this->columns[$property->name] = $property_annotations;

                // Silently ignore properties that do not have the ORM column type annotation.
                if (isset($property_annotations->type)) {
                    $column_type = strtolower($property_annotations->type);

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
                        throw new UnknownColumnTypeException(
                            sprintf(
                                __('Unknown model property column type %s set in @ORM column type on model %s..'),
                                $column_type,
                                $this->class
                            )
                        );
                    }

                    // Build the rest of the schema partial.
                    $schema_string = $property->name . ' ' . $column_type;

                    if (isset($property_annotations->length)) {
                        $schema_string .= '(' . $property_annotations->length . ')';
                    }

                    if (isset($property_annotations->null)) {
                        $schema_string .= ' ' . $property_annotations->null;
                    }

                    // Add the schema to the mapper array for this class.
                    $placeholder_values_type = '%s';  // Initially assume column is string type.

                    if (in_array($column_type, [  // If the column is a decimal type.
                        'tinyint',
                        'smallint',
                        'bigint',
                    ])) {
                        $placeholder_values_type = '%d';
                    }

                    if (in_array($column_type, [  // If the column is a float type.
                        'float',
                    ])) {
                        $placeholder_values_type = '%f';
                    }

                    $this->schemas[$property->name] = $schema_string;
                    $this->placeholders[$property->name] = $placeholder_values_type;
                }
            }
        }
    }

    /**
     * @param Table $model
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     */
    protected function validateModel(Table $model)
    {
        // Validate type
        if (!isset($model->type)) {
            $this->table['validated'] = false;

            throw new RequiredAnnotationMissingException(
                sprintf(__('The annotation type does not exist on the model %s.'), $this->class)
            );
        }

        // Validate table
        if (!isset($model->table)) {
            $this->table['validated'] = false;
            throw new RequiredAnnotationMissingException(
                sprintf(__('The annotation ORM->table does not exist on the model %s.'), $this->class)
            );
        }

        // Validate allow_schema_update
        if (!isset($model->allow_schema_update)) {
            $this->table['validated'] = false;
            throw new RequiredAnnotationMissingException(
                sprintf(__('The annotation ORM_AllowSchemaUpdate does not exist on the model.'), $this->class)
            );
        }

        // Validate repository
        if (isset($model->repository)) {
            if (!class_exists($model->repository)) {
                throw new RepositoryClassNotDefinedException(
                    sprintf(
                        __('Repository class %s does not exist on model %s.'),
                        $model->repository,
                        $this->class
                    )
                );
            }
        }
    }

    /**
     * Compares a database table schema to the model schema (as defined in th
     * annotations). If there any differences, the database schema is modified to
     * match the model.
     *
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function updateSchema()
    {
        global $wpdb;

        // Are we allowed to update the schema of this model in the db?
        if (!$this->table->allow_schema_update) {
            throw new AllowSchemaUpdateIsFalseException(
                sprintf(__('Refused to update model schema %s. ORM_AllowSchemaUpdate is FALSE.'), $this->class)
            );
        }

        // Create an ID type string.
        $id_type = 'ID';
        $id_type_string = 'id bigint(20) NOT NULL AUTO_INCREMENT';

        // Build the SQL CREATE TABLE command for use with dbDelta.
        $table_name = $this->getPrefix() . $this->table->table;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . $table_name . " (
          " . $id_type_string . ",
          " . implode(",\n  ", $this->schemas) . ",
          PRIMARY KEY (" . $id_type . ")
        ) $charset_collate;";

        echo $sql;

        // Use dbDelta to do all the hard work.
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * @throws AllowSchemaUpdateIsFalseException
     */
    public function dropTable()
    {
        global $wpdb;
        
        // Are we allowed to update the schema of this model in the db?
        if (!$this->table->allow_schema_update) {
            throw new AllowSchemaUpdateIsFalseException(
                sprintf(__('Refused to drop table for model %s. ORM_AllowSchemaUpdate is FALSE.'), $this->class)
            );
        }

        // Drop the table.
        $table_name = self::getPrefix() . $this->table->table;
        $sql = "DROP TABLE IF EXISTS " . $table_name;
        $wpdb->query($sql);
    }

}
