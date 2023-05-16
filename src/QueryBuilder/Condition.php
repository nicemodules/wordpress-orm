<?php
namespace NiceModules\ORM\QueryBuilder;

class Condition
{
    protected string $column;
    protected $value;
    protected string $compsrsion;

    /**
     * @param string $column
     * @param $value
     * @param string $compsrsion
     */
    public function __construct(string $column, $value, string $compsrsion = '=')
    {
        $this->column = $column;
        $this->value = $value;
        $this->compsrsion = $compsrsion;
    }

    /**
     * @return string
     * @throws PropelException
     */
    public function build(): string
    {
        return $this->column . ' ' . $this->compsrsion . $this->getValue($this->value);
    }

    /**
     * @param $value
     * @return string
     */
    protected function getValue($value): string
    {
        if ($value === null) {
            $value = '';
        } elseif ($value === '') {
            $value = "''";
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        } elseif (!is_numeric($value)) {
            $value = "'" . $value . "'";
        }
        return $value !== '' ? ' ' . $value : $value;
    }

}