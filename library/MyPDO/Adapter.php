<?php
namespace MyPDO;
use \PDO;

/**
 * 
 * @author shen2
 *
 * 删除了以下函数
 * _checkRequiredOptions
 * lastSequenceId
 * nextSequenceId
 * getQuoteIdentifierSymbol
 * supportsParameters
 * limit
 * _quote
 * 
 * 等价修改了以下函数，使之代码长度变短
 * setFetchMode 
 * foldCase
 * quote
 * 
 * 注释掉了所有的throw-catch-rethrow块，直接抛出PDOException
 * 
 * 相比原来的Zend_Db_Adapter类，删除了以下代码
 * 
 * 删除了事务代码中，多余quote
 * 
 * limit函数中的count大小检查
 * 构造函数中
        if (!is_array($config)) {
            throw new AdapterException('Adapter parameters must be in an array or a Zend_Config object');
        }
            switch ($case) {
                case PDO::CASE_LOWER:
                case PDO::CASE_UPPER:
                case PDO::CASE_NATURAL:
                    $this->_caseFolding = $case;
                    break;
                default:
                    throw new AdapterException('Case must be one of the following constants: '
                        . 'PDO::CASE_NATURAL, PDO::CASE_LOWER, PDO::CASE_UPPER');
            }

 * 
 *
 */
class Adapter
{
    /**
     * User-provided configuration
     *
     * @var array
     */
    protected $_config = array();

    /**
     * Fetch mode
     *
     * @var integer
     */
    protected $_fetchMode = PDO::FETCH_ASSOC;

    /**
     * Query profiler object, of type Profiler
     * or a subclass of that.
     *
     * @var Profiler
     */
    protected $_profiler;

    /**
     * Default class name for the profiler object.
     *
     * @var string
     */
    protected $_defaultProfilerClass = 'MyPDO\Profiler';

    /**
     * Database connection
     *
     * @var PDO|null
     */
    protected $_connection = null;

    /**
     * Specifies the case of column names retrieved in queries
     * Options
     * PDO::CASE_NATURAL (default)
     * PDO::CASE_LOWER
     * PDO::CASE_UPPER
     *
     * @var integer
     */
    protected $_caseFolding = PDO::CASE_NATURAL;

    /**
     * Specifies whether the adapter automatically quotes identifiers.
     * If true, most SQL generated by Zend_Db classes applies
     * identifier quoting automatically.
     * If false, developer must quote identifiers themselves
     * by calling quoteIdentifier().
     *
     * @var bool
     */
    protected $_autoQuoteIdentifiers = true;


    /** Weither or not that object can get serialized
     *
     * @var bool
     */
    protected $_allowSerialization = true;

    /**
     * Weither or not the database should be reconnected
     * to that adapter when waking up
     *
     * @var bool
     */
    protected $_autoReconnectOnUnserialize = false;

