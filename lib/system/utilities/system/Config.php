<?
/// used for dealing with framework instance configurations
class Config{
	///array of configuration options including defaults
	static public $x;
	
	///gets the defaults and deals with special config variable syntax
	static function get(){
		self::defaults();
		foreach(self::$x as $k=>&$v){
			if(is_array($v)){
				foreach($v as &$v2){
					if(is_string($v2)){
						self::applyConfigSyntax($v2);
					}
				}
			}elseif(is_string($v)){
				self::applyConfigSyntax($v);
			}
		}
		date_default_timezone_get(Config::$x['timezone']);
	}
	static function applyConfigSyntax(&$config){
		preg_match_all('@\:\:([^:]+)\:\:@',$config,$matches,PREG_SET_ORDER);
		if($matches){
			foreach($matches as $match){
				$config = preg_replace('@'.preg_quote($match[0]).'@',Tool::pregQuoteReplaceString(Config::$x[$match[1]]),$config);
			}
		}
	}
	
	///sets defaults of configuration options
	static function defaults(){
		$defaults = array(
				'resourceNotFound' => '',
				
				'horribleProgrammer'=>true,
				
				'throwErrors'=>false,
				'errorHandler'=>'Debug::handleError',
				'logLocation'=>'info/log',
				
				'errorsLogged'=>E_ALL & ~ E_NOTICE & ~ E_STRICT,
				'systemFolder'=>self::$x['systemLocation'].'/system/',
				'instanceFolder'=>self::$x['instanceLocation'].'/instance/',
				'systemPublicFolder'=>self::$x['systemLocation'].'/public/system/',
				'instancePublicFolder'=>self::$x['instanceLocation'].'/public/instance/',
				'displayFolder'=>self::$x['instanceLocation'].'/instance/display/',
				'templateFolder'=>self::$x['instanceLocation'].'/instance/display/templates/',
				
				'urlInstanceFileToken'=>'public',
				'urlSystemFileToken'=>'tpfile',
				
				'sessionExpiry' => '-1 year',
				'sessionFolder' => self::$x['instanceLocation'].'/instance/storage/sessions/',
				
				'sessionUseDb' => false,
				'sessionDbTable' => '',
				
				
				'showPreHooks' => array(),
				'showPostHooks' => array(),
				
				'cryptCipher' => MCRYPT_RIJNDAEL_128,
				'cryptMode' => MCRYPT_MODE_ECB,
				'cryptKey' => 'php.framework.thoughtpush.com'
				
			);
		foreach($defaults as $k=>$v){
			self::setDefault($k,$v);
		}
		if(substr(self::$x['logLocation'],0,1) != '/'){
			self::$x['logLocation'] = self::$x['instanceFolder'].self::$x['logLocation'];
		}
	}
	///sets config default if not already set
	static function setDefault($key,$value){
		if(!isset(self::$x[$key])){
			self::$x[$key] = $value;
		}
	}
	static function userFileLocation($file,$defaultLocation){
		if(substr($file,0,1) != '/'){
			$file = Config::$x['instanceFolder'].$defaultLocation.'/'.$file;
		}
		//since file base ensured (not purely relative), can run through absolutePath function
		return Tool::absolutePath($file);
	}
	///loads a user file, using a relative path if file doesn't start with "/"
	static function loadUserFile($file,$defaultLocation = '.',$globalize=null){
		if($file){
			$file = self::userFileLocation($file,$defaultLocation);
			Files::req($file,$globalize);
		}
	}
	///loads user files using self::loadUserFile
	static function loadUserFiles($files,$defaultLocation='.',$globalize=null){
		if(is_array($files)){
			foreach($files as $file){
				self::loadUserFile($file,$defaultLocation,$globalize);
			}
		}elseif($files){
			self::loadUserFile($files,$defaultLocation,$globalize);
		}
	}
}