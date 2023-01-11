<?php

require_once "Classes/ParamBindObject.php";

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
     * Select
     * 
     * Executes a select query on the database.
     * 
     * @param string $query The query to execute.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     */
    public function select($query = "", $params = [])
    {
        $result = $this->executeStatement($query, $params);

        if ($result === false) {
            $this->checkError([false, "Error while executing statement."]);
        }

        $returnArray = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            array_push($returnArray, $row);
        }

        return $this->checkError([true, $returnArray]);
    }

    /**
     * Insert
     * 
     * Executes an insert query on the database.
     * 
     * @param string $table The table to insert into.
     * @param array $params The parameters to bind to the query. (ParamBindObject)
     */
    public function insert($table = "", $params = [])
    {
        $tableExists = $this->executeStatement("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name;", [new ParamBindObject(":table_name", $table)]);

        if ($tableExists == false || $tableExists->fetchArray() == false) {
            $this->checkError([false, "Table does not exist."]);
        }

        $sql_statementColumns = $this->getStatementString($params, true);
        $sql_statementValues = $this->getStatementString($params);

        $sql = "INSERT INTO {$table} {$sql_statementColumns} VALUES {$sql_statementValues};";

        $result = $this->executeStatement($sql, $params);
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
            $stmt->bindValue($param->param, $param->value, $param->type);
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
     */
    private function getStatementString($params = [], $withColon = false, $updateString = false)
    {
        if (!$updateString) {
            $string = "(";
            for ($i = 0; $i < count($params); $i++) {
                if ($withColon) {
                    $string .= substr($params[$i]->param, $params[$i]->idCount);
                } else {
                    $string .= $params[$i]->param;
                }
                if ($i < count($params) - 1) {
                    $string .= ", ";
                }
            }
            $string .= ")";
            return $string;
        } else {
            $string = "";
            for ($i = 0; $i < count($params); $i++) {
                $string .= substr($params[$i]->param, $params[$i]->idCount) . " = " . $params[$i]->param;
                if ($i < count($params) - 1) {
                    $string .= ", ";
                }
            }
            return $string;
        }
        return;
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
