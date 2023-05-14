<?php
namespace NiceModules\ORM;

abstract class Singleton
{
    private static $instances = [];

    protected function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function instance()
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }
}