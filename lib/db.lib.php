<?php
/**
 *  An abstract of DB operation ;
 *  
 *	Features
 *		1. Multi-DB supports(MySQL , MongoDB ...);
 *      2. NoSQL-style;
 *      3. Result Enhance;
 *      4. More Security : auto escape name and value;
 *
 *	Limitations
 *		1. (for MySQL) : Primary key is required , and auto increasement , and named 'id' ;
 *
 *	Connection
 *		1. Permanent Connection
 *		2. Instance : close connection after DB class
 *		3. Query : close connection after per query
 *
 *	Usage 
 *
 *      // tcp
 *		$config = 'dbtype://user:password@host:port/?charset=utf8';
 *      // socket
 *      $config = 'dbtype://user:password@localhost/?socket=/path/to/mysql.sock&charset=utf8';
 *
 *      // oo class
 *		$db = DB::Instance($config);
 *      // op methods
 *      $db = db($config);
 *		
 *      // select single line ;
 *		$result = $db->select($dbname,$tablename,$where = array(),$options=NULL);
 *      // select multi lines ;
 *      $result = $db->selects($dbname,$tablename,$where = array(),$options=NULL);
 *      // 
 *		$result = $db->count($dbname,$tablename,$where = array());
 *		$result = $db->increase($dbname,$tablename,array('field1'=>1,'field2'=>-1),$where);
 *
 *		$result = $db->insert($dbname,$tablename,$value=array());
 *		$result = $db->inserts($dbname,$tablename,$values=array(array));
 *		$result = $db->update($dbname,$tablename,$value,$where);
 *		$result = $db->replace($dbname,$tablename,$value,$where);
 *		$result = $db->delete($dbname,$tablename,$where);
 *		$result = $db->query($sql);
 *      
 *      // meta operations
 *		$db->showDBs($options=NULL);
 *		$db->showTables($dbname,$options=NULL);
 *	    $db->showColumns($dbname,$tablename,$options=NULL);
 *		$db->showIndexes($dbname,$tablename,$options=NULL);
 *		$db->showTriggers($dbname,$tablename,$options=NULL);
 *		$db->showViews($dbname,$options=NULL);
 *		$db->showProcedures($dbname,$options=NULL);
 *		$db->showVariables($dbname,$options=NULL);
 *		
 *		$db->createDB($dbname,$params=NULL);
 *		$db->createTable($dbname,$tablename,$params);
 *		$db->createView($name,$params);
 *		$db->createIndex($name,$params);
 *
 *		$db->dropDB($dbname,$options=NULL);
 *		$db->dropTable($dbname,$tablename,$options=NULL);
 *		$db->dropView($dbname,$viewname,$options=NULL);
 *		$db->dropIndex($dbname,$indexname,$options=NULL);
 *
 *
 */


/**   数据配置信息
 *     
 *     @param $configs = array(
 *         'db' => array(
 *             'key' => 'mysql://user:pass@host:port/db?encoding=utf8',
 *         ),
 *         
 *     );
 *
 *     @usage 
 *     // 设置配置(merge实现)
 *     db_config($conf);
 *     // 读取配置
 *     $conf = db_config();
 */
function db_config ($config=NULL) {
    return DB::Config ($config);
}

// 返回最后一条
function db_backlog ($idx = 0) {
    return DB::Backlog();
}

/**   获取数据库
 *     
 */
function db ($server) {
    return DB::Factory($server);
}

/**   测试连接 
 *     
 *     @return 1:连接可用; 0:连接不可用;
 */
function db_ping ($server) {
    
}

/**   选择操作
 *     
 */
function db_select ($server, $db_table, $fields = NULL, $where = NULL, $order = NULL, $limit = NULL) {
    $db = db($server); if(!$db) return FALSE;
    return (func_num_args()==3) ? $db->select($db_table, $fields) : $db->select($db_table, $fields, $where, $order, $limit);
}

function db_count($server, $db_table, $where = NULL) {
    $db = db($server); if(!$db) return FALSE;
    return $db->count($db_table, $where);
}

function db_insert($server, $db_table, $data = NULL) {
    $db = db($server); if(!$db) return FALSE;
    return $db->insert($db_table, $data);
}

function db_update($server, $db_table, $data = NULL , $where = NULL) {
    $db = db($server); if(!$db) return FALSE;
    return $db->update($db_table, $data, $where);
}

/**   删除操作 (为防止误操作，不支持全表删除，请使用db_truncate)
 *     
 */
function db_delete($server, $db_table, $where = NULL) {
    $db = db($server); if(!$db) return FALSE;
    return $db->delete($db_table, $where);
}

