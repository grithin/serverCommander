<?


class Cache{
	/// reference to primary PDO instance
	public $cacher;	
	/// latest result set returning from $db->query()
	public $result;				// latest result set returns from $db->query()
	/// Name of the primary database connection
	static $primary = 0;
	/// named Db class instances
	static $connections = array();
	
	///prevent public instantiation
	private function __construct(){}
	
	///make connection
	/**
	@param	connection	name of the connection
	*/
	function connect(){
		$this->cacher = new Memcached;
		foreach($this->connectionInfo as $v){
			if(!$this->cacher->addserver($v[0],$v[1],$v[2])){
				Debug::quit('Failed to add cacher',$v);
			}
		}
		$this->cacher->set('on',1);
		if(!$this->cacher->get('on')){
			Debug::quit('Failed to get cache');
		}
	}
	///lazy load a new db instance; uses singleton base on name.
	/**
	@param	connectionInfo	array:
		@verbatim
	 [
		[ip/name,port,weight]
	]
	*/
	static function initialize($connectionInfo,$name=0){
		if(!isset(self::$connections[$name])){
			//set primary if no connections except this one
			if(!self::$connections){
				self::$primary = $name;
			}
			//add connection
			$class = __class__;
			self::$connections[$name] = new $class();
			self::$connections[$name]->connectionInfo = $connectionInfo;
		}
		return self::$connections[$name];
	}
	/// used to translate static calls to the primary database instance
	static function __callStatic($name,$arguments){
		$that = self::$connections[self::$primary];
		if(!$that->cacher){
			$that->connect();
		}
		if(method_exists($that,$name)){
			return call_user_func_array(array($that,$name),$arguments);
		}
		return call_user_func_array(array($that->cacher,$name),$arguments);
	}
	function __call($name,$arguments){
		if(!$this->cacher){
			$this->connect();
		}
		if(method_exists($this,$name)){
			return call_user_func_array(array($this,$name),$arguments);
		}
		return call_user_func_array(array($this->cacher,$name),$arguments);
	}
	///updateGet for getting and potentially updating cache
	/**
	allows a single client to update a cache while concurrent connetions just use the old cache (ie, prevenut multiple updates).  Useful on something like a public index page with computed resources - if 100 people access page after cache expiry, cache is only re-updated once, not 100 times.
	
	Perhaps open new process to run update function
	
	@param	name	name of cache key
	@param	updateFunction	function to call in case cache needs updating or doesn't exist
	@param	optionTimes	
			[
				update => relative time after which to update
				timeout => update timeout in seconds (optional)
				expiry => time after update, where if update doesn't happen, cache expires (in seconds) (optional)
			]
	@param additional	any additinoal args are passed to the updateFunction
	*/
	private function uGet($name,$updateFunction,$optionTimes){
		$times = $this->cacher->get($name.':|:update:times',null,$casToken);
		if($times){
			if(time() > $times['nextUpdate']){
				if($optionTimes['timeout']){
					$times['nextUpdate'] += $optionTimes['timeout'];
				}else{
					$times = self::uTimes($optionTimes);
				}
				if($this->cacher->cas($casToken,$name.':|:update:times',$times,$times['nextExpiry'])){
					return self::uSet($name,$updateFunction,$optionTimes,array_slice(func_get_args(),3));
				}
			}
			$value = $this->cacher->get($name);
			if($this->cacher->getResultCode() == Memcached::RES_SUCCESS){
				return $value;
			}
		}
		return self::uSet($name,$updateFunction,$optionTimes,array_slice(func_get_args(),3));
	}
	private function uSet($name,$updateFunction,$optionTimes,$args){
		$times = self::uTimes($optionTimes);
		$value = call_user_func_array($updateFunction,$args);
		$this->cacher->set($name,$value,$times['nextExpiry']);
		$this->cacher->set($name.':|:update:times',$times,$times['nextExpiry']);
		return $value;
	}
	///generates all times necessary for uget functions
	private function uTimes($optionTimes){
		$updateTime = i()->Time($optionTimes['update']);
		$updateTimeUnix = $updateTime->unix();
		if($optionTimes['expiry']){
			$expiryTimeUnix = $updateTime->relative('+'.$optionTimes['expiry'].' seconds')->unix();
			$times['nextExpiry'] = $expiryTimeUnix - time();
		}
		
		$times['nextUpdate'] = $updateTimeUnix;
		return $times;
	}
}
