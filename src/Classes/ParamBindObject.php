<?php

/**
 * ParamBindObject
 * 
 * This class is used to bind parameters to a query.
 * 
 * @author  Jesse Soeteman
 * @version 1.0.0
 * @since   29-12-2022
 */
class ParamBindObject
{
    /**
     * @var string $param The parameter to bind.
     */
    public $param;
    /**
     * @var string $value The value to bind to the parameter.
     */
    public $value;
    /**
     * @var int $type The type of the value.
     */
    public $type;
    /**
     * @var int $idCount A counter to keep track of how many characters are used to bind the parameter.
     */
    public $idCount;

    /**
     * Constructor
     * 
     * @param string $param The parameter to bind.
     * @param string $value The value to bind to the parameter.
     * @param int $idCount A counter to keep track of how many characters are used to bind the parameter.
     */
    public function __construct($param, $value, $idCount = 1)
    {
        $this->param = $param;
        $this->idCount = $idCount;

        $escapedValue = SQLite3::escapeString($value);
        $this->value = $escapedValue;

        $type = gettype($escapedValue);

        switch ($type) {
            case "boolean":
                $this->type = SQLITE3_INTEGER;
                break;
            case "integer":
                $this->type = SQLITE3_INTEGER;
                break;
            case "float":
                $this->type = SQLITE3_FLOAT;
                break;
            case "string":
                $this->type = SQLITE3_TEXT;
                break;
            default:
                $this->type = null;
                break;
        }
    }
}
