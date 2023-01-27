<?php

namespace SQLite3_Connection;

use SQLite3;
use Exception;

use SQLite3_Connection\Classes\ParamBindObject;
use SQLite3_Connection\Classes\WhereClause;
use SQLite3_Connection\Statics\OPERATOR;

/**
 * SQLite3_Connection
 * 
 * This class is used to connect to a SQLite3 database. It contains methods to execute queries and statements.
 * This class can throw exceptions be sure to catch them.
 * 
 * @author  Jesse Soeteman
 * @version 1.0.0
 * @since   29-12-2022
 */
class SQLite3_Connection
{
    /**
     * @var string $path The path to the database file.
     */
    private $path;
    /**
     * @var string $filename The name of the database file.
     */
    private $filename;
    /**
     * @var SQLite3 $db The SQLite3 database connection.
     */
    private $db;

    /**
     * Constructor
     * 
     * @param string $path The path to the database file.
     * @param string $filename The name of the database file.
     */
    public function __construct($path, $filename)
    {
        $this->path = $this->checkError($this->checkIfNull($path, "Database path not specified."));
        $this->filename = $this->checkError($this->checkIfNull($filename, "Database name not specified."));

        try {
            $this->db = new SQLite3($path . $filename);
            $this->db->busyTimeout(5000);
        } catch (Exception $e) {
            $this->checkError([false, "Data base connection failed: " . $e->getMessage()]);
        }
    }

    /**
     * Select Query, returns an array of rows.
     *  
     * @param string $table The table to select from.
     * @param array $columns The columns to select.
     * @param WhereClause $where The where clause to use.
     * 
     * @return array The rows that were selected.
     * 
     * @throws Exception
     */
    public function select(string $table, array $columns, WhereClause $where = null): array
    {
        $this->checkTableAndColumns($table, $columns);

        $sql = "SELECT " . implode(" ,", $columns) . " FROM {$table}";

        $params = [];
        if ($where != null) {
            $sql .= " WHERE " . $where->getClause();
            $params = $where->getBoundParams();
        }
        
        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        $returnArray = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $returnArray[] = $row;
        }

