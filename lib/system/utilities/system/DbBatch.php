<?
///for getting and putting batches
class DbBatch implements Iterator{
	public $position, $step;
	public $sql;
	public $currentRows = array();
	
	public $db;
	function __construct($db=null){
		if(!$db){
			$db = Db::$connections[Db::$primary];
		}
		$this->db = $db;
		$this->position = 0;
	}
	/// used to translate static calls to the primary database instance
	static function __callStatic($name,$arguments){
		$class = __class__;
		$that = new $class();
		return call_user_func_array(array($that,$name),$arguments);
	}
	/// used to translate calls to non existance methods to _method
	function __call($name,$arguments){
		return call_user_func_array(array($this,$name),$arguments);
	}
	
	
//+	for getting batches {
	/*overloaded such that arguments can be
		@param	db	db object to use
		@param	step	step to batch
		@param	sql	sql
	*/
	private function get($step,$sql){
		if(is_a($step,'Db')){
			$class = __class__;
			$that = new $class($step);
			return call_user_func_array(array($that,'get'),array_slice(func_get_args(),1));
		}
		$this->sql = $this->db->getOverloadedSql(2,func_get_args());
		$this->step = $step;
		return $this;
	}	
	function rewind(){
		$this->position = 0;
	}

	function current(){
		return $this->currentRows;
	}

	function key(){
		return $this->position;
	}

	function next(){
		$this->position++;
	}

	function valid() {
		$limit = "\nLIMIT ".$this->position * $this->step.', '.$this->step;
		$this->currentRows = $this->db->rows($this->sql.$limit);
		return (bool)$this->currentRows;
	}
//+	}
//+	for putting batches {
	///insert multiple rows
	private function put($table,$rows){
		if($rows){
			return $this->db->intos('INSERT',$table,$rows);
		}
	}
	private function putIgnore($table,$rows){
		if($rows){
			return $this->db->intos('INSERT IGNORE',$table,$rows);
		}
	}
	private function putReplace($table,$rows){
		if($rows){
			return $this->db->intos('REPLACE',$table,$rows);
		}
	}
	///currently just executes insertUpdate for all rows
	private function putUpdate($table,$rows){
		if($rows){
			foreach($rows as $row){
				$this->db->insertUpdate($table,$row);
			}
		}
	}
//+	}
}
