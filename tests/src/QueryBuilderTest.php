<?php

namespace NiceModules\Tests\ORM;

use NiceModules\ORM\Manager;
use NiceModules\ORM\Models\Test\Bar;
use NiceModules\ORM\Models\Test\Foo;
use NiceModules\ORM\QueryBuilder\Where;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    public function testWhere()
    {
        $unique = uniqid('u', true);
        $number = 10;

        $bars = [];

        for ($i = 1; $i < $number; $i++) {
            $bar = new Bar();
            $bar->set('name', $unique . '-' . $i);
            $bars[] = $bar;
            Manager::instance()->persist($bar);
        }

        Manager::instance()->flush();

        $queryBuilder = Manager::instance()
            ->getRepository(Bar::class)
            ->createQueryBuilder()
            ->where('name', '%-1%', 'LIKE')
            ->where('name', '%-2%', 'LIKE', 'OR')
            ->where('name', '%-%', 'LIKE');

        $where57 = new Where($queryBuilder);
        $where57->addCondition(Bar::class, 'name', '%-5%', 'LIKE', 'OR')
            ->addCondition(Bar::class,'name', '%-7%', 'LIKE', 'OR');

        $whereU = new Where($queryBuilder);
        $whereU->addCondition(Bar::class,'name', '%u%', 'LIKE');
        $whereU->addCondition(Bar::class,'name', [1, 2, 3], 'NOT IN', 'OR');

        $queryBuilder->getWhere()->addWhere($where57, 'OR');
        $queryBuilder->getWhere()->addWhere($whereU);
        $queryBuilder->buildQuery();

        $q = $queryBuilder->getQuery();

        $v = $queryBuilder->getWhereValues();

        print_r(PHP_EOL . '___WHERE QUERY EXAMPLE:' . PHP_EOL);
        print_r($q);
        print_r(PHP_EOL);

        print_r(PHP_EOL . '___WHERE QUERY VALUES:' . PHP_EOL);
        print_r($v);
        print_r(PHP_EOL);

        $result = $queryBuilder->getResult();

        $this->assertEquals(4, count($result));

        foreach ($bars as $bar) {
            Manager::instance()->remove($bar);
        }

        Manager::instance()->flush();
    }
}