function db_truncate ($conf, $db_table) {
    // todo
}


/** DB static class provides methods for database's config
 *  
 */
class DB {
    
    /**   DB config */
    private static $_Config = array(
        'db' => array(
            //'example' => 'mysql://user:pass@host:port/?charset=utf8',
        ),
        'log' => array(
            //'mode' => 'echo', // echo | file
            //'path' => '/tmp/',
            //'split' => 'Ymd',
            //'types' => array('sql', 'error'),
        ),
    );
        
    /**   Set(merge) or get config */
    public static function Config ($config=NULL) {
        // return config
        if(!$config) { return self::$_Config; }
        // config must be array
        if(!is_array($config)) { return FALSE; }
        // merge config
        self::$_Config = array_merge_recursive(self::$_Config, $config);
        // return 
        return TRUE;
    }

    /**   Log */
    private static $_Backlog = NULL;
    
    /**   Set or get log  */
    public static function Backlog ($msg = NULL, $type = 'error') {
        // get log
        if($msg==NULL) return self::$_Backlog;
        // format msg
        $message = "{$type}:{$msg}";
        // set log
        self::$_Backlog = "{$type}:{$msg}";
        // check condition
        if(!isset(self::$_Config['log']['types']) || !is_array(self::$_Config['log']['types']) || !in_array($type, self::$_Config['log']['types'])) return FALSE;
        // check mode
        switch(self::$_Config['log']['mode']) {
            case 'echo' : echo $message, "\n"; break;
            case 'file' : break;
            default : break;
        }
        return TRUE;
    }

    /** server pool */
    private static $_Pool = array();

    /**   Singleton Pattern to create server */
    public static function Factory ($server) {
        // check server params
        if(empty($server)) { self::Backlog('DB::Factory params server cannot be empty'); return FALSE; }
        if(!isset(self::$_Config['db'][$server])) { self::Backlog('DB::Factory server key "'.$server.'" not set'); return FALSE; }
        // check pool
        if(isset(self::$_Pool[$server])) { self::Backlog('DB::Factory get server from pool', 'trace'); return self::$_Pool[$server]; };
        // parse config
        $configs = parse_url(self::$_Config['db'][$server]);
        // 'On seriously malformed URLs, parse_url() may return FALSE.'
        if ( $configs === FALSE ) { self::Backlog('DB::Factory params format error : '.json_encode(self::$_Config['db'][$server])); return FALSE; }
        // rename scheme to type
        $configs['type'] = $configs['scheme']; unset($configs['scheme']);
        // make server as host:port
        $configs['server'] = $configs['host']; if(isset($configs['port'])) { $configs['server'] .= ':'.$configs['port'];}
        // make path as db
        if(isset($configs['path'])) { if($configs['path']!='/') { $configs['db'] = substr($configs['path'], 1); } unset($configs['path']); };
        // make query string to array and merge to config
        if(isset($configs['query'])) { parse_str($configs['query'], $queries); $configs = array_merge($configs, $queries); unset($configs['query']); }
        // factory method to produce instance
        switch ($configs['type']) {
            case 'mysql' : $db = new MySQLNormal($configs); break;
            case 'mysqli' : $db = new MySQLImprove($configs); break;
            case 'mysqlpdo' : $db = new MySQLPDO($configs); break;
            default : self::Backlog('DB::Factory server type not support : '.$configs['type']); $db = FALSE; break;
        }
        self::$_Pool[$server] = $db;
        return $db;
    }

}

/** Database Base Class
 */
class DBBase {

    /** 配置参数
    private static $_Params = array(
        'log_dir' => 'db' ,
        'log_split' => 'Ymd' ,
    ); */

    protected function _backlog ($msg, $type = 'error') {
        DB::Backlog($msg, $type);
    }
}

/* MySQLBase */
class MySQLBase extends DBBase {

	protected $_config = array(
        'type' => 'mysql',
        'host' => '127.0.0.1' ,
        'port' => 3306 ,
        'server' => '127.0.0.1:3306',
        'user' => 'root',
        'pass' => '******',
        'charset' => 'utf8',
    );

	function __construct ($config) {
        if(empty($config)) return;
		$this->_configs = array_merge($this->_config ,$config);
	}

	public function showDBs ($options = NULL) {
		$sql = "SHOW DATABASES";
		$data = $this->_query($sql);
		if($data===FALSE) { return FALSE; }
		$result = array(); foreach($data as $d) { $result[] = $d['Database']; }
		return $result;
	}

