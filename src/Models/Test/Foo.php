<?php

namespace NiceModules\ORM\Models\Test;


use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Annotations\Index;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="foo",
 *     allow_schema_update=true,
 *     allow_drop=true,
 *     prefix="prefix",
 *     indexes={@Index(name="name_index", columns={"name"})},
 *     repository="NiceModules\ORM\Repositories\FooRepository",
 *     inherits="NiceModules\ORM\Models\BaseModel"
 *     )
 */
class Foo extends BaseModel
{
    /**
     * @Column(type="datetime", null ="NOT NULL")
     */
    protected string $date_add;

    /**
     * @Column(type="timestamp", null="NOT NULL")
     */
    protected string $date_update;
    
    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected  string $name;

    /**
     * @Column(
     *     type="bigint", 
     *     length="20", 
     *     null="NOT NULL", 
     *     type="bigint", 
     *     length="20"
     * )
     */
    protected $bar_ID;
    
}