    /**
     * Constructor.
     *
     * $config is an array of key/value pairs
     * containing configuration options.  These options are common to most adapters:
     *
     * dbname         => (string) The name of the database to user
     * username       => (string) Connect to the database as this username.
     * password       => (string) Password associated with the username.
     * host           => (string) What host to connect to, defaults to localhost
     *
     * Some options are used on a case-by-case basis by adapters:
     *
     * port           => (string) The port of the database
     * persistent     => (boolean) Whether to use a persistent connection or not, defaults to false
     * protocol       => (string) The network protocol, defaults to TCPIP
     * caseFolding    => (int) style of case-alteration used for identifiers
     *
     * @param  array $config An array having configuration data
     * @throws Zend_Db_Adapter_Exception
     */
    public function __construct($config)
    {
        //$this->_checkRequiredOptions($config);

        $options = array(
            CASE_FOLDING           => $this->_caseFolding,
            AUTO_QUOTE_IDENTIFIERS => $this->_autoQuoteIdentifiers,
            FETCH_MODE             => $this->_fetchMode,
        );
        $driverOptions = array();

        /*
         * normalize the config and merge it with the defaults
         */
        if (array_key_exists('options', $config)) {
            // can't use array_merge() because keys might be integers
            foreach ((array) $config['options'] as $key => $value) {
                $options[$key] = $value;
            }
        }
        if (array_key_exists('driver_options', $config)) {
            if (!empty($config['driver_options'])) {
                // can't use array_merge() because keys might be integers
                foreach ((array) $config['driver_options'] as $key => $value) {
                    $driverOptions[$key] = $value;
                }
            }
        }

        if (!isset($config['charset'])) {
            $config['charset'] = null;
        }

        if (!isset($config['persistent'])) {
            $config['persistent'] = false;
        }

        $this->_config = array_merge($this->_config, $config);
        $this->_config['options'] = $options;
        $this->_config['driver_options'] = $driverOptions;


        // obtain the case setting, if there is one
        if (array_key_exists(CASE_FOLDING, $options)) {
            $this->_caseFolding = (int) $options[CASE_FOLDING];
        }

        if (array_key_exists(FETCH_MODE, $options)) {
            if (is_string($options[FETCH_MODE])) {
                $constant = 'PDO::FETCH_' . strtoupper($options[FETCH_MODE]);
                if(defined($constant)) {
                    $options[FETCH_MODE] = constant($constant);
                }
            }
            $this->setFetchMode((int) $options[FETCH_MODE]);
        }

        // obtain quoting property if there is one
        if (array_key_exists(AUTO_QUOTE_IDENTIFIERS, $options)) {
            $this->_autoQuoteIdentifiers = (bool) $options[AUTO_QUOTE_IDENTIFIERS];
        }

        // obtain allow serialization property if there is one
        if (array_key_exists(ALLOW_SERIALIZATION, $options)) {
            $this->_allowSerialization = (bool) $options[ALLOW_SERIALIZATION];
        }

        // obtain auto reconnect on unserialize property if there is one
        if (array_key_exists(AUTO_RECONNECT_ON_UNSERIALIZE, $options)) {
            $this->_autoReconnectOnUnserialize = (bool) $options[AUTO_RECONNECT_ON_UNSERIALIZE];
        }

        // 修改了原来的Zend_Db代码，在不开启profiler的情况下，不再生成profiler实例
        $this->_profiler = false;
        
        if (array_key_exists(PROFILER, $this->_config)) {
            if ($this->_config[PROFILER])
            	$this->setProfiler($this->_config[PROFILER]);
            unset($this->_config[PROFILER]);
        }
    }

    /**
     * Returns the underlying database connection object or resource.
     * If not presently connected, this initiates the connection.
     *
     * @return object|resource|null
     */
    public function getConnection()
    {
        $this->_connect();
        return $this->_connection;
    }