	public function showTables ($dbname) {
		$dbname = $this->_escapeName($dbname);
		$sql = "SHOW TABLES FROM {$dbname}"; // SHOW FULL TABLES 
		$data = $this->_query($sql);
		if ( $data === FALSE ) { return FALSE ;}
		$result = array();
		foreach( $data as $d ) { $result[] = $d['Tables_in_' .  str_replace('`','',$dbname) ]; }
		return $result ;
	}

    public function select ($db_table, $fields = NULL, $where = NULL, $order = NULL, $limit = NULL) {
        // when only two param , consider as select * from table where id = $id
        $one = (func_num_args()==2);
        if($one) { $where = $fields; $fields = NULL; }
        
        $db_table = $this->_escapeName($db_table);
        $fields = $this->_buildFields($fields);
        $where = $this->_buildWhere($where);
        $sql = "SELECT {$fields} FROM {$db_table} WHERE {$where}";
        
        if($one) {
            $sql .= " LIMIT 1";
        }
        else {
            if (!empty($order)) {
                if(is_string($order)) $order = explode(',', $order);
                if(is_array($order)) {
                    $ords = array();
                    foreach( $order as $ord ) {
                        $sc = ($ord[0]=='-') ? 'DESC' : 'ASC';
                        $ord = str_replace(array('+','-'), array('',''), $ord);
                        $ords[] = $this->_escapeName($ord).' '.$sc;
                    }
                    $order = implode(', ', $ords);
                    $sql .= ' ORDER BY ' . $order;
                }
            }
            if ($limit) {
                if (is_array($limit) && isset($limit[1])) $limit = $limit[0] . ', ' . $limit[1];
                $sql .= ' LIMIT '.$limit;
            }
        }
        $result = $this->_query($sql) ; if($result===FALSE) return FALSE;

        if($one) {
            $result = isset($result[0]) ? $result[0] : NULL;
        }
        return $result;
	}

	public function count ($db_table, $where) {
		$db_table = $this->_escapeName($db_table);
		$where = $this->_buildWhere($where);
		$sql = "SELECT COUNT(*) AS C FROM {$db_table} WHERE {$where}";
		$result = $this->_query($sql);
		if ($result) { $result = $result[0]['C']; }
		return $result ;
	}

	public function insert ($db_table, $data) {
		$db_table = $this->_escapeName($db_table);
		list($fields, $values) = $this->_buildInsert($data);
		$sql = "INSERT INTO {$db_table} ( {$fields} ) VALUES ( {$values} )";
		return $this->_query($sql);
	}
	
	public function update ($db_table, $data, $where) {
		$db_table = $this->_escapeName($db_table);
		$set = $this->_buildSet($data);
		$where = $this->_buildWhere($where);
		$sql = "UPDATE {$db_table} SET {$set} WHERE {$where}";
		return $this->_query($sql);
	}

    public function replace ($db_table, $data) {
        $db_table = $this->_escapeName($db_table);
		list( $fields , $values ) = $this->_buildInsert( $data );
		$sql = "REPLACE INTO {$db_table} ( {$fields} ) VALUES ( {$values} )";
		return $this->_query($sql);
    }

	public function increase ($db_table, $data, $where) {
		$db_table = $this->_escapeName($db_table);
		$increase = $this->_buildIncrease($data);
		$where = $this->_buildWhere($where);
		$sql = "UPDATE {$db_table} SET {$increase} WHERE {$where}";
		return $this->_query($sql);
	}

	public function delete ($db_table, $where) {
        // delete all not support , use truncate instead
        if(empty($where)) {
            $this->_backlog('DB dont support delete all rows , use truncate instead ');
            return FALSE;
        }
		$db_table = $this->_escapeName($db_table);
        $where = $this->_buildWhere($where);
		$sql = "DELETE FROM {$db_table} WHERE {$where}";
		return $this->_query($sql);
	}

    public function truncate ($db_table) {}

    private function _query ($sql) {

        if (empty($sql)) {
            $this->_backlog('DB query sql is empty', 'error');
            return FALSE ;
        }
        else {
		    $this->_backlog($sql, 'sql');
        }

		if (preg_match('/^SELECT|SHOW/i' , $sql)) {
			$crud = 'read';
		}
		elseif (preg_match('/^INSERT/i' , $sql)) {
			$crud = 'insert';
		}
		elseif (preg_match('/^UPDATE|DELETE|REPLACE|CREATE/i' , $sql)) {
			$crud = 'update';
		}
		else {
            $this->_backlog('DB query sql is not support : '.$sql, 'error');
            return FALSE;
		}

        return $this->query($sql, $crud);
    }

