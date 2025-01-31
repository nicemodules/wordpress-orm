<?php

namespace NiceModules\ORM\Models\Test;


use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Index;
use NiceModules\ORM\Annotations\ManyToOne;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="foo",
 *     allow_schema_update=true,
 *     allow_drop=true,
 *     prefix="prefix",
 *     indexes={@Index(name="name_index", columns={"name"})},
 *     repository="NiceModules\ORM\Repositories\Test\FooRepository",
 *     column_order={"ID", "date_add", "date_update"},
 *     inherits="NiceModules\ORM\Models\BaseModel"
 *     )
 */
class Foo extends BaseModel
{
    /**
     * @Column(type="datetime", null ="NULL")
     */
    protected string $date_add;

    /**
     * @Column(type="timestamp", null="NULL", default="CURRENT_TIMESTAMP")
     */
    protected string $date_update;

    /**
     * @Column(
     *     type="int",
     *     length="10",
     *     null="NOT NULL",
     *     many_to_one=@ManyToOne(modelName="NiceModules\ORM\Models\Test\Bar", propertyName="ID", onDelete="CASCADE")
     *     )
     */
    protected int $bar_ID;

    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected string $name;

    /**
     * @Column(type="varchar", length="50")
     * @var string
     */
    protected string $description;

    public function beforeSave()
    {
        parent::beforeSave();

        if (!$this->hasId()) { // is object new?
            $this->date_add = date('Y-m-d H:i:s');
        }
    }

}