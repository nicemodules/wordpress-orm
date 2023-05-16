<?php

namespace NiceModules\ORM;

use NiceModules\ORM\Collections\TrackedCollection;
use NiceModules\ORM\DatabaseAdapters\DatabaseAdapter;
use NiceModules\ORM\DatabaseAdapters\WpDbAdapter;
use NiceModules\ORM\Exceptions\FailedToDeleteException;
use NiceModules\ORM\Exceptions\FailedToInsertException;
use NiceModules\ORM\Exceptions\FailedToUpdateException;
use NiceModules\ORM\Models\BaseModel;
use NiceModules\ORM\Repositories\BaseRepository;
use ReflectionException;
use Throwable;

class Manager extends Singleton
{
    /**
     * Holds an array of objects the manager knows exist in the database (either
     * from a query or a previous persist() call).
     *
     * @var TrackedCollection
     */
    private TrackedCollection $tracked;
    private DatabaseAdapter $adapter;


    /**
     * Manager constructor.
     * TODO: pdo adapter
     */
    protected function __construct()
    {
        $this->tracked = new TrackedCollection;

        $this->adapter = new WpDbAdapter();

//        switch (ORM_ADAPTER) {
//            case WpDbAdapter::NAME:
//                {
//                    $this->adapter = new WpDbAdapter();
//                }
//                break;
//            case PDOAdapter::NAME:
//                {
//                    $this->adapter = new PDOAdapter();
//                }
//                break;
//        }
    }

    /**
     * Get repository instance from classname.
     *
     * @param $classname
     *
     * @return BaseRepository
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function getRepository($classname)
    {
        // Get the annotations for this class.
        $annotations = Mapper::instance($classname)->getTable();

        // Get the repository for this class (this has already been validated in the Mapper). .
        if (isset($annotations->repository)) {
            return $annotations->repository::getInstance($classname, $annotations);
        } // No repository set, so assume the base.
        else {
            return BaseRepository::getInstance($classname, $annotations);
        }
    }

    /**
     * Queue this model up to be added to the database with flush().
     *
     * @param BaseModel $object
     */
    public function persist(BaseModel $object)
    {
        // Start tracking this object (because it is new, it will be tracked as
        // something to be INSERTed)
        $this->tracked[$object] = _OBJECT_NEW;
    }

    /**
     * Start tracking a model known to exist in the database.
     *
     * @param BaseModel $object
     */
    public function track(BaseModel $object)
    {
        // Save it against the key.
        $this->tracked[$object] = _OBJECT_TRACKED;
    }

    /**
     * This model should be removed from the db when flush() is called.
     *
     * @param $model
     */
    public function remove(BaseModel $object)
    {
        unset($this->tracked[$object]);
    }

    /**
     * Start tracking a model known to exist in the database.
     *
     * @param BaseModel $object
     */
    public function clean(BaseModel $object)
    {
        // Save it against the key.
        $this->tracked[$object] = _OBJECT_CLEAN;
    }

    /**
     * @return DatabaseAdapter
     */
    public function getAdapter(): DatabaseAdapter
    {
        return $this->adapter;
    }

    /**
     * Add new objects to the database.
     * This will perform one query per table no matter how many records need to
     * be added.
     *
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws FailedToInsertException
     * @throws ReflectionException
     */
    private function _flush_insert()
    {
        // Get a list of tables and columns that have data to insert.
        $insert = $this->tracked->getInsertUpdateTableData('getPersistedObjects');

        // Process the INSERTs
        if (count($insert)) {
            // Build the combined query for table: $tablename
            foreach ($insert as $classname => $values) {
                $table_name = $values['table_name'];
                $sql = '';

                try {
                    $this->getAdapter()->execute('START TRANSACTION;');
                    // Build the placeholder SQL query.

                    $sql .= "INSERT INTO " . $table_name . "
                        (" . implode(", ", $values['columns']) . ")
                        VALUES
                         ";

                    while ($values['placeholders_count'] > 0) {
                        $sql .= "(" . implode(", ", $values['placeholders']) . ")";

                        if ($values['placeholders_count'] > 1) {
                            $sql .= ",
                            ";
                        }

                        $values['placeholders_count'] -= 1;
                    }

                    $sql .= ';' . PHP_EOL;

                    // Insert using Wordpress prepare() which provides SQL injection protection (apparently).
                    $count = $this->getAdapter()->execute($sql, $values['values']);

                    // Get last inserted id && row count 
                    $result = $this->getAdapter()->fetchRow('SELECT LAST_INSERT_ID() as id, ROW_COUNT() as rows;');

                    // Start tracking all the added objects.
                    if ($count && $count == $result->rows && $count == count($values['objects'])) {
                        $this->getAdapter()->execute('COMMIT;');

                        foreach ($values['objects'] as $object) {
                            $object->set('ID', $result->id);
                            $this->track($object);
                            $result->id++;
                        }
                    } // Something went wrong. ROOLBACK;
                    else {
                        $this->getAdapter()->execute('ROLLBACK;');
                        throw new FailedToInsertException();
                    }
                } catch (Throwable $e) {
                    $this->getAdapter()->execute('ROLLBACK;');
                    throw $e;
                }
            }
        }
    }

