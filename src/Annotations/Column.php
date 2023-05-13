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
    public string $join_property;
    public string $many_to_one;
}