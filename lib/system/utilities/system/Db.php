<?
///see warning
/**Class details:
	- Class employs lazy loader(Connection only made when needed) and simgleton(Only one db object instantiated) patterns.
	- Class holds db instances in static $con allowing one db instance to use multiple connections: might be useful in master & slave database
	- @warning Class sets sql_mode to ansi sql if mysql db to allow interroperability with postgres.	As such, double quotes " become table and column indicators, ` become useless, and single quotes are used as the primary means to quote strings
	- @note Most of the querying methods are overloaded; there are two forms of possible input:
		- Form 1:	simple sql string; eg "select * from bob where bob = 'bob'"
		- Form 2: 
			@verbatim
			@param	table	the table to be used
			@param	select	a select array.	See the Db::select function
			@endverbatim
	- most private functions are still callable due to __call and __callStatic
*/
class Db{
	/// reference to primary PDO instance
	public $db;	
	/// latest result set returning from $db->query()
	public $result;				// latest result set returns from $db->query()
	/// Name of the primary database connection
	static $primary = 0;
	/// named Db class instances
	static $connections = array();
	/// last SQL statement
	static $lastSql;			
	
	///prevent public instantiation
	private function __construct(){}
	
	///make connection
	/**
	@param	connection	name of the connection
	*/
	function connect(){
		if($this->connectionInfo['dsn']){
			$dsn = $this->connectionInfo['dsn'];
		}else{
			$dsn = $this->connectionInfo['driver'].':dbname='.$this->connectionInfo['database'].';host='.$this->connectionInfo['host'];
		}
		$this->db = new PDO($dsn,$this->connectionInfo['user'],$this->connectionInfo['password']);
		if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)=='mysql'){
			$this->query('SET SESSION sql_mode=\'ANSI\'');
			#$this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		}
	}
	///lazy load a new db instance; uses singleton base on name.
	/**
	@param	connectionInfo	array:
		@verbatim
array(
	driver => ...,
	database => ...,
	host => ...,
	user => ...,
	password ...
		@endverbatim
	@param	name	name of the connetion
	*/
	static function initialize($connectionInfo,$name=0,$override=false){
		if(!isset(self::$connections[$name]) || $override){
			//set primary if no connections except this one
			if(!self::$connections){
				self::$primary = $name;
			}
			//add connection
			$class = __class__;
			self::$connections[$name] = new $class();
			$connectionInfo['name'] = $name;
			self::$connections[$name]->connectionInfo = $connectionInfo;
		}
		return self::$connections[$name];
	}

	/// used to translate static calls to the primary database instance
	static function __callStatic($name,$arguments){
		//allow _func to be treated as func
		if(!method_exists(__class__,$name)){
			$name = '_'.$name;
		}
		return call_user_func_array(array(self::$connections[self::$primary],$name),$arguments);
	}
	/// used to translate calls to non existance methods to _method
	function __call($name,$arguments){
		//allow _func to be treated as func
		if(!method_exists(__class__,$name)){
			$name = '_'.$name;
		}
		return call_user_func_array(array($this,$name),$arguments);
	}

	/// returns escaped string with quotes.	Use on values to prevent injection.
	/**
	@param	v	the value to be quoted
	*/
	private function quote($v){
		if(!$this->db){
			$this->connect($this->connectionInfo);
		}
		return $this->db->quote($v);
	}
	
	/// perform database query
	/**
	@param	sql	the sql to be run
	@return the PDOStatement object
	*/
	private function query($sql){
		if(!$this->db){
			$this->connect($this->connectionInfo);
		}
		if($this->result){
			$this->result->closeCursor();
		}
		self::$lastSql = $sql;
		$this->result = $this->db->query($sql);
		if((int)$this->db->errorCode()){
			$error = $this->db->errorInfo();
			$error = "--DATABASE ERROR--\n".' ===ERROR: '.$error[0].'|'.$error[1].'|'.$error[2]."\n ===SQL: ".$sql;
			Debug::throwError($error);
		}
		return $this->result;
	}

	/// Used internally.	Checking number of arguments for functionality
	protected function getOverloadedSql($expected, $actual){
		$overloaded = count($actual) - $expected;
		if($overloaded > 0){
			//$overloaded + 1 because the expected $sql is actually one of the overloading variables
			$overloaderArgs = array_slice($actual,-($overloaded + 1));
			return call_user_func_array(array($this,'select'),$overloaderArgs);
			
		}else{
			return end($actual);
		}
	}
	
	/// query returning a row
	/**See class note for input
	@warning "limit 1" is appended to the sql input
	@return	a single row, or, if one column, return that columns value
	*/
	private function row(){
		$sql = $this->getOverloadedSql(1,func_get_args());
		#function implies only 1 retured row
		if(!preg_match('@[\s]*show@i',$sql)){
			$sql .= "\nLIMIT 1";
		}
		if($res = $this->query($sql)){
			if($res->columnCount()==1){
				return	$res->fetchColumn();
			}
			return $res->fetch(PDO::FETCH_ASSOC);
		}
	}

	/// query returning multiple rows
	/**See class note for input
	@return	a sequential array of rows
	*/
	private function rows($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$res2 = array();
		if($res = $this->query($sql)){
			$i = 0;
			while($row=$res->fetch(PDO::FETCH_ASSOC)){
				foreach($row as $k=>$v){
					$res2[$i][$k]=$v;
				}
				$i++;
			}
		}
		return $res2;
	}
	
	/// query returning a column
	/**
	See class note for input
	@return	array where each element is the column value of each row.  If multiple columns are in the select, just uses the first column
	*/
	private function column($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$res = $this->query($sql);
		while($row=$res->fetch(PDO::FETCH_NUM)){$res2[]=$row[0];}
		if(!is_array($res2)){
			return array();
		}
		return $res2;
	}

	/// query returning number indexed array
	/**See class note for input
	@return	row as numerically indexed array for potential use by php list function
	*/
	private function enumerate($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$sql .= "\nLIMIT 1";
		return $this->query($sql)->fetch(PDO::FETCH_NUM);
	}
	
	
	/// query check if there is a match
	/**See class note for input
	@return	true if match, else false
	*/
	private function check($table,$where){
		$sql = $this->select($table,$where,'1');
		return $this->row($sql) ? true : false;
	}
	
	///get the id of some row, or make it if the row doesn't exist
	/**
	@param	additional	additional fields to merge with where on insert
	*/
	private function id($table,$where,$additional=null){
		$sql = $this->select($table,$where,'id');
		$id = $this->row($sql);
		if(!$id){
			if($additional){
				$where = Arrays::merge($where,$additional);
			}
			$id = $this->insert($table,$where);
		}
		return $id;
	}
	
	
	/// query returning a column with keys
	/**See class note for input
	@param	key	the column key to be used for each element.	If they key is an array, the first array element is taken as the key, the second is taken as the mapped value column
	@return	array where one column serves as a key pointing to either another column or another set of columns
	*/
	
	private function columnKey($key,$sql){
		$arguments = func_get_args();
		array_shift($arguments);
		$rows = call_user_func_array(array($this,'rows'),$arguments);
		if(is_array($key)){
			return Arrays::compileKey($rows,$key['key'] ? $key['key'] : $key[0], $key['value'] ? $key['value'] : $key[1]);
		}else{
			return Arrays::compileKey($rows,$key);
		}
	}
	
	/// internal use.	Key to value formatter (used for where clauses and updates)
	/**
	@param	kvA	various special syntax is applied:
		- normally, sets key = to value, like "key = 'value'" with the value escaped
		- if "?" is in the key, the part after the "?" will server as the "equator", ("bob?<>"=>'sue') -> "bob <> 'sue'"
		- if key starts with ":", value is not escaped
			- if value is "null", on where prefix with "is".	
			- if value = null (php null), set string to null
		- if value = null, set value to unescaped "null"
	@param	type	1 = where, 2 = update
	*/
	private function ktvf($kvA,$type=1){		
		foreach($kvA as $k=>$v){
			if(strpos($k,'?')!==false){
				preg_match('@(^[^?]+)\?([^?]+)$@',$k,$match);
				$k = $match[1];
				$equator = $match[2];
			}else{
				$equator = '=';
			}
			
			if($k[0]==':'){
				$k = substr($k,1);
				if($v == 'null' || $v === null){
					if($type == 1){
						$equator = 'is';
					}
					$v = 'null';
				}
			}elseif($v === null){
				if($type == 1){
					$equator = 'is';
				}
				$v = 'null';
			}else{
				$v = $this->quote($v);
			}
			$k = '"'.$k.'"';
			#Fields like user.id to "user"."id"
			if(strpos($k,'.')!==false){
				$k = implode('"."',explode('.',$k));
			}
			$kvtA[] = $k.' '.$equator.' '.$v;
		}
		return $kvtA;
	}

	/// construct where clause from array or string
	/**
	@param	where	various forms:
		- either plain sql statement "bob = 'sue'"
		- single identifier "fj93" translated to "id = 'fj93'"
		- key to value array.	See self::ktvf()
	@return	where string
	@note if the where clause does not exist, function will just return nothing; this generally leads to an error
	*/
	private function where($where){
		if(is_array($where)){
			$where = implode("\n\tAND ",$this->ktvf($where));
		}elseif(!$where  && !Tool::isInt($where)){
			return;
		}elseif(!preg_match('@[ =<>]@',$where)){//ensures where is not long where string (bob=sue, bob is null), but simple item.
			if((string)(int)$where != $where){
				$where = $this->quote($where);
			}
			$where = 'id = '.$where;
		}
		return "\nWHERE ".$where;
	}
	private function intos($command,$table,$rows){
		//use first row as template
		list($keys) = self::kvp($rows[0]);
		$insertRows = array();
		foreach($rows as $row){
			list(,$values) = self::kvp($row);
			$insertRows[] = '('.implode(',',$values).')';
		}
		Db::query($command.' INTO '.$table.' ('.implode(',',$keys).")\t\nVALUES ".implode(',',$insertRows));
	}
	
	/// Key value parser
	private function kvp($kvA){
		foreach($kvA as $k=>$v){
			if($k[0]==':'){
				$k = substr($k,1);
				if($v === null){
					$v = 'null';
				}
			}elseif($v === null){
				$v = 'null';
			}else{
				$v = $this->quote($v);
			}
			$keys[] = '"'.$k.'"';
			$values[] = $v;
		}
		return array($keys,$values);
	}
	
	/// Key value formatter (used for insert like statements)
	/**
	@param	kva	array('key' => 'value',...)	special syntax is applied:
		- normally, sets (key) values (value) with the value escaped
		- if key starts with ":", value is not escaped
		- if value = null (php null), set string to null
	*/
	private function kvf($kvA){
		list($keys,$values) = self::kvp($kvA);
		return ' ('.implode(',',$keys).")\t\nVALUES (".implode(',',$values).') ';
	}

	
	/// Insert into a table
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	*/
	private function insert($table,$kvA){
		return $this->into('INSERT',$table,$kvA);
	}
	/// Insert with a table and ignore if duplicate key found
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	@return	insert row id
	*/
	private function insertIgnore($table,$kvA){
		return $this->into('INSERT IGNORE',$table,$kvA);
	}
	/// insert into table; on duplicate key update
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	@param	update	either plain sql or null; if null, defaults to updating all values to $kvA input
	@return	see Db::into
	*/
	private function insertUpdate($table,$kvA,$update=null){
		if(!$update){
			$update .= implode(', ',$this->ktvf($kvA,2));
		}elseif(is_array($update)){
			$update = implode(', ',$this->ktvf($update,2));
		}
		return $this->into('INSERT',$table,$kvA,"\nON DUPLICATE KEY UPDATE\n".$update);
	}

	/// replace on a table
	/**
	@param	table	table to replace on
	@param	kva	see self::kvf() function
	@return	see Db::into
	*/
	private function replace($table,$kvA){
		return $this->into('REPLACE',$table,$kvA);
	}

	/// internal use; perform insert into [called from in(), inUp()]
	/**
	@return row id or row count.  In the case of an single insert update, will return the row id if the update actually changed something.  In the case of multiple affected rows, will be a row count.
	*/
	private function into($type,$table,$kvA,$update=''){
		$res = $this->query($type.' INTO "'.$table.'"'.$this->kvf($kvA).$update);
		if($this->db->lastInsertId()){
			return $this->db->lastInsertId();
		}elseif($kvA['id']){
			return $kvA['id'];
		}else{
			return $res->rowCount();
		}
	}

	/// perform update, returns number of affected rows
	/**
	@param	table	table to update
	@param	update	see self::ktvf() function
	@param	where	see self::where() function
	@return	row count
	*/
	private function update($table,$update,$where){
		if(!$where){
			Debug::throwError('Unqualified update is too risky.  Use 1=1 to verify');
		}
		$vf=implode(', ',$this->ktvf($update,2));
		$res = $this->query('UPDATE "'.$table.'" SET '.$vf.$this->where($where));
		return $res->rowCount();
	}
	
	/// perform delete
	/**
	@param	table	table to replace on
	@param	where	see self::where() function
	@return	row count
	@note as a precaution, to delete all must use $where = '1 = 1'
	*/
	private function delete($table,$where){
		if(!$where){
			Debug::throwError('Unqualified delete is too risky.  Use 1=1 to verify');
		}
		return $this->query('DELETE FROM "'.$table.'"'.$this->where($where))->rowCount();
	}
	
	/// perform a count and select rows; doesn't work with all sql
	/**
	Must have "ORDER" on separate and single line
	Must have "LIMIT" on separate line
	@return	array($count,$results)
	*/
	private function countAndRows($countLimit,$sql){
		$sql = $this->getOverloadedSql(2,func_get_args());
		$countSql = $sql;
		//get sql limit if exists from last part of sql
		$limitRegex = '@\sLIMIT\s+([0-9,]+)\s*$@i';
		if(preg_match($limitRegex,$countSql,$match)){
			$limit = $match[1];
			$countSql = preg_replace($limitRegex,'',$countSql);
		}
		
		//order must be on single line or this will not work
		$orderRegex = '@\sORDER BY[\t ]+([^\n]+)\s*$@i';
		if(preg_match($orderRegex,$countSql,$match)){
			$order = $match[1];
			$countSql = preg_replace($orderRegex,'',$countSql);
		}
		
		$countSql = array_pop(preg_split('@[\s]FROM[\s]@i',$countSql,2));
		if($countLimit){
			$countSql = "SELECT COUNT(*)\n FROM (\nSELECT 1 FROM \n".$countSql."\nLIMIT ".$countLimit.') t ';
		}else{
			$countSql = "SELECT COUNT(*)\nFROM ".$countSql;
		}
		$count = $this->row($countSql);
		$results = $this->rows($sql);
		return array($count,$results);
	}
	///generate sql
	/**
	Ex: 
		- row('select * from user where id = 20') vs row('user',20);
		- rows('select name from user where id > 20') vs sRows('user',array('id?>'=>20),'name')
	@param	from	table, array of tables, or from statement
	@param	where	see self::$where()
	@param	columns	list of columns; either string or array.	"*" default.
	@param	order	order by columns
	@param	limit	result limit
	@return sql string
	@note	this function is just designed for simple queries
	*/
	private function select($from,$where,$columns='*',$order=null,$limit=null){
		if(is_array($from)){
			$from = '"'.implode('", "',$from).'"';
		}elseif(strpos($from,' ') === false){//don't quote a from statement
			$from = '"'.$from.'"';
		}
		if(is_array($columns)){
			$columns = '"'.implode('", "',$columns).'"';
		}
		$select = 'SELECT '.$columns."\nFROM ".$from.$this->where($where);
		if($order){
			if(!is_array($order)){
				$order = Arrays::stringArray($order);
			}
			$orders = array();
			foreach($order as $part){
				$part = explode(' ',$part);
				if(!$part[1]){
					$part[1] = 'ASC';
				}
				//'"' works with functions like "sum(cost)"
				$orders[] = '"'.$part[0].'" '.$part[1];
			}
			$select .= "\nORDER BY ".implode(',',$orders);
		}
		if($limit){
			$select .= "\nLIMIT ".$limit;
		}
		return $select;
	}
	
	//get database table column information
	private function columnsInfo($table){
		$columns = array();
		if($this->connectionInfo['driver'] == 'mysql'){
			$rows = $this->rows('describe "'.$table.'"');
			foreach($rows as $row){
				$column =& $columns[$row['Field']];
				$column['type'] = self::parseColumnType($row['Type']);
				$column['limit'] = self::parseColumnLimit($row['Type']);
				$column['nullable'] = $row['Null'] == 'NO' ? false : true;
				$column['autoIncrement'] = preg_match('@auto_increment@',$row['Extra']) ? true : false;
			}
		}
		return $columns;
	}
	//take db specific column type and translate it to general
	static function parseColumnType($type){
		if(preg_match('@int@i',$type)){//int,bigint
			return 'int';
		}elseif(preg_match('@decimal@i',$type)){
			return 'decimal';
		}elseif(preg_match('@float@i',$type)){
			return 'float';
		}elseif(in_array($type,array('datetime','date'))){
			return $type;
		}elseif(in_array($type,array('varchar','text'))){
			return 'text';
		}
	}
	static function parseColumnLimit($type){
		preg_match('@\(([0-9,]+)\)@',$type,$match);
		if($match[1]){
			$limit = explode(',',$match[1]);
			return $limit;
		}
	}
}