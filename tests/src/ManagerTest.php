<?php

namespace NiceModules\Tests\ORM;

use NiceModules\ORM\Manager;
use NiceModules\ORM\Mapper;
use NiceModules\ORM\Models\Test\Bar;
use NiceModules\ORM\Models\Test\Foo;
use NiceModules\ORM\Repositories\Test\FooRepository;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{

    public function testInstance()
    {
        $manager = Manager::instance();
        $this->assertInstanceOf(Manager::class, $manager);
    }

    public function testGetRepository()
    {
        $repo = Manager::instance()->getRepository(Foo::class);
        $this->assertInstanceOf(FooRepository::class, $repo);
    }

    public function testPersist()
    {
        Mapper::instance(Bar::class)->updateSchema();

        $unique = uniqid('u', true);

        $bars = [];
        for ($i = 0; $i < 10; $i++) {
            $bar = new Bar();
            $bar->set('name', $unique . '-' . $i);

            $bars[] = $bar;
            
            Manager::instance()->persist($bar);
        }

        Manager::instance()->flush();

        $bars = Manager::instance()->getRepository(Bar::class);
    }

    public function testRemove()
    {
    }

    public function testTrack()
    {
    }

    public function testClean()
    {
    }

    public function testFlush()
    {
    }
}
