<?php

namespace NiceModules\ORM\Annotations;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

/**
 * @Annotation
 */
class Table
{
    public bool $allow_schema_update;
    public string $type;
    public string $name;
    public string $prefix;
    public Index $index;
    public string $inherits;
    public string $repository;
    
    public function __construct(array $values)
    {
        foreach ($values as $name => $value){
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
                                
                                if (isset($parentAnnotations->$propertyName)) {
                                    $this->$propertyName = $parentAnnotations->$propertyName;
                                }
                            }
                        }
                    }
                    break;
                default:
                    {
                        $this->$name = $value;
                    }
                    break;
            }
        }
    }
}