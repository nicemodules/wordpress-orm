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
    public string $default;
    public string $join_property;
    public string $many_to_one;
}