        return $this->checkError([true, $returnArray]);
    }

    /**
     * Insert Query, inserts a row into the database.
     * 
     * @param string $table The table to insert into.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     * 
     */
    public function insert($table, array $params)
    {
        $this->checkTableAndColumns($table, array_map(function ($param) {
            if (!$param instanceof ParamBindObject) {
                $this->checkError([false, "ParamBindObject expected."]);
            }
            return $param->param;
        }, $params));

        $sql = "INSERT INTO {$table} (" . implode(" ,", array_map(function ($param) {
            return $param->param;
        }, $params)) . ") VALUES (" . implode(" ,", array_map(function ($param) {
            return str_repeat(":", $param->idCount) . $param->param;
        }, $params)) . ")";

        $result = $this->executeStatement($sql, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        return $this->checkError([true, $result]);
    }

    /**
     * Update
     * 
     * Executes an update query on the database.
     * 
     * @param string $table The table to update.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     */
    public function update($table = "", $params = [], $where = [])
    {
        $tableExists = $this->executeStatement("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name;", [new ParamBindObject(":table_name", $table)]);

        if ($tableExists == false || $tableExists->fetchArray() == false) {
            $this->checkError([false, "Table does not exist."]);
        }

        $sqlStatementData = $this->getStatementString($params, true, true);
        $sqlConditionData = $this->getStatementString($where, true, true);

        $sql = "UPDATE {$table} SET {$sqlStatementData} WHERE {$sqlConditionData};";

        $params = array_merge($params, $where);

        $result = $this->executeStatement($sql, $params);
        return $this->checkError([true, $result]);
    }

    /**
     * Delete
     * 
     * Executes a delete query on the database.
     * 
     * @param string $table The table to delete from.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     */
    public function delete($table = "", $where = [])
    {
        $tableExists = $this->executeStatement("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name;", [new ParamBindObject(":table_name", $table)]);

        if ($tableExists == false || $tableExists->fetchArray() == false) {
            $this->checkError([false, "Table does not exist."]);
        }

        $sqlConditionData = $this->getStatementString($where, true, true);

        $sql = "DELETE FROM {$table} WHERE {$sqlConditionData};";

        $result = $this->executeStatement($sql, $where);
        return $this->checkError([true, $result]);
    }

    /**
     * Execute Statement
     * 
     * Executes a statement on the database. With this function you can execute any query.
     * 
     * @param string $query The query to execute.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     */
    public function executeStatement($query = "", $params = [])
    {
        $stmt = null;

        if (!$stmt = $this->db->prepare($query)) {
            $this->checkError([false, "Failed to prepare statement."]);
        }

        foreach ($params as &$param) {
            $idCountString = str_repeat(":", $param->idCount);
            $stmt->bindValue($idCountString . $param->param, $param->value, $param->type);
            if (!$stmt) {
                $this->checkError([false, "Failed to bind value."]);
            }
        }

        $result = $stmt->execute();

        if (!$result) {
            $this->checkError([false, "Failed to execute statement."]);
        }

        return $result;
    }

    /**
     * Get Statement String
     * 
     * Returns a string for a statement.
     * 
     * if $updateString is true the result will be like this: "column1 = :column1, column2 = :column2"
     * if $updateString is false the result will be like this: "(:column1, :column2)" and like this if $withColon is false: "(column1, column2)"
     * 
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     * @param bool $withColon If the string should contain a colon.
     * @param bool $updateString If the string should be used for an update statement.
     * 
     * @return string The string for the statement.
     */
    private function getStatementString(array $params = [], bool $withColon = false, bool $updateString = false): string
    {
        if (!$updateString) {
            $string = "(";
            for ($i = 0; $i < count($params); $i++) {
                if ($withColon) {
                    $string .= $params[$i]->param;
                } else {
                    $string .= str_repeat(":", $params[$i]->idCount) . $params[$i]->param;
                }
                if ($i < count($params) - 1) {
                    $string .= ", ";
                }
            }
            $string .= ")";
            return $string;
        }

        $string = "";
        for ($i = 0; $i < count($params); $i++) {
            $string .= $params[$i]->param . " = " . str_repeat(":", $params[$i]->idCount) . $params[$i]->param;
            if ($i < count($params) - 1) {
                $string .= ", ";
            }
        }
        return $string;
    }

    /**
     * Check Table And Columns
     * 
     * Checks if a table and columns exist. Throws an exception if the table or columns do not exist.
     * 
     * @param string $table The table to check.
     * @param array $columns The columns to check.
     * 
     * @return void
     */
    public function checkTableAndColumns(string $table, array $columns = ["*"]): void
    {
        $tableExists = $this->executeStatement("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name;", [new ParamBindObject("table_name", $table)]);

        if ($tableExists == false || $tableExists->fetchArray() == false) {
            $this->checkError([false, "Table does not exist. Table: {$table}"]);
        }

        if (count($columns) == 0 || $columns[0] == "*") {
            return;
        }

        $tableColumns = $this->executeStatement("PRAGMA table_info({$table});");

        $tableColumnsArray = [];

        while ($row = $tableColumns->fetchArray()) {
            $tableColumnsArray[] = $row["name"];
        }

        foreach ($columns as $column) {
            if (!in_array($column, $tableColumnsArray)) {
                $this->checkError([false, "Column does not exist. Column: {$column}"]);
            }
        }
    }

    /**
     * Check if null
     * 
     * Checks if a value is null.
     * 
     * @param mixed $value The value to check.
     * @param string $message The message to return if the value is null.
     */
    private function checkIfNull($value, $message): array
    {
        if (isset($value) == false) {
            return [false, $message];
        }
        return [true, $value];
    }

    /**
     * Check Error
     * 
     * Checks if an error occured.
     * Throws an exception if an error occured.
     * 
     * @param array $result The result to check.
     */
    private function checkError($result)
    {
        if ($result[0] == false) {
            throw new Exception($result[1]);
        }
        return $result[1];
    }
}
