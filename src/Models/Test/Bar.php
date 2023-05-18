<?php

namespace NiceModules\ORM\Models\Test;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="bar",
 *     allow_schema_update=true,
 *     allow_drop=false,
 *     repository="NiceModules\ORM\Repositories\Test\BarRepository",
 *     inherits="NiceModules\ORM\Models\BaseModel"
 *     )
 */
class Bar extends BaseModel
{
    /**
     * @Column(type="varchar", length="100")
     * @var string
     */
    protected string $name = '';
}