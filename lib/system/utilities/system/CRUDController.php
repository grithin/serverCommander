<?
///Create Read Update Delete general class

/**
Static b/c there appears little reason to have more than one on a single page
*/
class CRUDController{
	static function getId(){
		$id = abs(Page::$in['id']);
		if(!$id){
			$tokens = RequestHandler::$urlTokens;
			krsort($tokens);
			foreach($tokens as $token){
				$id = abs($token);
				if($id){
					break;
				}
			}
		}
		if($id){
			Page::$data->id = $id;
			PageTool::$id = $id;
			return $id;
		}
	}
	static function __callStatic($fn,$args){
		if(in_array($fn,array('create','update','delete','read'))){
			return self::handle(array($fn),$args[0]);
		}
	}
	
	static $attempted;
	static $called;
	/**
	@param	commands	list of commands to look for in input for running (will only run one, order by priority)
	@param	default	the command to use if none of the provided were found.  Will be run regardless of whether corersponding input command found
	*/
	static function handle($commands=array(),$default='read'){
		self::$attempted = self::$called = array();
		foreach($commands as $command){
			if(Page::$in['_cmd_'.$command]){
				$return = self::callFunction($command);
				if($return === null || $return === false){
					continue;
				}
				return new CRUDResult($command,$return,Page::$in['_cmd_'.$command]);
			}
		}
		if($default && !in_array($default,self::$attempted)){
			$return = self::callFunction($default,Page::$in['_cmd_'.$command]);
			return new CRUDResult($default,$return);
		}
		return new CRUDResult('',null);
	}
	static function getFunction($command,$subcommand=null){
		if(!$subcommand){
			$subcommand = Page::$in['_cmd_'.$command];
		}
		if(method_exists('PageTool',$command.'_'.$subcommand)){
			return array('PageTool',$command.'_'.$subcommand);
		}elseif(method_exists('PageTool',$command)){
			return array('PageTool',$command);
		}elseif(isset(PageTool::$model) && PageTool::$model['table'] && method_exists('CRUDModel',$command)){
			return array('CRUDModel',$command);
		}
		return false;
	}
	//callbacks applied at base for antibot behavior
	static function callFunction($command,$subcommand=null,$error=false){
		self::$attempted[] = $command;
		$function = self::getFunction($command);
		if($function){
			self::$called[] = $command;
			$return = call_user_func($function);
			return $return;
		}
		if($error){
			Page::error('Unsupported command');
		}
	}
}
/*Note, the handling of a result in a standard way would potentially require standard action names, item titles, directory structure, id parameters, etc.  So, just make it easy to handle, don't actually handle*/
class CRUDResult{
	function __construct($type,$return,$subType=null){
		$this->type = $type;
		$this->return = $return;
		$this->subType = $subType;
		$this->attempted = CRUDController::$attempted;
		$this->called = CRUDController::$called;
		if($type){
			$this->$type = $return;
		}
	}
}