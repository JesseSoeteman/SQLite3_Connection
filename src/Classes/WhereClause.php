<?php

namespace SQLite3_Connection\Classes;

use SQLite3_Connection\Classes\ParamBindObject;
use SQLite3_Connection\Statics\OPERATOR;

class WhereClause
{
    private string $column;
    private mixed $value;
    private OPERATOR $operator;
    private array $boundParams = [];

    public function __construct(string $column, OPERATOR $operator = OPERATOR::EQUALS, mixed $value = null)
    {
        $this->column = $column;
        $this->value = $value;
        $this->operator = $operator;

        switch ($this->operator) {
            case OPERATOR::IS_NULL:
            case OPERATOR::IS_NOT_NULL:
                $this->value = "";
                break;
            case OPERATOR::EQUALS:
            case OPERATOR::NOT_EQUALS:
            case OPERATOR::GREATER_THAN:
            case OPERATOR::GREATER_THAN_OR_EQUAL_TO:
            case OPERATOR::LESS_THAN:
            case OPERATOR::LESS_THAN_OR_EQUAL_TO:
            case OPERATOR::LIKE:
            case OPERATOR::NOT_LIKE:
                $this->value = " " . "`" . "::__" . $this->column . "`";
                $this->boundParams = [new ParamBindObject("::__" . $this->column, $this->value)];
                break;
            case OPERATOR::IN:
            case OPERATOR::NOT_IN:
                if (!is_array($this->value)) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array.");
                }
                $this->value = " (" . implode(", ", array_map(function ($value, $index) {
                    $this->boundParams[] = new ParamBindObject("::" . str_repeat(":", $index) . "__" . $this->column, $value);
                    return "`" . "::" . str_repeat(":", $index) . "__" . $this->column . "`";
                }, $this->value)) . ")";
                break;
            case OPERATOR::BETWEEN:
            case OPERATOR::NOT_BETWEEN:
                if (!is_array($this->value)) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array.");
                }
                if (count($this->value) !== 2) {
                    throw new \Exception("The value for the '" . $this->operator . "' operator must be an array with 2 values.");
                }
                $this->value = " " . implode(" AND ", array_map(function ($value, $index) {
                    $this->boundParams[] = new ParamBindObject("::" . str_repeat(":", $index) . "__" . $this->column, $value);
                    return "`" . "::" . str_repeat(":", $index) . "__" . $this->column . "`";
                }, $this->value));
                break;
            default:
                throw new \Exception("The operator '" . $this->operator . "' is not supported.");
                break;
        }
    }

    public function getClause(): string
    {
        return $this->column . " " . $this->operator . $this->value;
    }

    public function getBoundParams(): array
    {
        return $this->boundParams;
    }
}
