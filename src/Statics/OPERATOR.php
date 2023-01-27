<?php

namespace SQLite3_Connection\Statics;

abstract class OPERATOR {
    const EQUALS = '=';
    const NOT_EQUALS = '!=';
    const GREATER_THAN = '>';
    const GREATER_THAN_OR_EQUAL_TO = '>=';
    const LESS_THAN = '<';
    const LESS_THAN_OR_EQUAL_TO = '<=';
    const LIKE = 'LIKE';
    const NOT_LIKE = 'NOT LIKE';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';
    const BETWEEN = 'BETWEEN';
    const NOT_BETWEEN = 'NOT BETWEEN';
    const IS_NULL = 'IS NULL';
    const IS_NOT_NULL = 'IS NOT NULL';
}