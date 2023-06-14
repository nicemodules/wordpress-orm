<?php

namespace NiceModules\ORM\Annotations;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

/**
 * @Annotation
 */
class Table
{
    public string $type;
    public string $name;
    public bool $allow_schema_update = false;
    public bool $allow_drop = false;
    public string $prefix;
    /**
     * @var Index[]
     */
    public array $indexes;
    public string $inherits;
    public string $repository;
    public array $column_order;
    public bool $i18n = false;

    public function __construct(array $values)
    {
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }

        foreach ($values as $name => $value) {
            switch ($name) {
                case 'inherits':
                    {
                        $inheritedReflectionClass = new ReflectionClass($value);
                        $reader = new AnnotationReader();
                        $parentAnnotations = $reader->getClassAnnotation($inheritedReflectionClass, self::class);
                        $thisReflectionClass = new ReflectionClass($this);

                        if ($parentAnnotations) {
                            foreach ($thisReflectionClass->getProperties() as $property) {
                                $propertyName = $property->getName();

                                if (isset($parentAnnotations->$propertyName) && !isset($this->$propertyName)) {
                                    $this->$propertyName = $parentAnnotations->$propertyName;
                                }
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }
    }
}