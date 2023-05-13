<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Table;

/**
 * @Table(type="Entity", 
 *     name="users", 
 *     allow_schema_update=false, 
 *     repository="\NiceModules\ORM\Repositories\UsersRepository"
 * )
 */
class Users extends IdModel
{

    /**
     * @Column(type="bigint", length="20", null="NOT NULL", primary=true)
     */
    protected int $ID;
    
    /**
     * @Column(type="varchar", length="60", null="NOT NULL");
     */
    protected $user_login;

    /**
     * @Column(type="varchar", length="255", null="NOT NULL");
     */
    protected $user_pass;

    /**
     * @Column(type="varchar", length="50", null="NOT NULL");
     */
    protected $user_nicename;

    /**
     * @Column(type="varchar", length="100", null="NOT NULL");
     */
    protected $user_email;

    /**
     * @Column(type="varchar", length="100", null="NOT NULL");
     */
    protected $user_url;

    /**
     * @Column(type="datetime", null="NOT NULL");
     */
    protected $user_registered;

    /**
     * @Column(type="varchar", length="255", null="NOT NULL");
     */
    protected $user_activation_key;

    /**
     * @Column(type="int", null="NOT NULL");
     */
    protected $user_status;

    /**
     * @Column(type="varchar", length="250", null="NOT NULL");
     */
    protected $display_name;

}
