<?php

namespace NiceModules\ORM\DatabaseAdapters;

use NiceModules\ORM\Mapper;

interface DatabaseAdapter
{
    public function connect();

    public function disconnect();

    public function execute(string $query, array $values = []);
    
    public function fetch(string $query, array $values = []);
    
    public function fetchRow(string $query, array $values = []);

    public function getPrefix(): string;

    public function getCharsetCollate(): string;

    public function updateSchema(Mapper $mapper);
    
    public function escape($value);
}