	private function _buildFields ($fields) {
		if ( is_array($fields) ) {
            $result = array();
            foreach( $fields as $key => $field ) {
                // supports 'AS'
                if ( is_int($key) ) {
                    $result[] = $this->_escapeName($field);
                }
                else {
                    $result[] = $this->_escapeName($key) . ' AS ' . $this->_escapeName($field);
                }
            }
            return implode(',',$result);
        }
        else {
            return '*';
        }
	}

	private function _buildWhere ($where) {
		if (empty($where)){
            return '1';
        }
        // id = conditions
        elseif (is_scalar($where)) { // todo: supports raw sql
            return $this->_escapeName('id').' = \''.$this->_escapeValue($where). '\'';
        }
        // map hash to conditions
        elseif (is_array($where) && !isset($where[0])) {
            $result = array();
            foreach( $where as $key => $val ) {
                if ( is_int( $val ) ) {
                    $result[] = $this->_escapeName($key) . ' = ' . $val ;
                }
                elseif (is_string($val)) {
                    $result[] = $this->_escapeName($key) . ' = \'' . $this->_escapeValue($val) . '\'' ;
                }
                elseif (is_null($val)) {
                    $result[] = $this->_escapeName($key) . ' IS NULL' ;
                }
                elseif( is_array($val) && !isset($val[0]) ) {
                    // LIKE
                    if(isset($val['like'])) {
                        $result[] = $this->_escapeName($key) . ' LIKE \'%' . $this->_escapeValue($val['like']) . '%\'';
                    }
                    // >
                    if(isset($val['>'])) {
                        $result[] = $this->_escapeName($key) . ' > '. $this->_escapeValue($val['>']) . '';
                    }
                    // <
                    if(isset($val['<'])) {
                        $result[] = $this->_escapeName($key) . ' < '. $this->_escapeValue($val['<']) . '';
                    }
                    // todo : between
                }
                elseif ( is_array( $val ) ) {
                    $_temp = array();
                    foreach( $val as $v ) {
                        if ( is_int( $v ) ) {
                            $_temp[] = $this->_escapeName($key) . ' = ' . $v ;
                        }
                        elseif ( is_string( $v ) ) {
                            $_temp[] = $this->_escapeName($key) . ' = \'' . $this->_escapeValue($v) . '\'' ;
                        }
                    }
                    $result[] = '(' . implode(' OR ', $_temp) . ')';
                }
            }
            return implode(' AND ', $result);
        }
        else {
            return '1';
        }
	}

	private function _buildInsert ($data) {
		if ( empty($data) ) {
			return array('','');
		}
		$fields = array();
		$values = array();
		foreach( $data as $key => $val ) {
			$fields[] = $this->_escapeName($key);
			$values[] = is_int($val) ? $val : '\'' . $this->_escapeValue($val) . '\'' ;
		}
		return array(implode(' , ' , $fields),implode(' , ' , $values));
	}

	private function _buildSet ($set) {
		$result = array();
		foreach( $set as $key => $val ) {
			if ( is_int ($val) ) {
				$result[] = $this->_escapeName($key) . ' = ' . $val ;
			}
			else {
				$result[] = $this->_escapeName($key) . ' = \'' . $this->_escapeValue($val) . '\'' ;
			}
		}
		return implode(',',$result);
	}
	
	private function _buildIncrease ($data) {
		if ( empty( $data ) ) {
			return '1=1';
		}
		$result = array();
		foreach($data as $key => $val) {
			if ( !is_int($val) ) { 
				continue; 
			}
			$result[] = $this->_escapeName($key) . ' = ' . $this->_escapeName($key) . ( $val > 0 ? ' + ' : ' - ' ) . abs($val) ;
		}
		return implode(' , ' , $result);
	}

    /** Escape Name 
     *  
     *  @example
     *      'table' => '`table`'
     *      '`table`' => '`table`'
     *      'db.table' => '`db`.`table`'
     *      '`db`.table' => '`db`.`table`'
     */
	private function _escapeName ($name) {
        $result = FALSE;
        if(strpos($name, '.') === FALSE) {
		    $result = ( strpos($name , '`') === FALSE ) ? "`{$name}`" : $name ;
        }
        else {
            list($db, $table) = explode('.', $name);
            $db = ( strpos($db , '`') === FALSE ) ? "`{$db}`" : $db ;
            $table = ( strpos($table , '`') === FALSE ) ? "`{$table}`" : $table ;
            $result = $db.'.'.$table ;
        }
        return $result;
	}

    /** Escape Value (avoid SQL-Injection) */
	private function _escapeValue ($value) {
		return addslashes($value) ;
	}

