<?
///Used for getting data from utilities (called in the controller) to templates
/**
The philosophy:
	Any server request which would normally engage the need for subdata collection (ie, not just outputting static html or images) is considered a "Page"
	Pages have common behaviors.  This class serves to:
		Provide a generic variable for data (like a $global, but context to being Page specific) to be used across the incontiguous parts (model, template, etc)
		Provide a standard input variable which can operate when run on command line and through apache
		Provide backend to frontend handling of messages (errors)
	
	Four types of messages are recognized:
		Errors: Things that prevent movement forward
		Warnings: Things tthat may later prevent movement forward
		Notices: Things to optmize movement forward
		Success: Indicator of movement forward
*/
class Page{
	static $in;///<a combination of get and post using special handling of repeated tokens
	static $messages = array();
	static $data;
	const defaultContext = 'default';
	static function error($message,$name=null,$context=self::defaultContext){
		self::message($message,$name,array('error',$context));
	}
	static function success($message=null,$name=null,$context=self::defaultContext){
		if(!$message){
			$message = Display::pageTitle().' success';
		}
		self::message($message,$name,array('success',$context));
	}
	static function notice($message,$name=null,$context=self::defaultContext){
		self::message($message,$name,array('notice',$context));
	}
	static function warning($message,$name=null,$context=self::defaultContext){
		self::message($message,$name,array('warning',$context));
	}
		
	//checks if there is a field error for a given field in a given context
	static function fieldError($field,$context=self::defaultContext){
		$context = Arrays::stringArray($context);
		array_unshift($context,'error');
		return self::getMessages($context,$field);
	}
	///checks if there was an error.  Defaults to checking all contexts
	static function errors($context=null){
		$context = Arrays::stringArray($context);
		array_unshift($context,'error');
		return self::getMessages($context);
	}
	///get messages based on context and name
	static function getMessages($context,$name=null){
		$messages = array();
		foreach(self::$messages as $message){
			if(!Arrays::orderedSubset($context,$message['context'])){
				continue;
			}
			if($name !== null && $name != $message['name']){
				continue;
			}
			$messages[] = $message;
		}
		return $messages;
	}
	
	static function message($message,$name,$context){
		self::$messages[] = array('context'=>Arrays::stringArray($context),'name'=>$name,'content'=>$message);
	}
	
	/**
	@param	fields	array	array with keys being fields and values being rules to apply to fields.  See appyFilterValidateRules for rule syntax
	@param	fieldValues	array	the array to be operated on by the filters and validators.  Defaults toe Page::$in
		Page::filterAndValidate(
			array(
				'email' => rules
				'loginName' => rules
			)
		);
	*/
	
