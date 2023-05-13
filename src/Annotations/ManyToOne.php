<?php

namespace NiceModules\ORM\Annotations;

/**
 * @Annotation
 */
class ManyToOne
{
    public string $modelName;
    public string $propertyName;
}