    public function ping () {
        // by sub class 
        return FALSE;
	}

    public function query ($sql, $crud) {
        // by sub class 
        return FALSE;
	}
}

/** MySQL query by mysql_xxxx functions */
class MySQLNormal extends MySQLBase {
    
    public function ping () {
        $config = $this->_configs;
        $con = mysql_connect($config['server'], $config['user'], $config['pass']) ;
        $result = mysql_ping($con);
        if($con) mysql_close($con);
        return $result;
    }

    public function query ($sql, $crud) {
        $config = $this->_configs;
        $con = mysql_connect($config['server'], $config['user'], $config['pass']) ;
        if ($con===FALSE ) { $this->_backlog('MySQL connect : [' . mysql_errno($con) . '] : ' . mysql_error($con)); return FALSE ; }

        if (isset($config['charset'])) mysql_set_charset($config['charset'], $con);
        if (isset($config['db'])) {
            $result = mysql_select_db($config['db'], $con); 
            if(!$result) {
                $this->_backlog('MySQL->query() mysql_select_db '.$config['db'].' error');
                return FALSE;
            }
        }

        $data = mysql_query($sql, $con);

        if ($data === FALSE) { $this->_backlog('MySQL query : ' . $sql . ' ' . mysql_errno($con) . ':' . mysql_error($con)); return FALSE ; }
        
        if ( $crud == 'read' ) { 
            $result =  array();
            if(is_resource($data)) { // why ? when insert with a exist key
                while ($row = mysql_fetch_assoc($data)) $result[] = $row;
                mysql_free_result($data);
            }
        } 
        elseif ( $crud == 'insert' ) {
            $result = mysql_insert_id($con);
            // if no insert id , return affected rows;
            if($result==0) $result = mysql_affected_rows($con);
        } 
        else {
            $result = mysql_affected_rows($con);
        }

        if($con) mysql_close($con);
        return $result ;
	}

}

/** MySQL query by mysqli_xxxx functions */
class MySQLImprove extends MySQLBase {
    public function query ($sql, $crud) {
        $config = $this->_configs;
        $con = new mysqli($config['host'], $config['user'], $config['pass'], '', $config['port']);
        if ($con->connect_error) {
            $this->_backlog('MySQLi->query() : ['.$con->connect_errno.'] : '.$con->connect_error, 'error');
            return FALSE ;
        }
        if (isset( $config['charset'])) $con->set_charset($config['charset']);
        if (isset( $config['db'])) { 
            $result = $con->select_db($config['db']);
            if(!$result) {
                $this->_backlog('MySQLi->query select_db '.$config['db'].' error');
                return FALSE;
            }
        }
        $data = $con->query($sql);
        if ($data === FALSE) {
            $this->_backlog('MySQLi->query() : ' . $sql . ' ' . $con->errno . ':' . $con->error); 
            return FALSE ;
        }
        else {
            if ($crud == 'read') {
                $result = array();
                while($row=$data->fetch_assoc()) $result[] = $row;
                $data->close();
            } 
            else if ($crud == 'insert') {
                $result = $con->insert_id;
                // if no insert id , return affected rows;
                if(!$result) $result = $con->affected_rows;
            } 
            else {
                $result = $con->affected_rows;
            }
        }
        
        if ($con) mysqli_close($con);
        return $result ;
    }
}

/** MySQL query by PDO class */
class MySQLPDO extends MySQLBase {

    public function query ($sql, $crud) {
        $config = $this->_configs;
        // DSN/Data Source Name (http://www.php.net/manual/en/ref.pdo-mysql.connection.php)
        $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';';
        if (isset($config['db'])) $dsn .= 'dbname='.$config['db'];

        try {
            $con = new PDO($dsn, $config['user'], $config['pass']);
            if(!empty($config['charset'])) $con->query('SET NAMES '.$config['charset']);
            if ($crud=='read') {
                $result = $con->query($sql);
                if($result===FALSE) {
                    $this->_backlog('MySQLPDO query() : ' . $sql . ' ' . $con->errorCode() . ':' . implode('',$con->errorInfo())); 
                    return FALSE;
                }
                $result->setFetchMode(PDO::FETCH_ASSOC);
                $result = $result->fetchAll();
            }
            else if ($crud=='insert') {
                $result = $con->exec($sql);
                // if no insert id , return affected rows;
                $last_id = $con->lastInsertId();
                if($last_id) $result = $last_id;
            }
            else if ($crud=='update') {
                $result = $con->exec($sql);
            }
            return $result;
        }
        catch (PDOException $e) {
            $this->_backlog($e->getMessage());
            return FALSE;
        }
    }
}