	static function filterAndValidate($fields,$filterArrays=true,$context=self::defaultContext,&$fieldValues=null){
		if(!$fieldValues){
			///concept: if fieldValues is defaulting to Page::$in, there should not be code relying on the Page $in variable being an exact reflection of input after this call
			$fieldValues = &Page::$in;
		}
		if($filterArrays){
			foreach($fields as $field=>$rules){
				if(is_array($fieldValues[$field])){
					FieldIn::makeString($fieldValues[$field]);
				}
			}
		}
		
		foreach($fields as $field=>$rules){
			
			$continue = self::applyFilterValidateRules($field, $fieldValues[$field],$rules,$context);
			if(!$continue){
				break;
			}
		}
	}
	/**
	
	
	@param	rules	string or array	
		Rules can be an array of rules, or a string separated by "," for each rule.  
		Each rule can be a string or an array.  
		As a string, the rule should be in one of the following forms:
				"f:name|param1;param2" indicates InputFilter method
				"v:name|param1;param2" indicates InputValidator function
				"g:name|param1;param2" indicates global scoped function
				"class:name|param1,param2,param3" indicates static method "name: of class "class" 
				"p:name|param1,param2,param3" PageTool function
				"name" replaced by FieldIn fieldType of the same name
		As an array, the rule function part (type:method) is the first element, and the parameters to the function part are the following elements.  Useful if function arguments contain commas or semicolons.  Ex:
			array('type:method','arg1','arg2','arg3')
		
		The "type:method" part can be prefixed with "!" to indicate there should be a break on error, and no more rules for that field should be applied
		The "type:method" part can be prefixed with "!!" to indicate there should be a break on error and no more rules for any field should be applied
		
		If array, first part of rule is taken as string with the behavior above without parameters and the second part is taken as the parameters; useful for parameters that include commas or semicolons or which aren't strings
		
		Examples for rules:
			1: 'v:email|bob.com,customClass:method|param1;param2',
			2: array('v:email|bob.com','customClass:method|param1;param2'),
			3: array(array('v:email','bob.com'),array('customClass:method','param1','param2')),
	*/
	static function applyFilterValidateRules($field, &$value, $rules, $context){
		$originalRules = $rules;
		$rules = Arrays::stringArray($rules);
		for($i=0;$i<count($rules);$i++){
			$rule = $rules[$i];
			$params = array(&$value);
			if(is_array($rule)){
				$callback = array_shift($rule);
				$params2 = &$rule;
			}else{
				list($callback,$params2) = explode('|',$rule);
				
				if($params2){
					$params2 = explode(';',$params2);
				}
			}
			///merge field value param with the user provided params
			if($params2){
				Arrays::mergeInto($params,$params2);
			}
			
			//used in combination with !, like ?! for fields that, if not empty, should be validated, otherwise, ignored.
			$ignoreError = false;
			if(substr($callback,0,1) == '?'){
				$callback = substr($callback,1);
				$ignoreError = true;
			}
			
			if(substr($callback,0,2) == '!!'){
				$callback = substr($callback,2);
				$superBreak = true;
			}
			if(substr($callback,0,1) == '!'){
				$callback = substr($callback,1);
				$break = true;
			}
			
			list($type,$method) = explode(':',$callback);
			if(!$method){
				$method = $type;
				$type = '';
			}
			
			if(!$method){
				Debug::quit('Failed to provide method for input handler on field: '.$field, 'Rules:', $rules);
			}
			
			try{
				switch($type){
					case 'f':
						call_user_func_array(array('InputFilter',$method),$params);
					break;
					case 'v':
						call_user_func_array(array('InputValidator',$method),$params);
					break;
					case 'p':
						call_user_func_array(array('PageTool',$method),$params);
					break;
					case 'g':
						call_user_func_array($method,$params);
					break;
					case '':
						//get new rules and start over from current position
						if(!FieldIn::$fieldTypes[$method]){
							Debug::quit('Unknown standard field on field '.$field,'Rule:',$rule);
						}
						$newRules = Arrays::stringArray(FieldIn::$fieldTypes[$method]);
						if($i + 1 < count($rules)){
							$newRules = array_merge($newRules,array_slice($rules,$i + 1));
						}
						$rules = $newRules;
						$i = -1;
					break;
					default:
						call_user_func_array(array($type,$method),$params);
					break;
				}
			}catch(InputException $e){
				//add error to messages
				if(!$ignoreError){
					self::error($e->getMessage(),$field,$context);
				}
				
				//super break will break out of all fields
				if($superBreak){
					return false;
				}
				//break will stop validators for this one field
				if($break){
					break;
				}
			}
		}
		return true;
	}
	static function saveMessagesCode($data){
		return substr(sha1($_SERVER['HTTP_USER_AGENT'].$data.Config::$x['cryptKey']),0,10);
	}
	///puts messages in cookie for next pageload
	static function saveMessages($targetPage=null){
		$cookie['data'] = serialize(self::$messages);
		$cookie['code'] = self::saveMessagesCode($cookie['data']);
		if($targetPage){
			$cookie['target'] = $targetPage;
		}
		
		Cookie::set('_PageMessages',serialize($cookie));
	}
}

class InputException extends Exception{}


//+	Handle GET and POST variables{
$input['get'] = $_SERVER['QUERY_STRING'];
//can cause script to hang (if no stdin), so don't run if in script unless configured to
if(!Config::$x['inScript'] || Config::$x['scripgGetsStdin']){
	//multipart forms can either result in 1. input being blank or 2. including the upload.  In case 1, post vars can be taken from $_POST.  In case 2, need to avoid putting entire file in memory by parsing input
	if(!preg_match('@multipart/form-data@',$_SERVER['CONTENT_TYPE'])){
		$input['post'] = file_get_contents('php://input');
		$input['post'] = $input['post'] ? $input['post'] : file_get_contents('php://stdin');
	}elseif($_POST){
		$input['post'] = http_build_query($_POST);
	}
}
if(Config::$x['pageIn']){
	foreach(Config::$x['pageIn'] as $v){
		$array[] = $input[$v];
	}
	if($array){
		Page::$in = Http::parseQuery(Arrays::implode('&',$array),Config::$x['pageInPHPStyle']);
	}
}
//+	}

//+	Handle COOKIE system messages{
if($_COOKIE['_PageMessages']){
	do{
		$cookie = @unserialize($_COOKIE['_PageMessages']);
		if(is_array($cookie)){
			$code = Page::saveMessagesCode($cookie['data']);
			if($cookie['code'] == $code){
				if(is_array($cookie['target'])){
					if(!array_diff($cookie['target'],RequestHandler::$urlTokens)){
						Page::$messages = @unserialize($cookie['data']);
					}else{
						//not on right page, so break
						break;
					}
				}else{
					Page::$messages = @unserialize($cookie['data']);
				}
			}
		}
		Cookie::remove('_PageMessages');
	}while(false);
}
//+	}

///setup shortcut to Page::$data
global $page;
if(!$page){
	$page = new stdClass;
}
Page::$data =& $page;
