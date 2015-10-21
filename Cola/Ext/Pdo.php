<?php

class Cola_Ext_Pdo
{
    public $pdo = null;

    public $stmt = null;

    public $log = array();

    public function __construct($dsn, $user = '', $password = '', $options = array())
    {
        $options += array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
        $this->pdo = new PDO($dsn, $user, $password, $options);
    }

    /**
     * Close connection
     *
     */
    public function close()
    {
        $this->pdo = null;
    }

    /**
     * Free result
     *
     */
    public function free()
    {
        $this->stmt = null;
    }

    /**
     * Query sql
     *
     * @param string $sql
     * @return Cola_Ext_Db_Mysql
     */
    public function query($sql)
    {
        $this->log[] = array('time' => date('Y-m-d H:i:s'), 'sql' => $sql);
        $this->stmt = $this->pdo->query($sql);
        return $this->stmt;
    }

    public function sql($sql, $data = array())
    {
        $this->log[] = array('time' => date('Y-m-d H:i:s'), 'sql' => $sql);
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute($data);
        $tags = explode(' ', $sql, 2);
        switch (strtoupper($tags[0])) {
            case 'SELECT':
                $result = $this->fetchAll();
                break;
            case 'INSERT':
                $result = $this->lastInsertId();
                break;
            case 'UPDATE':
            case 'DELETE':
                $result = (0 <= $this->affectedRows());
                break;
            default:
                $result = $this->stmt;
        }
        return $result;
    }

    /**
     * Get a result row
     *
     * @param string $sql
     * @param int $style
     * @return array
     */
    public function row($sql, $style = PDO::FETCH_ASSOC)
    {
        $this->query($sql);
        return $this->fetch($style);
    }

    /**
     * Get first column of result
     *
     * @param string $sql
     * @return string
     */
    public function col($sql)
    {
        $this->query($sql);
        $result = $this->fetch();
        return empty($result) ? null : current($result);
    }

    /**
     * Insert
     *
     * @param string $table
     * @param array $data
     * @return boolean
     */
    public function insert($table, $data)
    {
        $keys = array();
        $marks = array();
        $values = array();
        foreach ($data as $key => $val) {
            is_array($val) && ($val = json_encode($val, JSON_UNESCAPED_UNICODE));
            $keys[] = "`{$key}`";
            $marks[] = '?';
            $values[] = $val;
        }

        $keys = implode(',', $keys);
        $marks = implode(',', $marks);
        $sql = "insert into {$table} ({$keys}) values ({$marks});";
        return $this->sql($sql, $values);
    }

    /**
     * Update table
     *
     * @param string $table
     * @param array $data
     * @param string $where
     * @return int
     */
    public function update($table, $data, $where = '0')
    {
        $keys = array();
        $values = array();
        foreach ($data as $key => $val) {
            is_array($val) && ($val = json_encode($val, JSON_UNESCAPED_UNICODE));
            $keys[] = "`{$key}`=?";
            $values[] = $val;
        }
        $keys = implode(',', $keys);
        if (is_string($where)) {
            $where = array($where, array());
        }
        $values = array_merge($values, $where[1]);
        $sql = "update {$table} set {$keys} where {$where[0]}";
        return $this->sql($sql, $values);
    }

    /**
     * Delete from table
     *
     * @param string $table
     * @param string $where
     * @return int
     */
    public function delete($table, $where = '0')
    {
        if (is_string($where)) {
            $where = array($where, array());
        }
        $sql = "delete from {$table} where {$where[0]}";
        return $this->sql($sql, $where[1]);
    }

    public function del($table, $where = '0')
    {
        return $this->delete($table, $where = '0');
    }

    /**
     * Count num rows
     *
     * @param string $table
     * @param string $where
     * @return int
     */
    public function count($table, $where)
    {
        $sql = "select count(1) as cnt from {$table} where {$where}";
        return intval($this->col($sql));
    }

    /**
     * Fetch one row result
     *
     * @param string $style
     * @return mixd
     */
    public function fetch($style = PDO::FETCH_ASSOC)
    {
        return $this->stmt->fetch($style);
    }

    /**
     * Fetch All result
     *
     * @param string $style
     * @return array
     */
    public function fetchAll($style = PDO::FETCH_ASSOC)
    {
        $result = $this->stmt->fetchAll($style);
        $this->free();
        return $result;
    }

    /**
     * Return the rows affected of the last sql
     *
     * @return int
     */
    public function affectedRows()
    {
        return $this->rowCount();
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * Get the last inserted ID.
     *
     * @param string $name
     * @return mixed
     */
    public function lastInsertId($name = null)
    {
        $last = $this->pdo->lastInsertId($name);
        if (false === $last) {
            return false;
        } else if ('0' === $last) {
            return true;
        } else {
            return intval($last);
        }
    }

    /**
     * Ping server
     *
     * @param boolean $reconnect
     * @return boolean
     */
    public function ping($reconnect = true)
    {
        if ($this->pdo && $this->pdo->query('select 1')) {
            return true;
        }

        if ($reconnect) {
            $this->close();
            $this->connect();
            return $this->ping(false);
        }

        return false;
    }
}