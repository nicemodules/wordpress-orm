<?php

namespace NiceModules\Tests\ORM;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Exceptions\AllowTruncateIsFalseException;
use NiceModules\ORM\Exceptions\AllowSchemaUpdateIsFalseException;
use NiceModules\ORM\Exceptions\PropertyDoesNotExistException;
use NiceModules\ORM\Mapper;
use NiceModules\ORM\Models\Test\Bar;
use NiceModules\ORM\Models\Test\Baz;
use NiceModules\ORM\Models\Test\Foo;

use PHPUnit\Framework\TestCase;


class MapperTest extends TestCase
{
    public function testInstance()
    {
        $mapper = Mapper::instance(Foo::class);
        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    public function testGetPlaceholders()
    {
        $mapper = Mapper::instance(Foo::class);
        $placeholders = $mapper->getPlaceholders();
        $expected = [
            'name' => '%s',
            'bar_ID' => '%d',
            'ID' => '%d',
        ];
        foreach ($expected as $name => $placeholder) {
            $this->assertArrayHasKey($name, $placeholders);
            $this->assertContains($placeholder, $placeholders);
        }
    }

    public function testGetPrefix()
    {
        global $wpdb;
        $this->assertEquals($wpdb->prefix . 'prefix_', Mapper::instance(Foo::class)->getPrefix());
    }

    public function testGetColumn()
    {
        $mapper = Mapper::instance(Foo::class);
        $column = $mapper->getColumn('name');
        $this->assertInstanceOf(Column::class, $column);
        
        try {
            $mapper->getColumn('foo');
        } catch (PropertyDoesNotExistException $e) {
            $this->assertInstanceOf(PropertyDoesNotExistException::class, $e);
            return;
        }
    }

    public function testGetColums()
    {
        $mapper = Mapper::instance(Foo::class);
        $array = $mapper->getColumns();
        $this->assertNotEmpty($array);
    }

    public function testGetClass()
    {
        $mapper = Mapper::instance(Foo::class);
        $class = $mapper->getClass();
        $this->assertEquals(Foo::class, $class);
    }

    public function testGetTable()
    {
        $mapper = Mapper::instance(Foo::class);
        $table = $mapper->getTable();
        $this->assertEquals('foo', $table->name);
        $this->assertInstanceOf(Table::class, $table);
    }

    public function testGetSchemas()
    {
        $mapper = Mapper::instance(Foo::class);
        $array = $mapper->getSchemas();
        $this->assertNotEmpty($array);
    }

    public function testUpdateSchema()
    {
        global $wpdb;
        $mapper = Mapper::instance(Foo::class);
        $mapper->updateSchema();

        $result = $wpdb->get_results('SHOW CREATE TABLE ' . $mapper->getTableName());
        $this->assertNotFalse($result);

        print_r(PHP_EOL . 'SHOW CREATE TABLE query result:' . PHP_EOL);
        print_r($result);
        print_r(PHP_EOL);
    }

    public function testAllowSchemaUpdateIsFalseException()
    {
        $mapper = Mapper::instance(Baz::class);

        try {
            $mapper->updateSchema();
        } catch (AllowSchemaUpdateIsFalseException $e) {
            $this->assertInstanceOf(AllowSchemaUpdateIsFalseException::class, $e);
            $this->assertEquals($e->getErrorMessage(Baz::class), $e->getMessage());
            return;
        }

        $this->fail('Expected exception was not thrown');
    }

    public function testDropTable()
    {
        global $wpdb;
        $mapper = Mapper::instance(Foo::class);
        $mapper->dropTable();
        $result = $wpdb->get_results('SHOW TABLES LIKE "' . $mapper->getTableName() . '"');
        $this->assertEmpty($result);
    }

    public function testAllowDropIsFalseException()
    {
        $mapper = Mapper::instance(Bar::class);
        $mapper->updateSchema();
        try {
            $mapper->dropTable();
        } catch (AllowTruncateIsFalseException $e) {
            $this->assertInstanceOf(AllowTruncateIsFalseException::class, $e);
            $this->assertEquals($e->getErrorMessage(Bar::class), $e->getMessage());
            return;
        }

        $this->fail('Expected exception was not thrown');
    }
}
