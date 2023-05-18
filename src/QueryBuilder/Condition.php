<?php

namespace NiceModules\ORM\QueryBuilder;

interface Condition
{
    public function getOperator() : string;
    public function build();
}