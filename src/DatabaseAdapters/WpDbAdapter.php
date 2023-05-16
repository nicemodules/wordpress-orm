<?php

namespace NiceModules\ORM\DatabaseAdapters;

use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
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

    public function connect()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function disconnect()
    {
        $this->wpdb = null;
    }

    /**
     * Execute mysql query
     * @return bool|int|null
     */
    function execute(string $query, array $values = [])
    {
        if ($values) {
            $query = $this->wpdb->prepare($query, $values);
        }

        return $this->wpdb->query($query);
    }

    /**
     * Fetch results from database
     * @return bool|int|null
     */
    public function fetch(string $query, array $values = [])
    {
        if ($values) {
            $query = $this->wpdb->prepare($query, $values);
        }

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
                $indexes[] = ', ' . PHP_EOL . 'INDEX ' . $index->name . ' (' . implode(',', $index->columns) . ')';
            }

            $indexesSql = implode(', ' . PHP_EOL, $indexes);
        }

        $sql = "CREATE TABLE " . $mapper->getTableName() . " (" .
            $columnsSql .
            $primaryKeysSql .
            $indexesSql . PHP_EOL .
            ")" . PHP_EOL . $charset_collate . ';';


        // Use dbDelta to do all the hard work.
        // Note that dbDelta doesn't support foreign key's and require specific format of sql query (spaces and new lines)
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
}