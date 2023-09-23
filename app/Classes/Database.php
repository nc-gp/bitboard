<?php

namespace App\Classes;

use mysqli;

/**
 * The Database class provides methods for interacting with a MySQL database using MySQLi.
 */
class Database
{
    private $connection;
    private $query;
    private bool $show_errors = true;
    private bool $query_closed = true;
    public int $query_count = 0;

    /**
     * Constructor to initialize the Database connection.
     *
     * @param string $dbhost   The database host.
     * @param string $dbuser   The database user.
     * @param string $dbpass   The database password.
     * @param string $dbname   The database name.
     * @param string $charset  The character set for the database connection.
     */
    public function __construct(string $dbhost = 'localhost', string $dbuser = 'root', string $dbpass = '', string $dbname = 'bitboard', string $charset = 'utf8')
    {
        $this->connection = new mysqli($dbhost, $dbuser, $dbpass);

        if ($this->connection->connect_error)
            $this->Error('Failed to connect to MySQL - ' . $this->connection->connect_error);

        $this->connection->set_charset($charset);
        $this->SelectDatabase($dbname);
    }

    /**
     * Select the database to use.
     *
     * @param string $dbname The name of the database to select.
     */
    public function SelectDatabase(string $dbname)
    {
        $this->Query('CREATE DATABASE IF NOT EXISTS ' . $dbname);

        $this->connection->select_db($dbname);
    }

    /**
     * Execute a MySQL query.
     *
     * @param string $query The SQL query to execute.
     * @return Database The Database object.
     */
    public function Query($query) 
    {
        if (!$this->query_closed)
            $this->query->close();

        if ($this->query = $this->connection->prepare($query))
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

            if ($this->query->errno)
                $this->Error('Unable to process MySQL query (check your params) - ' . $this->query->error);

            $this->query_closed = false;
            $this->query_count++;
        } 
        else
            $this->Error('Unable to prepare MySQL statement (check your syntax) - ' . $this->connection->error);

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
     * Close the database connection.
     *
     * @return bool True if the connection was successfully closed; false otherwise.
     */
    public function Close()
    {
        return $this->connection->close();
    }

    /**
     * Get the number of rows in the result set.
     *
     * @return int The number of rows in the result set.
     */
    public function NumRows()
    {
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
        return $this->query->affected_rows;
    }

    /**
     * Get the last inserted ID from the last query.
     *
     * @return int The last inserted ID.
     */
    public function LastInsertID()
    {
        return $this->connection->insert_id;
    }

    /**
     * Display an error message and exit if show_errors is enabled.
     *
     * @param string $error The error message to display.
     */
    public function Error($error)
    {
        if ($this->show_errors)
            exit($error);
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