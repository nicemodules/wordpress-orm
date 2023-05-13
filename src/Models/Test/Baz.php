<?php

namespace NiceModules\ORM\Models\Test;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="bar",
 *     allow_schema_update=false,
 *     )
 */
class Baz extends BaseModel
{
    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected  string $name;
}


