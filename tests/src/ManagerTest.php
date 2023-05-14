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
        
        $bar = new Bar();
        $bar->set('name', 'Foo');


        $bar2 = new Bar();
        $bar2->set('name', 'Bar');
        
        Manager::instance()->persist($bar);
        Manager::instance()->persist($bar2);
        Manager::instance()->flush();
        

        $results = Manager::instance()
            ->getRepository(Bar::class)
            ->findAll();
        
        print_r(PHP_EOL.'___:'.PHP_EOL);
        print_r($results);
        print_r(PHP_EOL); 
        
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
