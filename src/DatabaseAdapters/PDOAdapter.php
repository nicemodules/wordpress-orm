<?php

namespace NiceModules\ORM\DatabaseAdapters;

use NiceModules\ORM\Mapper;
use PDO;

class PDOAdapter implements DatabaseAdapter
{
    const NAME = 'PDO';
    
    private PDO $pdo;

    /**
     * TODO: figure out podo database configuration 
     */
    public function connect()
    {
        $dsn = 'mysql:host=localhost;dbname=my_database';
        $username = 'root';
        $password = '';

        $this->pdo = new PDO($dsn, $username, $password);
    }

    public function disconnect()
    {
        $this->pdo = null;
    }

    public function execute($query, $values = array())
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    public function executeTransaction(array $queries)
    {
        array_push($queries, 'START TRANSACTION;');
        array_unshift($queries, 'COMMIT;');

        return $this->execute(implode(PHP_EOL, $queries));
    }

    public function fetch($query, array $values = [])
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($values);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    public function getPrefix(): string
    {
        // TODO: Implement getPrefix() method.
        return '';
    }

    public function getCharsetCollate(): string
    {
        // TODO: Implement getCharsetCollate() method.
        return '';
    }

    public function updateSchema(Mapper $mapper)
    {
        // TODO: Implement updateTable() method.
    }

    public function escape($value)
    {
        return $this->pdo->quote($value);
    }
}