    /**
     * Compares known database state of tracked objects and compares them with
     * the current state. Applies any changes to the database.
     *
     * This will perform one query per table no matter how many records need to
     * be updated.
     * https://stackoverflow.com/questions/3432/multiple-updates-in-mysql
     */
    private function _flush_update()
    {
        // Get a list of tables and columns that have data to update.
        $update = $this->tracked->getInsertUpdateTableData('getChangedObjects');

        // Process the UPDATESs
        if (count($update)) {
            // Build the combined query for table: $tablename
            foreach ($update as $classname => $values) {
                $tableName = $values['table_name'];
                $sql = '';

                try {
                    $sql .= "INSERT INTO " . $tableName . "
                        (" . implode(", ", $values['columns']) . ")
                        VALUES
                         ";

                    while ($values['placeholders_count'] > 0) {
                        $sql .= "(" . implode(", ", $values['placeholders']) . ")";

                        if ($values['placeholders_count'] > 1) {
                            $sql .= ",
                            ";
                        }

                        $values['placeholders_count'] -= 1;
                    }

                    $sql .= "
                        ON DUPLICATE KEY UPDATE
                        ";
                    
                    $update_set = [];

                    foreach ($values['columns'] as $column) {
                        if ($column == 'ID') {
                            continue;
                        }

                        $update_set[] = $column . " = VALUES(" . $column . ")";
                    }

                    $sql .= implode(", ", $update_set) . ";";

                    // Update using WordPress prepare() which provides SQL injection protection (apparently).
                    $count = $this->getAdapter()->execute($sql, $values['values']);

                    if ($count) {
                        foreach ($values['objects'] as $object) {
                            $this->track($object);
                        }
                    } // Something went wrong. ROOLBACK;
                    else {
                        throw new FailedToUpdateException();
                    }
                } catch (Throwable $e) {
                    $this->getAdapter()->execute('ROLLBACK;');
                    throw $e;
                }
            }
        }
    }

    private function _flush_delete()
    {
        // Get a list of tables and columns that have data delete.
        $update = $this->tracked->getRemoveTableData();

        // Process the DELETEs
        if (count($update)) {
            // Build the combined query for table: $tablename
            foreach ($update as $classname => $values) {
                $table_name = Mapper::instance($classname)->getPrefix() . $values['table_name'];

                try {
                    // Build the SQL.
                    $sql = "DELETE FROM " . $table_name . " WHERE ID IN (" . implode(
                            ", ",
                            array_fill(
                                0,
                                count($values['values']),
                                "%d"
                            )
                        ) . ");";


                    // Process all deletes for a particular table together as a single query.
                    $count = $this->adapter->execute($sql, $values['values']);

                    // Really remove the object from the tracking list.
                    if($count){
                        foreach ($values['objects'] as $object) {
                            $this->clean($object);
                        }    
                    }else{
                        throw new FailedToDeleteException();
                    }
                    
                } catch (Throwable $e) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Apply changes to all models queued up with persist().
     * Attempts to combine queries to reduce MySQL load.
     *
     * @throws Exceptions\RepositoryClassNotDefinedException
     * @throws Exceptions\RequiredAnnotationMissingException
     * @throws Exceptions\UnknownColumnTypeException
     * @throws FailedToInsertException
     * @throws FailedToUpdateException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function flush()
    {
        $this->_flush_update();
        $this->_flush_insert();
        $this->_flush_delete();
    }

}