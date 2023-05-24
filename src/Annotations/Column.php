<?php

namespace NiceModules\ORM\Annotations;


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
    public bool $allow_update = true;


    public function __construct(array $values)
    {
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }
        
        if($this->type === 'timestamp'){
            $this->allow_update = false;
        }
    }
}