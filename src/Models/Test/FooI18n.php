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
 *     name="foo_i18n",
 *     allow_schema_update=true,
 *     allow_drop=true,
 *     prefix="prefix",
 *     indexes={@Index(name="language_index",  columns={"language"})},
 *     repository="NiceModules\ORM\Repositories\Test\FooI18nRepository",
 *     )
 */
class FooI18n extends BaseModel
{
    /**
     * @Column(
     *     type="int",
     *     length="10",
     *     null="NOT NULL",
     *     many_to_one=@ManyToOne(modelName="NiceModules\ORM\Models\Test\Foo", propertyName="ID", onDelete="CASCADE")
     *     )
     */
    protected int $object_id;

    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected string $language;
    
    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected string $name;

    /**
     * @Column(type="varchar", length="50", i18n=true)
     * @var string
     */
    protected string $description;
}