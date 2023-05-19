<?php

namespace NiceModules\ORM\Annotations;

/**
 * @Annotation
 */
class ManyToOne
{
    public string $modelName;
    public string $propertyName;
    public string $onDelete = 'SET NULL';
}