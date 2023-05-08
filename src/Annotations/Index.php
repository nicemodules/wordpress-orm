<?php

namespace NiceModules\ORM\Annotations;

/**
 * @Annotation
 */
class Index
{
    public string $name;
    public array $columns;
}