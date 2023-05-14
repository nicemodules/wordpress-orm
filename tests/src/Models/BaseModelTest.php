<?php

namespace NiceModules\Tests\ORM\Models;

use NiceModules\ORM\Mapper;
use NiceModules\ORM\Models\BaseModel;
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

    public function testGetRaw()
    {
        $foo = new Foo();
        $foo->set('bar_ID', 1);
        $this->assertEquals(1, $foo->getRaw('bar_ID'));
    }

    public function testGetAllUnkeyedValues()
    {
        $foo = new Foo();
        $foo->set('name', 'foo');
        $values = $foo->getAllUnkeyedValues();

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
        $this->assertIsArray($foo->getColumns());
        $this->assertNotEmpty($foo->getColumns());
    }

    public function testGetHash()
    {
        $foo = new Foo();
        $this->assertIsString($foo->getHash());
    }
}
