<?php

namespace NiceModules\ORM\Annotations;

use Doctrine\Common\Annotations\AnnotationReader;

/**
 * @Annotation
 */
class Column
{
    public string $type;
    public string $length;
    public string $null;
    public bool $primary;
    public bool $auto_increment;
    public string $default;
    public ManyToOne $many_to_one;
    public bool $allowUpdate = true;


    public function __construct(array $values)
    {
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }
        
        if($this->type === 'timestamp'){
            $this->allowUpdate = false;
        }
    }
}