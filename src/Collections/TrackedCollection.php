<?php

namespace NiceModules\ORM\Collections;

use ArrayAccess;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Exceptions\RepositoryClassNotDefinedException;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Exceptions\UnknownColumnTypeException;
use NiceModules\ORM\Logger;
use NiceModules\ORM\Models\BaseModel;
use ReflectionException;


/**
 * Class TrackedCollection
 *
 * Holds globally tracked objects that have been fetched or persisted to the
 * database.
 */
class TrackedCollection implements ArrayAccess
{
    const _OBJECT_NEW = 1;
    const _OBJECT_TRACKED = 2;
    const _OBJECT_CLEAN = -1;

    /**
     * The internal array of objects to track.
     *
     * @var array
     */
    private $list;

    /**
     * TrackedCollection constructor.
     */
    public function __construct()
    {
        $this->list = [];
    }

    /**
     * Get data used to INSERT/UPDATE objects in the database.
     *
     * @param $iterator
     * @return array
     * @throws PropertyDoesNotExistException
     * @throws RepositoryClassNotDefinedException
     * @throws RequiredAnnotationMissingException
     * @throws UnknownColumnTypeException
     * @throws ReflectionException
     */
    public function getInsertUpdateTableData($iterator)
    {
        $data = [];

        // Get the structural data.
        foreach ($this->$iterator() as $item) {
            /** @var BaseModel $model */
            $model = $item['model'];
            $modelClass = get_class($model);

            // execute model action before save
            $model->executeBeforeSave();

            // Add the table name and schema data (only once).
            if (!isset($data[$modelClass])) {
                $data[$modelClass] = [
                    'objects' => [],
                    'table_name' => $model->getTableName(),
                    'columns' => $model->getColumnNames(),
                    'placeholders' => $model->getPlaceholders(),
                    'placeholders_count' => 0,
                    'values' => [],
                ];
            }

            // Store the object.
            $data[$modelClass]['objects'][] = $model;

            // Now add the placeholder and row data.
            $data[$modelClass]['placeholders_count'] += 1;

            $data[$modelClass]['values'] = array_merge(
                $data[$modelClass]['values'],
                $model->getAllUpdateValues()
            );
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getRemoveTableData()
    {
        $data = [];

        foreach ($this->getRemovedObjects() as $item) {
            if (!isset($data[get_class($item['last_state'])])) {
                $data[get_class($item['last_state'])] = [
                    'objects' => [],
                    'table_name' => $item['last_state']->getTableName(),
                    'values' => [],
                ];
            }

            $data[get_class($item['last_state'])]['objects'][] = $item['last_state'];
            $data[get_class($item['last_state'])]['values'][] = $item['last_state']->getId();
        }

        return $data;
    }

    /**
     * @param mixed $object
     * @param mixed $state
     */
    public function offsetSet($object, $state)
    {
        switch ($state) {
            // If new, objects will have a 'model' but no 'last_state',
            case self::_OBJECT_NEW:
                Logger::instance()->log('Tracking _OBJECT_NEW: ', 1);
                Logger::instance()->log($object);
                $this->list[$object->getHash()] = [
                    'model' => $object,
                ];
                break;

            // If new, objects will have both a 'model' and a 'last_state',
            case self::_OBJECT_TRACKED:
                Logger::instance()->log('Tracking _OBJECT_TRACKED: ', 1);
                Logger::instance()->log($object);
                $this->list[$object->getHash()] = [
                    'model' => $object,
                    'last_state' => clone $object
                ];
                break;

            // Clean an object out of the $list
            case self::_OBJECT_CLEAN:
                Logger::instance()->log('Untrackig _OBJECT_CLEAN: ', 1);
                Logger::instance()->log($object);
                unset($this->list[$object->getHash()]);
                break;
        }
    }

    /**
     * @param mixed $object
     *
     * @return bool
     */
    public function offsetExists($object)
    {
        return isset($this->list[$object->getHash()]);
    }

    /**
     * Queue up an object for removal when calling flush().
     * These objects will have a 'last_state' but no model.
     *
     * @param mixed $object
     */
    public function offsetUnset($object)
    {
        // If the object exists in the list.
        if (isset($this->list[$object->getHash()])) {
            // If a new object (without a last_state) is being deleted, just delete the entire object.
            if (!isset($this->list[$object->getHash()]['last_state'])) {
                unset($this->list[$object->getHash()]);
            } else {
                unset($this->list[$object->getHash()]['model']);
            }
        }
    }

    /**
     * @param mixed $object
     *
     * @return mixed|null
     */
    public function offsetGet($object)
    {
        return isset($this->list[$object->getHash()]) ? $this->list[$object->getHash()]['model'] : null;
    }

    public function removeFromCollection($obj_hash)
    {
        $this->list[$obj_hash] = [];
    }

    /**
     * Return an array of the objects to be INSERTed.
     */
    public function getPersistedObjects()
    {
        foreach ($this->list as $item) {
            if (isset($item['model']) && !isset($item['last_state'])) {
                yield $item;
            }
        }
    }

    /**
     * Return an array of the objects to be UPDATEd and the changed properties.
     */
    public function getChangedObjects()
    {
        foreach ($this->list as $item) {
            if (isset($item['model']) && isset($item['last_state']) && $item['model'] != $item['last_state']) {
                yield $item;
            }
        }
    }

    /**
     * Return an array of the objects to be DELETEd.
     */
    public function getRemovedObjects()
    {
        foreach ($this->list as $item) {
            if (!isset($item['model']) && isset($item['last_state'])) {
                yield $item;
            }
        }
    }

}
