<?
///Used to handle requests by parsing the uri path piece by piece and applying rules
/**
Various rule calling logic:
	- start from base token and work to end token: bob/bill/sue bob -> bill -> sue
	- look for rules.php from base till end or until self::$stopRules set to true
		- Each time the parser runs through all rules with no success, it will try to move one level up by using the current token as the next directory and looking for a "rules.php" until it finds no more directories to traverse.
	- Each time a rule is parsed and matches, the parser reparses all rules unless there are negation flags


@note	if you get confused about what is going on with the rules, you can print out both self::$matchedRules and self::$ruleFiles at just about any time
@note	if you want to fabricate a request, just modify $_SERVER['REQUEST_URI']; for instance, if you want to run the framework through console, just make sure to set that variable in a script resembling the index.php
Various controller calling logic:
	- start from base token and work to end token: bob/bill/sue bob -> bill -> sue
	- look for controller.php from base till end or until self::$stopControls set to true
	- look for TOKEN.php on final token
	- if not on last token and no more directories to traverse, look for CURRENT_TOKEN.php

RequestHandler rules are not meant to change $_GET or $_POST variables, and if such is to be done, it is not done by this file.  


*/
class RequestHandler{
	static $stopControls;///<stops more controllers from being called; use within controller file
	static $stopRules;///<stops more rules from being called; use within rule file
	static $urlTokens = array();///<an array of url path parts; rules can change this array
	static $realUrlTokens = array();///<the original array of url path parts
	static $parsedUrlTokens = array();///<used internally
	static $unparsedUrlTokens = array();///<used internally
	static $rules;///<used internally
	static $ruleFiles;///<files containing rules that have been included, along with rules.  Potentially useful for debugging
	static $matchedRules;///<list of rules that were matched
	static $urlBase;///<the string, untokenized, path of the url.  Use $_SERVER to get actual url
	static $currentToken;///<used internally; serves as the item compared on token compared rules
	///parses url, routes it, then calls off all the controllers until no more or told to stop
	static function handle(){
		self::parseRequest();
		
		//url corresponds to public file directory, provide file
		if(self::$urlTokens[0] == Config::$x['urlInstanceFileToken']){
			self::sendFile('instance');
		}elseif(self::$urlTokens[0] == Config::$x['urlSystemFileToken']){
			self::sendFile('system');
		}
		
		self::routeRequest();
		Config::loadUserFiles(Config::$x['requestHandlers'],'requestHandling');
		
		
//+	load controllers and page utilities{
		if(!self::$stopControls){
			//after this following line, self::$urlTokens has no more influence on routing.  Add to self::$unparsedUrlTokens if you want add controllers from controllers
			self::$unparsedUrlTokens = self::$urlTokens;
			
			//First, see if there is a main site controller
			files::inc(config::$x['instanceFolder'].'controllers/controller.php');
			
			//page utility levels
			$pageUtillityLevel = self::getPageUtilityLevel(Config::$x['instanceFolder'].'utilities/section/');
			$pageDisplayUtillityLevel = self::getPageUtilityLevel(Config::$x['instanceFolder'].'display/utilities/section/');
			
			//get the section and page controllers
			while(self::$unparsedUrlTokens && !self::$stopControls){
				self::$parsedUrlTokens[] = self::$currentToken = array_shift(self::$unparsedUrlTokens);
//+		include page utilities, if at appropriate level {
				$level = count(self::$parsedUrlTokens);
				if($pageUtillityLevel && $level == $pageUtillityLevel){
					files::incOnce(Config::$x['instanceFolder'].'utilities/section/'.implode('/',self::$parsedUrlTokens).'.php');
				}
				if($pageDisplayUtillityLevel && $level == $pageDisplayUtillityLevel){
					files::incOnce(Config::$x['instanceFolder'].'display/utilities/section/'.implode('/',self::$parsedUrlTokens).'.php');
				}
//+		}
				
				//load the controller
				if(!self::getTokenFile('controllers','controller',array('page'))){
					if(Config::$x['pageNotFound']){
						Config::loadUserFiles(Config::$x['pageNotFound'],'controllers',array('page'));
						exit;
					}else{
						Debug::throwError('Request handler encountered unresolvable token at controller level.'."\nCurrent token: ".self::$currentToken."\nTokens parsed".print_r(self::$parsedUrlTokens,true));
					}
				}
			}
		}
//+	}
	}
	///get the first (most specific to page) token based utility (if config page utilities is on)
	/**@return tokens at which utility was found*/
	private static function getPageUtilityLevel($base){
		$tokens = self::$urlTokens;
		while($tokens){
			if(is_file($base.implode('/',$tokens).'.php')){
				return count($tokens);
			}
			array_pop($tokens);
		}
	}
	///internal use. initial breaking apart of url
	private static function parseRequest(){
		self::$realUrlTokens = explode('?',$_SERVER['REQUEST_URI'],2);
		self::tokenize(self::$realUrlTokens[0]);
		
		//urldecode tokens.  Note, this can make some things relying on domain path info for file path info insecure
		foreach(self::$urlTokens as &$token){
			$token = urldecode($token);
		}
		unset($token);
		
		//Potentially, the urlTokens will change according to routes, but the real ones may be referenced
		self::$realUrlTokens = self::$urlTokens;
	}
	/// internal use. tokenizes url
	/** splits url path on "/"
	@param	urlDir	str	path part of url string
	*/
	private static function tokenize($urlDir){
		self::$urlTokens = Tool::explode('/',$urlDir);
		self::$urlBase = $urlDir;
	}
	///internal use. Parses all current files and rules
	/** adds file and rules to ruleFiles and parses all active rules in current file and former files
	@param	file	str	file location string
	*/
	private static function parseRules($file){
		global $rules;
		self::$ruleFiles[] = array('file'=>$file,'rules'=>$rules);
		unset($rules);
		
		//lc = lower case
		$lcUrlBase = strtolower(self::$urlBase);
		$lcCurrentToken = strtolower(self::$currentToken);
		
		foreach(self::$ruleFiles as $kF=>&$file){
			if($file['ignore']){
				$file['ignore'] = false;
				continue;
			}
			if($file['rules']){
				foreach($file['rules'] as $kR=>&$rule){
					unset($matched);
					$flags = $rule[2] ? explode(',',$rule[2]) : array();
					
					//parse flags for determining match string
					if(!$rule['match']){
						if(in_array('regex',$flags)){
							$rule['regex'] = true;
							$rule['match'] = Tool::pregDelimit($rule[0]);
							if(in_array('insensitive',$flags)){
								$rule['match'] .= 'i';
							}
							
						}else{
							if(in_array('insensitive',$flags)){
								$rule['match'] = strtolower($rule[0]);
							}else{
								$rule['match'] = $rule[0];
							}
						}
						if(in_array('token',$flags)){
							$rule['token'] = true;
						}
					}
					
					
					//determine subject string
					if($rule['token']){
						$subject = self::$currentToken;
					}else{
						$subject = self::$urlBase;
					}
					
					
					//test match
					if($rule['regex']){
						if(preg_match($rule['match'],$subject)){
							$matched = true;
						}
					}else{
						if($rule['match'] == $subject){
							$matched = true;
						}
					}
					
					if($matched){
						self::$matchedRules[] = $rule;
						
						if($rule['regex']){
							$replacement = preg_replace($rule['match'],$rule[1],$subject);
						}else{
							$replacement = $rule[1];
						}
						//remake url with replacement
						self::tokenize($replacement);
						self::$parsedUrlTokens = null;
						self::$unparsedUrlTokens = self::$urlTokens;
						
						//apply parse flag
						if(in_array('ignore',$flags)){
							unset($file['rules'][$kR]);
						}elseif(in_array('last',$flags)){
							unset(self::$ruleFiles[$kF]);
						}elseif(in_array('veryLast',$flags)){
							self::$unparsedUrlTokens = array();
						}elseif(in_array('nextFile',$flags)){
							$file['ignore'] = true;
						}
						return;
					}
				}
			}
		}
		unset($rules);	
	}
	///internal use. Gets files and then applies rules for routing
	private static function routeRequest(){
		if(Files::inc(Config::$x['instanceFolder'].'requestHandling/rules.php',array('rules'))){
			global $rules;
			self::parseRules(Config::$x['instanceFolder'].'requestHandling/rules.php');
		}
		while(self::$unparsedUrlTokens && !self::$stopRules){
			self::$parsedUrlTokens[] = self::$currentToken = array_shift(self::$unparsedUrlTokens);
			$file = self::getTokenFile('requestHandling','rules',array('rules'));
			if(!$file){
				#no more rules
				break;
			}
			self::parseRules($file);
		}
		self::$parsedUrlTokens = null;
	}
	///internal use. Gets a file based on next token in the unparsedUrlTokens variable
	private static function getTokenFile($prefix,$defaultName,$globalize=null){
		$path = $prefix.'/'.implode('/',self::$parsedUrlTokens);
		//if path not directory, possibly is file
		if(!is_dir(Config::$x['instanceFolder'].$path)){
			$file = Config::$x['instanceFolder'].$path.'.php';
			$return = files::inc($file,$globalize);
			$return = $return ? $file : $return;
		}else{
			$file = $return = Config::$x['instanceFolder'].$path.'/'.$defaultName.'.php';
			files::inc($file,$globalize);
		}
		
		return $return;
	}
	///internal use. attempts to find non php file and send it to the browser
	private static function sendFile($type){
		array_shift(self::$urlTokens);
		$path = Config::$x['instanceLocation'].'/public/'.$type.'/'.escapeshellcmd(implode('/',self::$urlTokens));
		if(Config::$x['downloadParamIndicator']){
			//don't ust Page::$in because this isn't a full page, it is a resource page
			$saveAs = $_GET[Config::$x['downloadParamIndicator']] ? $_GET[Config::$x['downloadParamIndicator']] : $_POST[Config::$x['downloadParamIndicator']];
		}
		Display::sendFile($path,$saveAs);
	}
}