    /**
     * Returns the configuration variables in this adapter.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Set the adapter's profiler object.
     *
     * The argument may be a boolean, an associative array, an instance of
     * Profiler.
     *
     * A boolean argument sets the profiler to enabled if true, or disabled if
     * false.  The profiler class is the adapter's default profiler class,
     * Profiler.
     *
     * An instance of Profiler sets the adapter's instance to that
     * object.  The profiler is enabled and disabled separately.
     *
     * An associative array argument may contain any of the keys 'enabled',
     * 'class', and 'instance'. The 'enabled' and 'instance' keys correspond to the
     * boolean and object types documented above. The 'class' key is used to name a
     * class to use for a custom profiler. The class must be Profiler or a
     * subclass. The class is instantiated with no constructor arguments. The 'class'
     * option is ignored when the 'instance' option is supplied.
     *
     * An object of type Zend_Config may contain the properties 'enabled', 'class', and
     * 'instance', just as if an associative array had been passed instead.
     *
     * @param  Profiler|array|boolean $profiler
     * @return Adapter Provides a fluent interface
     * @throws ProfilerException if the object instance or class specified
     *         is not Profiler or an extension of that class.
     */
    public function setProfiler($profiler)
    {
        $enabled          = null;
        $profilerClass    = $this->_defaultProfilerClass;
        $profilerInstance = null;

        if ($profilerIsObject = is_object($profiler)) {
            if ($profiler instanceof Profiler) {
                $profilerInstance = $profiler;
            } else {
                /**
                 * @see ProfilerException
                 */
                //require_once 'Zend/Db/Profiler/Exception.php';
                throw new ProfilerException('Profiler argument must be an instance of either Profiler'
                    . ' or Zend_Config when provided as an object');
            }
        }

        if (is_array($profiler)) {
            if (isset($profiler['enabled'])) {
                $enabled = (bool) $profiler['enabled'];
            }
            if (isset($profiler['class'])) {
                $profilerClass = $profiler['class'];
            }
            if (isset($profiler['instance'])) {
                $profilerInstance = $profiler['instance'];
            }
        } else if (!$profilerIsObject) {
            $enabled = (bool) $profiler;
        }

        if ($profilerInstance === null) {
            $profilerInstance = new $profilerClass();
        }

        if (!$profilerInstance instanceof Profiler) {
            /** @see ProfilerException */
            //require_once 'Zend/Db/Profiler/Exception.php';
            throw new ProfilerException('Class ' . get_class($profilerInstance) . ' does not extend '
                . 'Profiler');
        }

        if (null !== $enabled) {
            $profilerInstance->setEnabled($enabled);
        }

        $this->_profiler = $profilerInstance;

        return $this;
    }


    /**
     * Returns the profiler for this adapter.
     *
     * @return Profiler
     */
    public function getProfiler()
    {
        return $this->_profiler;
    }

    /**
     * Leave autocommit mode and begin a transaction.
     *
     * @return Adapter
     */
    public function beginTransaction()
    {
        $this->_connect();
        if ($this->_profiler) $q = $this->_profiler->queryStart('begin', Profiler::TRANSACTION);
        $this->_beginTransaction();
        if ($this->_profiler) $this->_profiler->queryEnd($q);
        return $this;
    }

    /**
     * Commit a transaction and return to autocommit mode.
     *
     * @return Adapter
     */
    public function commit()
    {
        $this->_connect();
        if ($this->_profiler) $q = $this->_profiler->queryStart('commit', Profiler::TRANSACTION);
        $this->_commit();
        if ($this->_profiler) $this->_profiler->queryEnd($q);
        return $this;
    }

    /**
     * Roll back a transaction and return to autocommit mode.
     *
     * @return Adapter
     */
    public function rollBack()
    {
        $this->_connect();
        if ($this->_profiler) $q = $this->_profiler->queryStart('rollback', Profiler::TRANSACTION);
        $this->_rollBack();
        if ($this->_profiler) $this->_profiler->queryEnd($q);
        return $this;
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insert($table, array $bind, $keyword = null)
    {
        // extract and quote col names from the array keys
        $cols = array();
        $vals = array();
        
        foreach ($bind as $col => $val) {
            $cols[] = $this->quoteIdentifier($col, true);
            if ($val instanceof Expr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = '?';
            }
        }

        // build the statement
        $sql = ($keyword ? "INSERT $keyword INTO " : "INSERT INTO ")
             . $this->quoteIdentifier($table, true)
             . ' (' . implode(', ', $cols) . ') '
             . 'VALUES (' . implode(', ', $vals) . ')';

        // execute the statement and return the number of affected rows
        $bind = array_values($bind);
        $stmt = $this->query($sql, $bind);
        $result = $stmt->rowCount();
        return $result;
    }
    
    /**
     * 使用insert delayed插入一条记录
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insertDelayed($table, array $bind)
    {
    	return $this->insert($table, $bind, 'DELAYED');
    }
    
    /**
     * 使用insert ignore插入一条记录
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insertIgnore($table, array $bind)
    {
    	return $this->insert($table, $bind, 'IGNORE');
    }
    
    public function insertOnDuplicateKeyUpdate($table, array $insertBind, array $updateBind)
    {
        // extract and quote col names from the array keys
        $cols = array();
        $vals = array();
        foreach ($insertBind as $col => $val) {
            $cols[] = $this->quoteIdentifier($col, true);
            if ($val instanceof Expr) {
                $vals[] = $val->__toString();
                unset($insertBind[$col]);
            } else {
                $vals[] = '?';
            }
        }

        /**
         * Build "col = ?" pairs for the statement,
         * except for Expr which is treated literally.
         */
        $set = array();
        foreach ($updateBind as $col => $val) {
            if ($val instanceof Expr) {
                $val = $val->__toString();
                unset($updateBind[$col]);
            } else {
                $val = '?';
            }
            $set[] = $this->quoteIdentifier($col, true) . ' = ' . $val;
        }

        // build the statement
        $sql = "INSERT INTO "
             . $this->quoteIdentifier($table, true)
             . ' (' . implode(', ', $cols) . ') '
             . 'VALUES (' . implode(', ', $vals) . ')'
             . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $set);
        
