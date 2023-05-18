<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\ManyToOne;
use NiceModules\ORM\Annotations\Table;

/**
 * @Table(
 *     type="Entity",
 *     name="posts",
 *     allow_schema_update=false,
 *     repository="\NiceModules\ORM\Repositories\PostsRepository"
 *     )
 */
class Posts extends IdModel
{

    /**
     * @Column(type="bigint", length="20", null="NOT NULL", primary=true)
     */
    protected int $ID;

    /**
     * @Column(
     *     type="bigint",
     *     length="20",
     *     null="NOT NULL",
     *     many_to_one=@ManyToOne(modelName="\NiceModules\ORM\Models\Users", propertyName="ID"),
     *     join_property="ID"
     *     )
     */
    protected $post_author;

    /**
     * @Column(type="datetime", null="NOT NULL")
     */
    protected $post_date;

    /**
     * @Column(type="datetime", null="NOT NULL")
     */
    protected $post_date_gmt;

    /**
     * @Column(type="longtext", null="NOT NULL")
     */
    protected $post_content;

    /**
     * @Column(type="text", null="NOT NULL")
     */
    protected $post_title;

    /**
     * @Column(type="text", null="NOT NULL")
     */
    protected $post_excerpt;

    /**
     * @Column(type="varchar", length="20", null="NOT NULL")
     */
    protected $post_status;

    /**
     * @Column(type="varchar", length="20", null="NOT NULL")
     */
    protected $comment_status;

    /**
     * @Column(type="varchar", length="20", null="NOT NULL")
     */
    protected $ping_status;

    /**
     * @Column(type="varchar", length="255", null="NOT NULL")
     */
    protected $post_password;

    /**
     * @Column(type="varchar", length="255", null="NOT NULL")
     */
    protected $post_name;

    /**
     * @Column(type="text", null="NOT NULL")
     */
    protected $to_ping;

    /**
     * @Column(type="text", null="NOT NULL")
     */
    protected $pinged;

    /**
     * @Column(type="datetime", null="NOT NULL")
     */
    protected $post_modified;

    /**
     * @Column(type="datetime", null="NOT NULL")
     */
    protected $post_modified_gmt;

    /**
     * @Column(type="longtext", null="NOT NULL")
     */
    protected $post_content_filtered;

    /**
     * @Column(type="bigint", length="20", null="NOT NULL")
     */
    protected $post_parent;

    /**
     * @Column(type="varchar", length="255", null="NOT NULL")
     */
    protected $guid;

    /**
     * @Column(type="int", length="11", null="NOT NULL")
     */
    protected $menu_order;

    /**
     * @Column(type="varchar", length="20", null="NOT NULL")
     */
    protected $post_type;

    /**
     * @Column(type="varchar", length="100", null="NOT NULL")
     */
    protected $post_mime_type;

    /**
     * @Column(type="bigint", length="20", null="NOT NULL")
     */
    protected $comment_count;

}
