<?php

namespace App\Classes;

use mysqli;
use mysqli_sql_exception;

/**
 * The Database class provides methods for interacting with a MySQL database using MySQLi.
 *
 * Only one statement is active at a time: Query() closes the previous statement.
 * Always fetch rows before starting another query.
 */
class Database
{
    private $connection;
    private $query;
    private bool $query_closed = true;
    public int $query_count = 0;

    /**
     * Constructor to initialize the Database connection.
     *
     * @param string $dbhost   The database host.
     * @param string $dbuser   The database user.
     * @param string $dbpass   The database password.
     * @param string $charset  The character set for the database connection.
     *
     * @throws DatabaseException When the connection or charset setup fails.
     */
    public function __construct(string $dbhost = 'localhost', string $dbuser = 'root', string $dbpass = '', string $charset = 'utf8')
    {
        // Make mysqli failures explicit on every supported PHP version instead
        // of relying on the PHP 8.1+ default.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try
        {
            $this->connection = new mysqli($dbhost, $dbuser, $dbpass);
        }
        catch (mysqli_sql_exception $e)
        {
            throw new DatabaseException(
                'Failed to connect to the database: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        // The application does not use explicit transactions. Keep autocommit on so
        // short-lived requests never leave row locks behind for the next request.
        if (!$this->connection->autocommit(true))
            throw new DatabaseException('Failed to enable autocommit: ' . $this->connection->error);

        if (!$this->connection->set_charset($charset))
            throw new DatabaseException('Failed to set database charset: ' . $this->connection->error);
    }

    /**
     * Select the database to use.
     *
     * @param string $dbname The name of the database to select.
     *
     * @throws DatabaseException When the database cannot be selected.
     */
    public function SelectDatabase(string $dbname)
    {
        try
        {
            $selected = $this->connection->select_db($dbname);
        }
        catch (mysqli_sql_exception $e)
        {
            throw new DatabaseException(
                'Unable to select database "' . $dbname . '": ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if (!$selected)
            throw new DatabaseException('Unable to select database "' . $dbname . '": ' . $this->connection->error);
    }

    /**
     * Execute a MySQL query.
     *
     * @param string $query The SQL query to execute.
     * @return Database The Database object.
     *
     * @throws DatabaseException When preparing or executing the statement fails.
     */
    public function Query($query)
    {
        if (!$this->query_closed && $this->query !== null)
            $this->query->close();

        // The previous statement is gone even if this one fails to prepare.
        $this->query = null;
        $this->query_closed = true;

        try
        {
            $statement = $this->connection->prepare($query);
        }
        catch (mysqli_sql_exception $e)
        {
            throw new DatabaseException(
                'Unable to prepare MySQL statement: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if (!$statement)
            throw new DatabaseException('Unable to prepare MySQL statement: ' . $this->connection->error);

        $this->query = $statement;

        try
        {
            if (func_num_args() > 1)
            {
                $x = func_get_args();
                $args = array_slice($x, 1);
                $types = '';
                $args_ref = array();

                foreach ($args as $k => &$arg) {
                    if (is_array($args[$k]))
                    {
                        foreach ($args[$k] as $j => &$a)
                        {
                            $types .= $this->_gettype($args[$k][$j]);
                            $args_ref[] = &$a;
                        }
                    }
                    else
                    {
                        $types .= $this->_gettype($args[$k]);
                        $args_ref[] = &$arg;
                    }
                }

                array_unshift($args_ref, $types);
                call_user_func_array(array($this->query, 'bind_param'), $args_ref);
            }

            $this->query->execute();
        }
        catch (mysqli_sql_exception $e)
        {
            try
            {
                $this->query->close();
            }
            catch (\Throwable $ignored)
            {
            }

            $this->query = null;
            $this->query_closed = true;

            throw new DatabaseException(
                'Unable to process MySQL query: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        $this->query_closed = false;
        $this->query_count++;

        return $this;
    }

    /**
     * Fetch all rows from a result set.
     *
     * @param callable $callback A callback function to process each row.
     * @return array The array of fetched rows.
     */
    public function FetchAll($callback = null)
    {
        $this->RequireResult();

        $params = array();
        $row = array();
        $meta = $this->query->result_metadata();

        while ($field = $meta->fetch_field())
            $params[] = &$row[$field->name];

        call_user_func_array(array($this->query, 'bind_result'), $params);
        $result = array();

        while ($this->query->fetch())
        {
            $r = array();

            foreach ($row as $key => $val)
                $r[$key] = $val;

            if ($callback != null && is_callable($callback))
            {
                $value = call_user_func($callback, $r);

                if ($value == 'break')
                    break;
            }
            else
                $result[] = $r;
        }

        $this->query->close();
        $this->query_closed = true;

        return $result;
    }

    /**
     * Fetch a single row as an associative array from a result set.
     *
     * @return array The associative array representing the fetched row.
     */
    public function FetchArray()
    {
        $this->RequireResult();

        $params = array();
        $row = array();
        $meta = $this->query->result_metadata();

        while ($field = $meta->fetch_field())
            $params[] = &$row[$field->name];

        call_user_func_array(array($this->query, 'bind_result'), $params);
        $result = array();

        while ($this->query->fetch())
            foreach ($row as $key => $val)
                $result[$key] = $val;

        $this->query->close();
        $this->query_closed = true;

        return $result;
    }

    /**
     * Start a transaction.
     */
    public function BeginTransaction(): bool
    {
        return $this->connection->begin_transaction();
    }

    /**
     * Commit the active transaction.
     */
    public function Commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Roll back the active transaction.
     */
    public function RollBack(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Close the database connection.
     *
     * @return bool True if the connection was successfully closed; false otherwise.
     */
    public function Close()
    {
        if (!$this->query_closed && $this->query !== null)
        {
            $this->query->close();
            $this->query_closed = true;
        }

        return $this->connection->close();
    }

    /**
     * Get the number of rows in the result set.
     *
     * @return int The number of rows in the result set.
     */
    public function NumRows()
    {
        $this->RequireResult();
        $this->query->store_result();
        return $this->query->num_rows;
    }

    /**
     * Get the number of affected rows by the last query.
     *
     * @return int The number of affected rows.
     */
    public function AffectedRows()
    {
        $this->RequireResult();
        return $this->query->affected_rows;
    }

    /**
     * Get the last inserted ID from the last query.
     *
     * @return int|string The last inserted ID.
     */
    public function LastInsertID()
    {
        return $this->connection->insert_id;
    }

    /**
     * Ensure a statement is available before reading result metadata.
     */
    private function RequireResult(): void
    {
        if ($this->query_closed || $this->query === null)
            throw new DatabaseException('No active query result. Run Query() before fetching rows.');
    }

    /**
     * Get the MySQL data type for a variable.
     *
     * @param mixed $var The variable to determine the type for.
     * @return string The MySQL data type.
     */
    private function _gettype($var)
    {
        if (is_string($var))
            return 's';

        if (is_float($var))
            return 'd';

        if (is_int($var))
            return 'i';

        return 'b';
    }
}

?>
