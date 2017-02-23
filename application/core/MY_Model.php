<?php
/**
 * Emulated eloquent for CodeIgniter
 * Copyright © 2017 Solomon GU
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class MY_Model extends CI_Model
{
    // Const value YES as 1.
    const VALUE_YES = 1;
    // Const value NO as 0.
    const VALUE_NO = 0;

    /**
     * Define the soft delete column.
     *
     * @var string
     */
    protected $soft_delete_column = 'is_deleted';

    /**
     * Define the soft delete time column.
     *
     * @var string
     */
    protected $soft_deleted_at_column = 'delete_time';

    /**
     * Define the default database config.
     *
     * @var string
     */
    protected $db_conf = 'default';

    /**
     * Database connection
     *
     * @var object
     */
    protected $db;

    /**
     * Define the table name without prefix.
     *
     * @var string
     */
    protected $table = '';

    /**
     * Define if soft delete enabled.
     *
     * @var bool
     */
    protected $soft_delete_enabled = false;

    /**
     * Define the table's columns.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Define the result order by.
     * [column => direction]
     *
     * @var array
     */
    protected $order_by = [];

    /**
     * Define the primary key.
     *
     * @var string
     */
    protected $primary_key = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->load->database($this->db_conf, true);

        if (! empty($this->order_by)) {
            foreach ($this->order_by as $column=> $direction) {
                $this->db->order_by($column, $direction);
            }
        }
    }

    /**
     * Create one row of data into the table.
     *
     * @param array $data
     * @return bool
     */
    public function create($data)
    {
        $insert_data = $this->tidy_data($data);

        if (empty($insert_data)) {
            return false;
        }

        return $this->insert($insert_data);
    }

    /**
     * Tidy the data according to the table's columns.
     *
     * @param array $data
     * @return mixed
     */
    protected function tidy_data($data)
    {
        $insert_data = [];
        $columns = $this->get_table_columns();
        foreach ($data as $key => $val) {
            if (in_array($key, $columns)) {
                $insert_data[$key] = is_null($val)? '' : $val;
            }
        }

        return $insert_data;
    }

    /**
     * Create rows of data into the table.
     *
     * @param array $batch_data
     * @return bool
     */
    public function batch_create($batch_data)
    {
        $batch_insert_data = [];
        foreach ($batch_data as $row) {
            $batch_insert_data[] = $this->tidy_data($row);
        }

        if (empty($batch_insert_data)) {
            return false;
        }

        $result = $this->insert_batch($batch_insert_data);

        if ($result === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Update one row of data.
     *
     * @param array $data
     * @param array $where
     * @return bool
     */
    public function save($data, $where = [])
    {
        $primary_key = $this->get_primary_key();
        if (empty($where) && isset($data[$primary_key])) {
            $where[$primary_key] = $data[$primary_key];
        }

        $data = $this->tidy_data($data);
        $where = $this->tidy_data($where);
        if (isset($data[$primary_key])) {
            unset($data[$primary_key]);
        }

        if (empty($data) || empty($where)) {
            return false;
        }

        if (is_array($where)) {
            foreach ($where as $col => $val) {
                $this->where($col, $val);
            }
        } elseif (is_string($where)) {
            $this->where($where);
        }

        return $this->update($data);
    }

    /**
     * Update rows of data. Return true if successful. Otherwise, return false.
     *
     * @param array $data
     * @param string $index
     * @return bool
     */
    public function batch_save($data, $index)
    {
        foreach ($data as &$row) {
            $row = $this->tidy_data($row);
        }

        if (empty($data)) {
            return false;
        }

        $result = $this->update_batch($data, $index);

        if ($result === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Search the table for one row of data with primary key.
     * If array is given, search with where conditions.
     *
     * @param mixed $id
     * @param string $columns
     * @return array
     */
    public function show($id, $columns = '')
    {
        $this->select($columns);

        if (is_array($id)) {
            foreach ($id as $col => $val) {
                $this->where($col, $val);
            }
        } else {
            $primary_key = $this->get_primary_key();
            $this->where($primary_key, $id);
        }

        return $this->get()->row_array();
    }

    /**
     * Search for rows of data with primary key values.
     *
     * @param array $ids
     * @param string $columns
     * @return array
     */
    public function find_by_ids($ids, $columns = '')
    {
        $primary_key = $this->get_primary_key();
        return $this->select($columns)->where_in($primary_key, $ids)->get()->result_array();
    }

    /**
     * Search for rows of data with where conditions.
     *
     * @param array $where
     * @param string $columns
     * @return array
     */
    public function find($where, $columns = '')
    {
        $where = $this->tidy_data($where);

        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $this->where_in($key, $val);
            } else {
                $this->where($key, $val);
            }
        }

        return $this->select($columns)->get()->result_array();
    }

    /**
     * If the solf delete is enabled, query with the soft delete is NO. 
     *
     * @return $this
     */
    public function query_undeleted()
    {
        if ($this->soft_delete_enabled) {
            $this->where($this->soft_delete_column, self::VALUE_NO);
        }

        return $this;
    }

    /**
     * If the solf delete is enabled, query with the soft delete is YES. 
     *
     * @return $this
     */
    public function query_deleted()
    {
        if ($this->soft_delete_enabled) {
            $this->where($this->soft_delete_column, self::VALUE_YES);
        }

        return $this;
    }

    /**
     * Select
     *
     * Generates the SELECT portion of the query
     *
     * @param	string
     * @param	mixed
     * @return $this
     */
    public function select($select = '*', $escape = null)
    {
        $select = $this->get_available_columns($select);

        $this->db->select($select, $escape);

        return $this;
    }


    /**
     * Select Max
     *
     * Generates a SELECT MAX(field) portion of a query
     *
     * @param	string	the field
     * @param	string	an alias
     * @return $this
     */
    public function select_max($select = '', $alias = '')
    {
        $select = $this->get_available_columns($select);

        $this->db->select_max($select, $alias);

        return $this;
    }

    /**
     * Select Min
     *
     * Generates a SELECT MIN(field) portion of a query
     *
     * @param	string	the field
     * @param	string	an alias
     * @return $this
     */
    public function select_min($select = '', $alias = '')
    {
        $select = $this->get_available_columns($select);

        $this->db->select_min($select, $alias);

        return $this;
    }

    /**
     * Select Average
     *
     * Generates a SELECT AVG(field) portion of a query
     *
     * @param	string	the field
     * @param	string	an alias
     * @return $this
     */
    public function select_avg($select = '', $alias = '')
    {
        $select = $this->get_available_columns($select);

        $this->db->select_avg($select, $alias);

        return $this;
    }

    /**
     * Select Sum
     *
     * Generates a SELECT SUM(field) portion of a query
     *
     * @param	string	the field
     * @param	string	an alias
     * @return $this
     */
    public function select_sum($select = '', $alias = '')
    {
        $select = $this->get_available_columns($select);

        $this->db->select_avg($select, $alias);

        return $this;
    }

    /**
     * Get SELECT query string
     *
     * Compiles a SELECT query string and returns the sql.
     *
     * @param	bool	TRUE: resets QB values; FALSE: leave QB values alone
     * @return	string
     */
    public function get_compiled_select($reset = true)
    {
        $table = $this->get_table();

        return $this->db->get_compiled_select($table, $reset);
    }

    /**
     * Get
     *
     * Compiles the select statement based on the other functions called
     * and runs the query
     *
     * @param	string	the limit clause
     * @param	string	the offset clause
     * @return	CI_DB_result
     */
    public function get($limit = null, $offset = null)
    {
        $table = $this->get_table();

        return $this->db->get($table, $limit, $offset);
    }

    /**
     * "Count All Results" query
     *
     * Generates a platform-specific query string that counts all records
     * returned by an Query Builder query.
     *
     * @param	bool	the reset clause
     * @return	int
     */
    public function count_all_results($reset = true)
    {
        $table = $this->get_table();

        return $this->db->count_all_results($table, $reset);
    }

    /**
     * Get_Where
     *
     * Allows the where clause, limit and offset to be added directly
     *
     * @param	string	$where
     * @param	int	$limit
     * @param	int	$offset
     * @return	CI_DB_result
     */
    public function get_where($where = null, $limit = null, $offset = null)
    {
        $table = $this->get_table();

        return $this->db->get_where($table, $where, $limit, $offset);
    }

    /**
     * Insert_Batch
     *
     * Compiles batch insert strings and runs the queries
     *
     * @param	array	$set 	An associative array of insert values
     * @param	bool	$escape	Whether to escape values and identifiers
     * @return	int	Number of rows inserted or FALSE on failure
     */
    public function insert_batch($set = null, $escape = null, $batch_size = 100)
    {
        $table = $this->get_table();

        return $this->db->insert_batch($table, $set, $escape, $batch_size);
    }

    /**
     * Get INSERT query string
     *
     * Compiles an insert query and returns the sql
     *
     * @param	bool	TRUE: reset QB values; FALSE: leave QB values alone
     * @return	string
     */
    public function get_compiled_insert($reset = true)
    {
        $table = $this->get_table();

        return $this->db->get_compiled_insert($table, $reset);
    }

    /**
     * Insert
     *
     * Compiles an insert string and runs the query
     *
     * @param	bool	$escape	Whether to escape values and identifiers
     * @return	bool	TRUE on success, FALSE on failure
     */
    public function insert($set = null, $escape = null)
    {
        $table = $this->get_table();

        return $this->db->insert($table, $set, $escape);
    }

    /**
     * Replace
     *
     * Compiles an replace into string and runs the query
     *
     * @param	array	an associative array of insert values
     * @return	bool	TRUE on success, FALSE on failure
     */
    public function replace($set = null)
    {
        $table = $this->get_table();

        return $this->db->replace($table, $set);
    }

    /**
     * Get UPDATE query string
     *
     * Compiles an update query and returns the sql
     *
     * @param	bool	TRUE: reset QB values; FALSE: leave QB values alone
     * @return	string
     */
    public function get_compiled_update($reset = true)
    {
        $table = $this->get_table();

        return $this->db->get_compiled_update($table, $reset);
    }

    /**
     * UPDATE
     *
     * Compiles an update string and runs the query.
     *
     * @param	array	$set	An associative array of update values
     * @param	mixed	$where
     * @param	int	$limit
     * @return	bool	TRUE on success, FALSE on failure
     */
    public function update($set = null, $where = null, $limit = null)
    {
        $table = $this->get_table();

        return $this->db->update($table, $set, $where, $limit);
    }

    /**
     * Update_Batch
     *
     * Compiles an update string and runs the query
     *
     * @param	array	an associative array of update values
     * @param	string	the where key
     * @return	int	number of rows affected or FALSE on failure
     */
    public function update_batch($set = null, $index = null, $batch_size = 100)
    {
        $table = $this->get_table();

        return $this->db->update_batch($table, $set, $index, $batch_size);
    }

    /**
     * Empty Table
     *
     * Compiles a delete string and runs "DELETE FROM table"
     *
     * @return	bool	TRUE on success, FALSE on failure
     */
    public function empty_table()
    {
        $table = $this->get_table();

        return $this->db->empty_table($table);
    }

    /**
     * Truncate
     *
     * Compiles a truncate string and runs the query
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @return	bool	TRUE on success, FALSE on failure
     */
    public function truncate()
    {
        $table = $this->get_table();

        return $this->db->truncate($table);
    }

    /**
     * Get DELETE query string
     *
     * Compiles a delete query string and returns the sql
     *
     * @param	bool	TRUE: reset QB values; FALSE: leave QB values alone
     * @return	string
     */
    public function get_compiled_delete($reset = true)
    {
        $table = $this->get_table();

        return $this->db->get_compiled_delete($table, $reset);
    }

    /**
     * Delete
     *
     * Compiles a delete string and runs the query
     *
     * @param	mixed	the where clause
     * @param	mixed	the limit clause
     * @param	bool
     * @return	mixed
     */
    public function delete($where = '', $limit = null, $reset_data = true)
    {
        if ($this->soft_delete_enabled) {
            $set = [
                $this->soft_delete_column => self::VALUE_YES,
                $this->soft_deleted_at_column => time(),
            ];

            return $this->update($set, $where, $limit);
        }

        $table = $this->get_table();

        return $this->db->delete($table, $where, $limit, $reset_data);
    }

    /**
     * DB Prefix
     *
     * Prepends a database prefix if one exists in configuration
     *
     * @return	string
     */
    public function dbprefix()
    {
        $table = $this->get_table();

        return $this->db->dbprefix($table);
    }

    /**
     * Calculate the aggregate query elapsed time
     *
     * @param	int	The number of decimal places
     * @return	string
     */
    public function elapsed_time($decimals = 6)
    {
        return $this->db->elapsed_time($decimals);
    }

    /**
     * Returns the total number of queries
     *
     * @return	int
     */
    public function total_queries()
    {
        return $this->db->total_queries();
    }

    /**
     * Returns the last query that was executed
     *
     * @return	string
     */
    public function last_query()
    {
        return $this->db->last_query();
    }

    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     * Sets boolean and null types
     *
     * @param	string
     * @return	mixed
     */
    public function escape($str)
    {
        return $this->db->escape($str);
    }

    /**
     * Escape String
     *
     * @param	string|string[]	$str	Input string
     * @param	bool	$like	Whether or not the string will be used in a LIKE condition
     * @return	string
     */
    public function escape_str($str, $like = false)
    {
        return $this->db->escape_str($str, $like);
    }

    /**
     * Escape LIKE String
     *
     * Calls the individual driver for platform
     * specific escaping for LIKE conditions
     *
     * @param	string|string[]
     * @return	mixed
     */
    public function escape_like_str($str)
    {
        return $this->db->escape_like_str($str);
    }

    /**
     * Primary
     *
     * Retrieves the primary key. It assumes that the row in the first
     * position is the primary key
     *
     * @return	string
     */
    public function primary()
    {
        $table = $this->get_table();

        return $this->db->primary($table);
    }

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @return	int
     */
    public function count_all()
    {
        $table = $this->get_table();

        return $this->db->count_all($table);
    }

    /**
     * Fetch Field Names
     *
     * @return	array
     */
    public function list_fields()
    {
        $table = $this->get_table();

        return $this->db->list_fields($table);
    }

    /**
     * Determine if a particular field exists
     *
     * @param	string
     * @return	bool
     */
    public function field_exists($field_name)
    {
        $table = $this->get_table();

        return $this->db->field_exists($field_name, $table);
    }

    /**
     * Returns an object with field data
     *
     * @return	array
     */
    public function field_data()
    {
        $table = $this->get_table();

        return $this->db->field_data($table);
    }

    /**
     * Generate an insert string
     *
     * @param	array	an associative array data of key/values
     * @return	string
     */
    public function insert_string($data)
    {
        $table = $this->get_table();

        return $this->db->insert_string($table, $data);
    }

    /**
     * Generate an update string
     *
     * @param	array	an associative array data of key/values
     * @param	mixed	the "where" statement
     * @return	string
     */
    public function update_string($data, $where)
    {
        $table = $this->get_table();

        return $this->db->update_string($table, $data, $where);
    }

    /**
     * Affected Rows
     *
     * @return	int
     */
    public function affected_rows()
    {
        return $this->db->affected_rows();
    }

    /**
     * Insert ID
     *
     * @return	int
     */
    public function insert_id()
    {
        return $this->db->insert_id();
    }

    /**
     * Error
     *
     * Returns an array containing code and message of the last
     * database error that has occurred.
     *
     * @return	array
     */
    public function error()
    {
        return $this->db->error();
    }

    /**
     * Dynamically call the instance method
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            $obj = $this;
        } elseif (method_exists($this->db, $method)) {
            $obj = $this->db;
        } else {
            throw new Exception('Call undefined method ' . $method . '.');
        }

        $result = $obj->$method(...$parameters);

        if ($result instanceof CI_DB_query_builder) {
            $result = $this;
        }

        return $result;
    }

    /**
     * Return the primary key column
     *
     * @return string
     */
    public function get_primary_key()
    {
        if (empty($this->primary_key)) {
            $this->primary_key = $this->primary();
        }

        return $this->primary_key;
    }

    /**
     * Get the table's columns. If the `columns` property is not set, it will fetch columns via `list_filed()`
     *
     * @return array
     */
    public function get_table_columns()
    {
        if (empty($this->columns)) {
            $this->columns = $this->list_fields();
        } elseif (is_string($this->columns)) {
            $this->columns = explode(',', $this->columns);
            $this->columns = array_map(function($column) {
                return trim($column);
            }, $this->columns);
        }

        return $this->columns;
    }

    /**
     * Filter the columns, return the availble columns.
     *
     * @param string $columns
     * @param bool $return_string true return string, false return array
     * @return string|array
     */
    public function get_available_columns($columns = '', $return_string = true)
    {
        $this->get_table_columns();

        if (empty($columns) || $columns == '*') {
            $columns = $this->columns;
        } else {
            if (is_string($columns)) {
                $columns = explode(',', $columns);
                $columns = array_map(function($column) {
                    return trim($column);
                }, $columns);
            }

            $columns = array_filter($columns, function($column) {
                return in_array($column, $this->columns);
            });
        }

        if ($return_string) {
            $columns = implode(',', $columns);
        }

        return $columns;
    }

    /**
     * Set the table.
     *
     * @param string $table
     * @return $this
     */
    public function set_table($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Return the table name.
     *
     * @param bool $with_prefix false without prefix, true with prefix
     * @return string
     */
    public function get_table($with_prefix = false)
    {
        $table = $this->table;

        if (empty($table)) {
            $class = strtolower(get_class($this));
            $table = strpos($class, '_model') !== false ? substr($class, 0, -6) : $class;
        }

        if ($with_prefix) {
            $table = $this->get_prefix() . $table;
        }

        return $table;
    }
}
