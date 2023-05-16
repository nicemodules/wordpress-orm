<?php

namespace NiceModules\Tests\ORM\QueryBuilder;

use NiceModules\ORM\QueryBuilder\Where;
use PHPUnit\Framework\TestCase;

class WhereTest extends TestCase
{

    public function testAddWhere()
    {   
        $where = new Where();
        $whereName = new Where();
        $whereId = new Where();
        
        $whereId->addCondition('ID', '3', '=')
            ->addCondition('ID', '7', '=', 'OR');
        
        $whereName->addCondition('name', 'aaa', 'LIKE')
            ->addCondition('name', 'bbb', 'LIKE', 'OR');
        
        $where->addCondition('staus', 'not ok')
            ->addWhere($whereName, 'OR')
            ->addWhere($whereId, 'OR');
        
        print_r(PHP_EOL.'___:'.PHP_EOL);
        print_r($where->build());
        print_r(PHP_EOL);
    }

    public function testAddCondition()
    {
    }

    public function testBuild()
    {
    }
}
