<?php

namespace NiceModules\ORM\Models;

use NiceModules\ORM\Manager;
use NiceModules\ORM\Mapping;

abstract class IdModel extends BaseModel {

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
  public function getId() {
    return $this->ID;
  }

}