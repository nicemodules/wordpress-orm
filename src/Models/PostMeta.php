<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;

/**
 * @Table(
 *     type="Entity",
 *     name="postmeta",
 *     allow_schema_update=false,
 * )
 */
class PostMeta extends BaseModel
{
    /**
     * @Column(type="bigint", length="20", null="NOT NULL");
     */
    protected $meta_id;

    /**
     * @Column(type="bigint", length="20", null="NOT NULL");
     */
    protected $post_id;

    /**
     * @Column(type="varchar", length="255");
     */
    protected $meta_key;

    /**
     * @Column(type="longtext");
     */
    protected $meta_value;


}
