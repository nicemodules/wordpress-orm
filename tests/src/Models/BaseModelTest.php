<?php

namespace NiceModules\Tests\ORM\Models;

use Cassandra\Map;
use NiceModules\ORM\Exceptions\RequiredAnnotationMissingException;
use NiceModules\ORM\Manager;
use NiceModules\ORM\Mapper;
use NiceModules\ORM\Models\BaseModel;
use NiceModules\ORM\Models\Test\Bar;
use NiceModules\ORM\Models\Test\Foo;
use PHPUnit\Framework\TestCase;

class BaseModelTest extends TestCase
{
    public function test__construct()
    {
        $foo = new Foo();
        $this->assertNotEmpty($foo->getHash());
        $this->assertInstanceOf(Foo::class, $foo);
    }

    /**
     * @covers NiceModules\ORM\Models\BaseModel::set
     * @covers NiceModules\ORM\Models\BaseModel::get
     */
    public function testGet()
    {
        $foo = new Foo();
        $foo->set('ID', 1);
        $this->assertEquals(1, $foo->get('ID'));
    }

    public function test__clone()
    {
        $foo = new Foo();
        $foo->set('name', 'FooBar');

        $fooClone = clone $foo;

        $this->assertEquals($foo->get('name'), $fooClone->get('name'));
    }

    public function testGetTableName()
    {
        $foo = new Foo();
        $table = $foo->getTableName();
        $this->assertEquals(Mapper::instance(Foo::class)->getTableName(), $table);
    }

    public function testGetAllValues()
    {
        $foo = new Foo();

        $foo->set('ID', 6);
        $foo->set('name', 'FooBar');

        $valuesArray = $foo->getAllValues();

        $this->assertArrayHasKey('name', $valuesArray);
        $this->assertArrayHasKey('ID', $valuesArray);
        $this->assertContains(6, $valuesArray);
        $this->assertContains('FooBar', $valuesArray);
    }

    public function testGetPlaceholders()
    {
        $foo = new Foo();
        $foo->set('ID', 100);
        $this->assertEquals(Mapper::instance(Foo::class)->getPlaceholders(), $foo->getPlaceholders());
    }

    public function testGetMultiple()
    {
        $foo = new Foo();

        $values = [
            'ID' => 6,
            'name' => 'FooBar',
        ];

        foreach ($values as $name => $value) {
            $foo->set($name, $value);
        }

        $result = $foo->getMultiple(array_keys($values));

        $this->assertEquals($values, $result);
    }


    /**
     * @covers NiceModules\ORM\Models\BaseModel::setObjectRelatedBy
     */
    public function testGetObjectRelatedBy()
    {
        // create bar table if not exsist
        Mapper::instance(Bar::class)->updateSchema();
        Mapper::instance(Foo::class)->updateSchema();
        
        $orm = Manager::instance();
        
        $bar = new Bar();
        $bar->set('name', 'Foo bar');
        $orm->persist($bar);

        $orm->flush();
        
        $foo = new Foo();
        $foo->setObjectRelatedBy('bar_ID', $bar);
        $orm->persist($foo);

        $orm->flush();

        // query db for object
        /** @var Foo $fooFromDb */
        $fooFromDb = $orm->getRepository(Foo::class)->find($foo->getId());
        
        $this->assertEquals($bar->getId(), $fooFromDb->getObjectRelatedBy('bar_ID')->getId());
        $orm->clean($fooFromDb);
        Mapper::instance(Foo::class)->dropTable();
    }

    public function testGetAllUnkeyedValues()
    {
        $foo = new Foo();
        $foo->set('name', 'foo');
        $values = $foo->getAllUpdateValues();

        $this->assertEquals($values[2], 'foo');
    }

    public function testGetId()
    {
        $foo = new Foo();
        $foo->set('ID', 1);
        $this->assertEquals(1, $foo->getId());
    }

    public function testGetColumns()
    {
        $foo = new Foo();
        $this->assertIsArray($foo->getUpdateColumns());
        $this->assertNotEmpty($foo->getUpdateColumns());
    }

    public function testGetHash()
    {
        $foo = new Foo();
        $this->assertIsString($foo->getHash());
    }
}
