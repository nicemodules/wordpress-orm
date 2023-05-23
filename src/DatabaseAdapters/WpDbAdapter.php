<?php

namespace NiceModules\ORM\DatabaseAdapters;

use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
use NiceModules\ORM\Logger;
use NiceModules\ORM\Mapper;
use wpdb;

class WpDbAdapter implements DatabaseAdapter
{
    const NAME = 'WPDB';

    protected wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Execute mysql query
     * @return bool|int|null
     */
    public function execute(string $query, array $values = [])
    {
        if ($values) {
            $query = $this->wpdb->prepare($query, $values);
        }

        Logger::instance()->log($query, true);
        return $this->wpdb->query($query);
    }

    /**
     * Fetch results from database
     * @return array|object|stdClass[]|null
     */
    public function fetch(string $query, array $values = [])
    {
        if ($values) {
            $query = $this->wpdb->prepare($query, $values);
        }

        Logger::instance()->log($query, true);
        return $this->wpdb->get_results($query);
    }

    public function fetchRow(string $query, array $values = [])
    {
        if ($values) {
            $query = $this->wpdb->prepare($query, $values);
        }

        return $this->wpdb->get_row($query);
    }

    public function escape($value)
    {
        return $this->wpdb->prepare('%s', $value);
    }

    function getPrefix(): string
    {
        return $this->wpdb->prefix;
    }

    function getCharsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }

    function updateSchema(Mapper $mapper)
    {
        if (!$mapper->getTable()->allow_schema_update) {
            throw new AllowSchemaUpdateIsFalseException($mapper->getClass());
        }

        // Build the SQL CREATE TABLE command for use with dbDelta.
        $charset_collate = $this->getCharsetCollate();

        $columnsSql = PHP_EOL . implode(", " . PHP_EOL, $mapper->getSchemas());

        $primaryKeysSql = '';

        if ($mapper->getPrimaryKeys()) {
            $primaryKeysSql = ', ' . PHP_EOL . 'PRIMARY KEY  (' . implode(',  ', $mapper->getPrimaryKeys()) . ')';
        }

        $indexesSql = '';

        if (isset($mapper->getTable()->indexes)) {
            $indexes = [];
            foreach ($mapper->getTable()->indexes as $index) {
                $indexes[] = 'INDEX ' . $index->name . ' (' . implode(',', $index->columns) . ')';
            }

            $indexesSql = ', ' . PHP_EOL  . implode(', ' . PHP_EOL, $indexes);
        }

        $sql = "CREATE TABLE " . $mapper->getTableName() . " (" .
            $columnsSql .
            $primaryKeysSql .
            $indexesSql . PHP_EOL .
            ")" . PHP_EOL . $charset_collate . ';';
        
        Logger::instance()->log('DbDeltaSql: ' . $sql);

        // Use dbDelta to do all the hard work.
        // Note that dbDelta doesn't support foreign key's and require specific format of sql query (spaces and new lines)
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);

        $this->updateForeignKeys($mapper);
    }

    protected function updateForeignKeys(Mapper $mapper)
    {
        foreach ($mapper->getForeignKeys() as $column => $manyToOne) {
            $foreignMapper = Mapper::instance($manyToOne->modelName);

            $sql = "SELECT i.TABLE_NAME, i.CONSTRAINT_TYPE, i.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
                    FROM information_schema.TABLE_CONSTRAINTS i 
                    LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME 
                    WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY' 
                    AND i.TABLE_SCHEMA = DATABASE()
                    AND i.TABLE_NAME = '" . $mapper->getTableName() . "'";

            $constraints = $this->fetch($sql);

            $found = false;

            foreach ($constraints as $constraint) {
                if ($constraint->COLUMN_NAME == $column) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $query = 'ALTER TABLE ' . $mapper->getTableName() .
                    ' ADD CONSTRAINT fk_' . $column .
                    ' FOREIGN KEY (' . $column . ')' .
                    ' REFERENCES ' . $foreignMapper->getTableName() . ' (ID)' .
                    ' ON DELETE ' . $manyToOne->onDelete;

                $this->execute($query);
            }

            return true;
        }
    }

}