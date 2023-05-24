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

    /**
     * 
     */
    public function testPersist()
    {
        Mapper::instance(Bar::class)->updateSchema();
        Mapper::instance(Foo::class)->updateSchema();

        $bars = Manager::instance()->getRepository(Bar::class)->findAll();
        
        foreach ($bars as $bar){
            Manager::instance()->remove($bar);
        }

        Manager::instance()->flush();
        
        $unique = uniqid('B', true);
        $number = 10;

        $bars = [];
        for ($i = 0; $i < $number; $i++) {
            $bar = new Bar();
            $bar->set('name', $unique . '-' . $i);
            $bars[] = $bar;
            Manager::instance()->persist($bar);
        }

        Manager::instance()->flush();
        
        /** @var Bar $bars */
        foreach ($bars as $bar){
            $foo = new Foo();
            $foo->set('name', 'FOO BAR '.$bar->get('name'));
            $foo->set('bar_ID', $bar->getId());
            Manager::instance()->persist($foo);
        }

        Manager::instance()->flush();

        $barsRepository = Manager::instance()->getRepository(Bar::class);
        $bars = $barsRepository->findAll();

        /** @var Foo[] $foos */
        $foos = Manager::instance()->getRepository(Foo::class)->findAll();
        
        $this->assertEquals($number, count($bars));
        $this->assertEquals($number, count($foos));
    }

    public function testRemove()
    {
        $bars = Manager::instance()->getRepository(Bar::class)->findAll();

        foreach ($bars as $bar) {
            $this->assertInstanceOf(Bar::class, $bar);
            Manager::instance()->remove($bar);
        }

        Manager::instance()->flush();

        $barsRepository = Manager::instance()->getRepository(Bar::class);
        $bars = $barsRepository->findAll();
        $foos = Manager::instance()->getRepository(Foo::class)->findAll();
        
        $this->assertEquals(0, count($bars));
        $this->assertEquals(0, count($foos));
    }

    public function testTrack()
    {
        $bar = new Bar();
        Manager::instance()->track($bar);
        $bar->set('name', 'foobar');
        Manager::instance()->flush();

        $bar = Manager::instance()
            ->getRepository(Bar::class)
            ->createQueryBuilder()->where('name', 'foobar')
            ->buildQuery()
            ->getSingleResult();

        $this->assertEquals($bar->get('name'), 'foobar');

        Manager::instance()->remove($bar);
        Manager::instance()->flush();
    }

    public function testClean()
    {
        $bar = new Bar();
        $bar->set('name', 'foo bar bar');
        Manager::instance()->track($bar);
        Manager::instance()->flush();

        $bar->set('name', 'foo');
        Manager::instance()->clean($bar);
        Manager::instance()->flush();

        $changedBar = Manager::instance()
            ->getRepository(Bar::class)
            ->createQueryBuilder()->where('name', 'foo')
            ->buildQuery()
            ->getResult();

        $this->assertEmpty($changedBar);

        Manager::instance()->remove($bar);
        Manager::instance()->flush();
    }
}
