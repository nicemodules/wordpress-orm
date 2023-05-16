<?php

namespace NiceModules\ORM\DatabaseAdapters;

use NiceModules\ORM\Mapper;
use PDO;

class PDOAdapter implements DatabaseAdapter
{
    const NAME = 'PDO';
    
    private PDO $pdo;

    /**
     * 
     */
    public function connect()
    {
        /*  * *************** CONFIGURATION DB *************** */
        $dbServer = WORDPRESS_DB_SERVER;
        $dbName = WORDPRESS_DB_USER;
        $dbUser = WORDPRESS_DB_PASS;
        $dbPassword = WORDPRESS_DB_NAME;
        /*  * **************** END OF CONFIGURATION *********** */

        $this->pdo = new mPDO('mysql:host=' . $dbServer . ';dbname=' . $dbName, $dbUser, $dbPassword);
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
    

    public function fetch(string $query, array $values = [])
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