<?php

namespace NiceModules\ORM\Models;

abstract class IdModel extends BaseModel
{

    /**
     * Every model has an ID.
     * @var
     */
    protected $ID;

    /**
     * Getter.
     *
     * @return string
     */
    public function getId()
    {
        return $this->ID;
    }

}