        // execute the statement and return the number of affected rows
        $bind = array_merge(array_values($insertBind), array_values($updateBind));
        $stmt = $this->query($sql, $bind);
        $result = $stmt->rowCount();
        return $result;
    }
    
    /**
     * Updates table rows with specified data based on a WHERE clause.
     *
     * @param  mixed        $table The table to update.
     * @param  array        $bind  Column-value pairs.
     * @param  mixed        $where UPDATE WHERE clause(s).
     * @return int          The number of affected rows.
     */
    public function update($table, array $bind, $where = '')
    {
        /**
         * Build "col = ?" pairs for the statement,
         * except for Expr which is treated literally.
         */
        $set = array();
        foreach ($bind as $col => $val) {
            if ($val instanceof Expr) {
                $val = $val->__toString();
                unset($bind[$col]);
            } else {
                $val = '?';
            }
            $set[] = $this->quoteIdentifier($col, true) . ' = ' . $val;
        }

        $where = $this->_whereExpr($where);

        /**
         * Build the UPDATE statement
         */
        $sql = "UPDATE "
             . $this->quoteIdentifier($table, true)
             . ' SET ' . implode(', ', $set)
             . (($where) ? " WHERE $where" : '');

        /**
         * Execute the statement and return the number of affected rows
         */
        $stmt = $this->query($sql, array_values($bind));
        $result = $stmt->rowCount();
        return $result;
    }

    /**
     * Deletes table rows based on a WHERE clause.
     *
     * @param  mixed        $table The table to update.
     * @param  mixed        $where DELETE WHERE clause(s).
     * @return int          The number of affected rows.
     */
    public function delete($table, $where = '')
    {
        $where = $this->_whereExpr($where);

        /**
         * Build the DELETE statement
         */
        $sql = "DELETE FROM "
             . $this->quoteIdentifier($table, true)
             . (($where) ? " WHERE $where" : '');

        /**
         * Execute the statement and return the number of affected rows
         */
        $stmt = $this->query($sql);
        $result = $stmt->rowCount();
        return $result;
    }

    /**
     * Convert an array, string, or Expr object
     * into a string to put in a WHERE clause.
     *
     * @param mixed $where
     * @return string
     */
    protected function _whereExpr($where)
    {
        if (empty($where)) {
            return $where;
        }
        if (!is_array($where)) {
            $where = array($where);
        }
        foreach ($where as $cond => &$term) {
            // is $cond an int? (i.e. Not a condition)
            if (is_int($cond)) {
                // $term is the full condition
                if ($term instanceof Expr) {
                    $term = $term->__toString();
                }
            } else {
                // $cond is the condition with placeholder,
                // and $term is quoted into the condition
                $term = $this->quoteInto($cond, $term);
            }
            $term = '(' . $term . ')';
        }

        $where = implode(' AND ', $where);
        return $where;
    }

    /**
     * Creates and returns a new Select object for this adapter.
     *
     * @return Select
     */
    public function select()
    {
        return new Select($this);
    }

    /**
     * Get the fetch mode.
     *
     * @return int
     */
    public function getFetchMode()
    {
        return $this->_fetchMode;
    }

    /**
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quoted
     * and then returned as a comma-separated string.
     *
     * @param mixed $value The value to quote.
     * @param mixed $type  OPTIONAL the SQL datatype name, or constant, or null.
     * @return mixed An SQL-safe quoted value (or string of separated values).
     */
    public function quote($value, $type = null)
    {
        if ($value instanceof Select) {
            return '(' . $value->assemble() . ')';
        }

        if ($value instanceof Expr) {
            return $value->__toString();
        }

        if ($type !== null && array_key_exists($type = strtoupper($type), $this->_numericDataTypes)) {
            $quotedValue = '0';
            switch ($this->_numericDataTypes[$type]) {
                case INT_TYPE: // 32-bit integer
                    $quotedValue = (string) intval($value);
                    break;
                case BIGINT_TYPE: // 64-bit integer
                    // ANSI SQL-style hex literals (e.g. x'[\dA-F]+')
                    // are not supported here, because these are string
                    // literals, not numeric literals.
                    if (preg_match('/^(
                          [+-]?                  # optional sign
                          (?:
                            0[Xx][\da-fA-F]+     # ODBC-style hexadecimal
                            |\d+                 # decimal or octal, or MySQL ZEROFILL decimal
                            (?:[eE][+-]?\d+)?    # optional exponent on decimals or octals
                          )
                        )/x',
                        (string) $value, $matches)) {
                        $quotedValue = $matches[1];
                    }
                    break;
                case FLOAT_TYPE: // float or decimal
                    $quotedValue = sprintf('%F', $value);
            }
            return $quotedValue;
        }

        return $this->_connection->quote($value);
    }
    
    /**
     * 为了解决quote的性能问题，将quote的Array迭代单独提出来
     */
    public function quoteArray($array, $type){
    	foreach ($array as &$val) {
            $val = $this->quote($val, $type);
        }
        return implode(', ', $array); 
    }

    /**
     * Quotes a value and places into a piece of text at a placeholder.
     *
     * The placeholder is a question-mark; all placeholders will be replaced
     * with the quoted value.   For example:
     *
     * <code>
     * $text = "WHERE date < ?";
     * $date = "2005-01-02";
     * $safe = $sql->quoteInto($text, $date);
     * // $safe = "WHERE date < '2005-01-02'"
     * </code>
     *
     * @param string  $text  The text with a placeholder.
     * @param mixed   $value The value to quote.
     * @param string  $type  OPTIONAL SQL datatype
     * @param integer $count OPTIONAL count of placeholders to replace
     * @return string An SQL-safe quoted value placed into the original text.
     */
    public function quoteInto($text, $value, $type = null, $count = null)
    {
    	//这里加入了连接检查之后quote就不需要再连接检查了
    	$this->_connect();
    	
    	$quotedValue = is_array($value) ? $this->quoteArray($value, $type) : $this->quote($value, $type);
    	
        if ($count === null) {
            return str_replace('?', $quotedValue, $text);
        } else {
            while ($count > 0) {
                if (strpos($text, '?') !== false) {
                    $text = substr_replace($text, $quotedValue, strpos($text, '?'), 1);
                }
                --$count;
            }
            return $text;
        }
    }

    /**
     * Quotes an identifier.
     *
     * Accepts a string representing a qualified indentifier. For Example:
     * <code>
     * $adapter->quoteIdentifier('myschema.mytable')
     * </code>
     * Returns: "myschema"."mytable"
     *
     * Or, an array of one or more identifiers that may form a qualified identifier:
     * <code>
     * $adapter->quoteIdentifier(array('myschema','my.table'))
     * </code>
     * Returns: "myschema"."my.table"
     *
     * The actual quote character surrounding the identifiers may vary depending on
     * the adapter.
     *
     * @param string|array|Expr $ident The identifier.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier($ident, $auto=false)
    {
        return $this->_quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array|Expr $ident The identifier or expression.
     * @param string $alias An alias for the column.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteColumnAs($ident, $alias, $auto=false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array|Expr $ident The identifier or expression.
     * @param string $alias An alias for the table.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     *
     * @param string|array|Expr $ident The identifier or expression.
     * @param string $alias An optional alias.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @param string $as The string to add between the identifier/expression and the alias.
     * @return string The quoted identifier and alias.
     */
    protected function _quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
    {
        if ($ident instanceof Expr) {
            $quoted = $ident->__toString();
        } elseif ($ident instanceof Select) {
            $quoted = '(' . $ident->assemble() . ')';
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            if (is_array($ident)) {
                $segments = array();
                foreach ($ident as $segment) {
                    if ($segment instanceof Expr) {
                        $segments[] = $segment->__toString();
                    } else {
                        $segments[] = $this->_quoteIdentifier($segment, $auto);
                    }
                }
                if ($alias !== null && end($ident) == $alias) {
                    $alias = null;
                }
                $quoted = implode('.', $segments);
            } else {
                $quoted = $this->_quoteIdentifier($ident, $auto);
            }
        }
        if ($alias !== null) {
            $quoted .= $as . $this->_quoteIdentifier($alias, $auto);
        }
        return $quoted;
    }

    /**
     * Quote an identifier.
     *
     * @param  string $value The identifier or expression.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string        The quoted identifier and alias.
     */
    protected function _quoteIdentifier($value, $auto=false)
    {
        if ($auto === false || $this->_autoQuoteIdentifiers === true) {
            return ('`' . str_replace('`', '``', $value) . '`');
        }
        return $value;
    }

    /**
     * Helper method to change the case of the strings used
     * when returning result sets in FETCH_ASSOC and FETCH_BOTH
     * modes.
     *
     * This is not intended to be used by application code,
     * but the method must be public so the Statement class
     * can invoke it.
     *
     * @param string $key
     * @return string
     */
    public function foldCase($key)
    {
        switch ($this->_caseFolding) {
            case PDO::CASE_LOWER:
                return strtolower((string) $key);
            case PDO::CASE_UPPER:
                return strtoupper((string) $key);
            case PDO::CASE_NATURAL:
            default:
                return (string) $key;
        }
    }

    /**
     * called when object is getting serialized
     * This disconnects the DB object that cant be serialized
     *
     * @throws AdapterException
     * @return array
     */
    public function __sleep()
    {
        if ($this->_allowSerialization == false) {
            /** @see AdapterException */
            //require_once 'Zend/Db/Adapter/Exception.php';
            throw new AdapterException(get_class($this) ." is not allowed to be serialized");
        }
        $this->_connection = false;
        return array_keys(array_diff_key(get_object_vars($this), array('_connection'=>false)));
    }

    /**
     * called when object is getting unserialized
     *
     * @return void
     */
    public function __wakeup()
    {
        if ($this->_autoReconnectOnUnserialize == true) {
            $this->_connect();
        }
    }

    //以下是PDO
	
    /**
     * Creates a PDO DSN for the adapter from $this->_config settings.
     *
     * @return string
     */
    protected function _dsn()
    {
        // baseline of DSN parts
        $dsn = $this->_config;

        // don't pass the username, password, charset, persistent and driver_options in the DSN
        unset($dsn['username']);
        unset($dsn['password']);
        unset($dsn['options']);
        unset($dsn['charset']);
        unset($dsn['persistent']);
        unset($dsn['driver_options']);

        // use all remaining parts in the DSN
        foreach ($dsn as $key => $val) {
            $dsn[$key] = "$key=$val";
        }

        return 'mysql:' . implode(';', $dsn);
    }

    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    public function isConnected()
    {
        return ((bool) ($this->_connection instanceof PDO));
    }

    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function closeConnection()
    {
        $this->_connection = null;
    }

    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement with placeholders.
     * @param array $bind An array of data to bind to the placeholders.
     * @return PDOStatement
     */
    public function prepare($sql)
    {
        $this->_connect();
        
        $stmt = $this->_connection->prepare($sql);
        $stmt->setFetchMode($this->_fetchMode);
        return $stmt;
    }

    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT column.
     *
     * As a convention, on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2), this method forms the name of a sequence
     * from the arguments and returns the last id generated by that sequence.
     * On RDBMS brands that support IDENTITY/AUTOINCREMENT columns, this method
     * returns the last value generated for such a column, and the table name
     * argument is disregarded.
     *
     * On RDBMS brands that don't support sequences, $tableName and $primaryKey
     * are ignored.
     *
     * @param string $tableName   OPTIONAL Name of table.
     * @param string $primaryKey  OPTIONAL Name of primary key column.
     * @return string
     */
    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        $this->_connect();
        return $this->_connection->lastInsertId();
    }

    /**
     * Special handling for PDO query().
     * All bind parameter names must begin with ':'
     *
     * @param string|Select $sql The SQL statement with placeholders.
     * @param array $bind An array of data to bind to the placeholders.
     * @return PDOStatement
     * @throws PDOException.
     */
    public function query($sql, $bind = array())
    {
        if (empty($bind) && $sql instanceof Select) {
            $bind = $sql->getBind();
        }

        if (is_array($bind)) {
            foreach ($bind as $name => $value) {
                if (!is_int($name) && !preg_match('/^:/', $name)) {
                    $newName = ":$name";
                    unset($bind[$name]);
                    $bind[$newName] = $value;
                }
            }
        }

        //try {省略throw-catch-rethrow块，直接抛出PDOException
            // connect to the database if needed
	        $this->_connect();
	
	        // is the $sql a Select object?
	        if ($sql instanceof Select) {
	            if (empty($bind)) {
	                $bind = $sql->getBind();
	            }
	
	            $sql = $sql->assemble();
	        }
	
	        // make sure $bind to an array;
	        // don't use (array) typecasting because
	        // because $bind may be a Expr object
	        if (!is_array($bind)) {
	            $bind = array($bind);
	        }
	        
	        //将结果缓冲当中的结果集读出来
	        Statement::flush();
	
	        // prepare and execute the statement with profiling
	        $stmt = $this->prepare($sql);
	        
	        // 由于取消了Statement，因此将Profiler的控制代码移动到这里
	        // 由于所处的程序位置，省略了$qp->start(),简化了$qp->bindParams()的相关代码
	    	if ($this->_profiler === false) {
	            $stmt->execute($bind);
	        }
	        else{
	        	$q = $this->_profiler->queryStart($sql);
	        	
		        $qp = $this->_profiler->getQueryProfile($q);
		        if ($qp->hasEnded()) {
		            $q = $this->_profiler->queryClone($qp);
		            $qp = $this->_profiler->getQueryProfile($q);
		        }
		        $qp->bindParams($bind);
		
		        $stmt->execute($bind);
		
		        $this->_profiler->queryEnd($q);
	        }
	        
	        // return the results embedded in the prepared statement object
	        $stmt->setFetchMode($this->_fetchMode);
	        return $stmt;
        //} catch (PDOException $e) {
            /**
             * @see StatementException
             */
            //require_once 'Zend/Db/Statement/Exception.php';
            
        	//throw new StatementException($e->getMessage(), $e->getCode(), $e);
        //}
    }

    /**
     * Executes an SQL statement and return the number of affected rows
     *
     * @param  mixed  $sql  The SQL statement with placeholders.
     *                      May be a string or Select.
     * @return integer      Number of rows that were modified
     *                      or deleted by the SQL statement
     * @throws PDOException
     */
    public function exec($sql)
    {
        if ($sql instanceof Select) {
            $sql = $sql->assemble();
        }

        //try {省略throw-catch-rethrow块，直接抛出PDOException
            $affected = $this->getConnection()->exec($sql);

            if ($affected === false) {
                $errorInfo = $this->getConnection()->errorInfo();
                /**
                 * @see AdapterException
                 */
                //require_once 'Zend/Db/Adapter/Exception.php';
                throw new AdapterException($errorInfo[2]);
            }

            return $affected;
        //} catch (PDOException $e) {
            /**
             * @see AdapterException
             */
            //require_once 'Zend/Db/Adapter/Exception.php';
        //    throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        //}
    }

    /**
     * Begin a transaction.
     */
    protected function _beginTransaction()
    {
        $this->_connection->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    protected function _commit()
    {
        $this->_connection->commit();
    }

    /**
     * Roll-back a transaction.
     */
    protected function _rollBack() {
        $this->_connection->rollBack();
    }

    /**
     * Set the PDO fetch mode.
     *
     * @todo Support FETCH_CLASS and FETCH_INTO.
     *
     * @param int $mode A PDO fetch mode.
     * @return void
     * @throws AdapterException
     */
    public function setFetchMode($mode)
    {
        $this->_fetchMode = $mode;
    }
    
    // 以下是PDO_Mysql

    /**
     * Keys are UPPERCASE SQL datatypes or the constants
     * INT_TYPE, BIGINT_TYPE, or FLOAT_TYPE.
     *
     * Values are:
     * 0 = 32-bit integer
     * 1 = 64-bit integer
     * 2 = float or decimal
     *
     * @var array Associative array of datatypes to values 0, 1, or 2.
     */
    protected $_numericDataTypes = array(
        INT_TYPE    => INT_TYPE,
        BIGINT_TYPE => BIGINT_TYPE,
        FLOAT_TYPE  => FLOAT_TYPE,
        'INT'                => INT_TYPE,
        'INTEGER'            => INT_TYPE,
        'MEDIUMINT'          => INT_TYPE,
        'SMALLINT'           => INT_TYPE,
        'TINYINT'            => INT_TYPE,
        'BIGINT'             => BIGINT_TYPE,
        'SERIAL'             => BIGINT_TYPE,
        'DEC'                => FLOAT_TYPE,
        'DECIMAL'            => FLOAT_TYPE,
        'DOUBLE'             => FLOAT_TYPE,
        'DOUBLE PRECISION'   => FLOAT_TYPE,
        'FIXED'              => FLOAT_TYPE,
        'FLOAT'              => FLOAT_TYPE
    );

    /**
     * Creates a PDO object and connects to the database.
     *
     * @return void
     * @throws PDOException
     */
    protected function _connect()
    {
        if ($this->_connection) {
            return;
        }

        if (!empty($this->_config['charset'])) {
            $initCommand = "SET NAMES '" . $this->_config['charset'] . "'";
            $this->_config['driver_options'][PDO::MYSQL_ATTR_INIT_COMMAND] = $initCommand;
        }

        //以下来自PDO::_connect
        // if we already have a PDO object, no need to re-connect.
        if ($this->_connection) {
            return;
        }

        // get the dsn first, because some adapters alter the $_pdoType
        $dsn = $this->_dsn();

        // create PDO connection
        if ($this->_profiler) $q = $this->_profiler->queryStart('connect', Profiler::CONNECT);

        // add the persistence flag if we find it in our config array
        if (isset($this->_config['persistent']) && ($this->_config['persistent'] == true)) {
            $this->_config['driver_options'][PDO::ATTR_PERSISTENT] = true;
        }

        //try {省略throw-catch-rethrow块，直接抛出PDOException
            $this->_connection = new PDO(
                $dsn,
                $this->_config['username'],
                $this->_config['password'],
                $this->_config['driver_options']
            );

            if ($this->_profiler) $this->_profiler->queryEnd($q);

            // set the PDO connection to perform case-folding on array keys, or not
            $this->_connection->setAttribute(PDO::ATTR_CASE, $this->_caseFolding);
            
            // always use exceptions.
            $this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 为了query buffer而强制增加的选项
            $this->_connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            
        //} catch (PDOException $e) {
            /**
             * @see AdapterException
             */
            //require_once 'Zend/Db/Adapter/Exception.php';
            //throw new AdapterException($e->getMessage(), $e->getCode(), $e);
        //}
    }
}
