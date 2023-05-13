<?php

namespace NiceModules\ORM\Models\Test;

use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="bar",
 *     allow_schema_update=true,
 *     allow_drop=false,
 *     )
 */
class Bar extends BaseModel
{
    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected  string $name;
}