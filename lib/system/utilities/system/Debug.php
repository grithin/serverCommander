<?
/// used for basic debuging
/** For people besides me, things don't always go perfectly.  As such, this class is exclusively for you.  Measure things.  Find new and unexpected features.  Explore the error messages*/
class Debug{
	/// measurements on time and memory
	static $measures;
	static $out;
	///provided for convenience to place various user debugging related values
	static $x;
	///allows for the decision to throw or trigger error based on the config
	/**
	@param	error	error string
	@param	throw	whether to throw the error (true) or trigger it (false)
	@param	type	either level of the error or the exception class to use
	*/
	static function throwError($error,$throw=null,$type=null){
		$throw = $throw === null ? Config::$x['throwErrors'] : $throw;
		if($throw){
			$type = $type ? $type : 'Exception';
			throw new $type($error);
		}
		$type = $type ? $type : E_USER_ERROR;
		trigger_error($error, $type);
	}
	///Take a measure
	/** Allows you to time things and get memory usage
	@param	name	the name of the measure to be printed out with results.  To get the timing between events, the name should be the same.
	*/
	static function measure($name='std'){
		$next = count(self::$measures[$name]);
		self::$measures[$name][$next]['time'] = microtime(true);
		self::$measures[$name][$next]['mem'] = memory_get_usage();
		self::$measures[$name][$next]['peakMem'] = memory_get_peak_usage();
	}
	///get the measurement results
	/**
	@param	type	the way in which to print out results if any.  options are "html" and "console"
	@return	returns an array with results
	*/
	static function measureResults(){
		foreach(self::$measures as $name=>$measure){
			$totalTime = 0;
			while(($instance = current($measure)) && next($measure)){
				$nextInstance = current($measure);
				if($nextInstance){
					$currentCount = count($out[$name]);
					$totalTime += $nextInstance['time'] - $instance['time'];
					$out[$name][$currentCount]['timeChange'] = $nextInstance['time'] - $instance['time'];
					$out[$name][$currentCount]['memoryChange'] = $nextInstance['mem'] - $instance['mem'];
					$out[$name][$currentCount]['peakMemoryChange'] = $nextInstance['peakMem'] - $instance['peakMem'];
					$out[$name][$currentCount]['peakMemoryLevel'] = $instance['peakMem'];
				}
			}
			$out[$name]['total']['time'] = $totalTime;
		}
		return $out;
	}
	///put variable into the log file for review
	/** Sometimes printing out the value of a variable to the screen isn't an option.  As such, this function can be useful.
	@param	var	variable to print out to file
	@param	title	title to use in addition to other context information
	@param	logfile	the log file to write to.  Config::$x['logLocation'] can be changed in the script, but this parameter provides an alternative to changing it
	*/
	static function toLog($var,$title='',$logfile=null){
		if($logfile){
			$fh = fopen($logfile,'a+');
		}else{
			$fh = fopen(Config::$x['logLocation'],'a+');
		}
		
		$bTrace = debug_backtrace();
		$file = self::abbreviateFilePath($bTrace[0]['file']);
		$line = $bTrace[0]['line'];
		fwrite($fh,"+=+=+=+ ".date("Y-m-d H:i:s").' | '.Config::$x['instanceName']." | TO FILE | ".$file.':'.$line.' | '.$title." +=+=+=+\n".var_export($var,1)."\n");
		fclose($fh);
	}
	///get a line from a file
	/**
	@param	file	file path
	@param	line	line number
	*/
	static function getLine($file,$line){
		if($file){
			$f = file($file);
			$code = substr($f[$line-1],0,-1);
			return preg_replace('@^\s*@','',$code);
		}
	}
	///print a boatload of information to the load so that even your grandma could fix that bug
	/**
	@param	eLevel	error level
	@param	eStr	error string
	@param	eFile	error file
	@param	eLine	error line
	*/
	static function handleError($eLevel,$eStr,$eFile,$eLine){
		if(ini_get('error_reporting') == 0){# @ Error control operator used
			return;
		}
		
		$code = self::getLine($eFile,$eLine);
		$eFile = preg_replace('@'.PU.'@','',$eFile);
		$eFile = preg_replace('@'.PR.'@','',$eFile);
		$err = "+=+=+=+ ".date("Y-m-d H:i:s").' | '.Config::$x['instanceName']." | ERROR | ".self::abbreviateFilePath($eFile).":$eLine +=+=+=+\n$eStr\n";
		
		if(Config::$x['errorDetail'] > 0){
			$bTrace = debug_backtrace();
			
			//php has some odd backtracing so need various conditions to remove excessive data
			if($bTrace[0]['file'] == '' && $bTrace[0]['class'] == 'Debug'){
				array_shift($bTrace);
			}
			
			if(Config::$x['debugAssumePerfection']){
				//Exclude system files (presume they are errorless)
				foreach($bTrace as $k=>$v){
					if(preg_match('@'.Config::$x['systemFolder'].'@',$v['file'])){
						$systemFileFound = true;
						array_shift($bTrace);
						if($nullFileFound){
							array_shift($bTrace);
						}
					}elseif($systemFileFound && !$v['file']){
						$nullFileFound = true;
					}else{
						break;
					}
				}
			}
			
			foreach($bTrace as $v){
				$err .= "\n".'(-'.$v['line'].'-) '.self::abbreviateFilePath($v['file'])."\n";
				$code = self::getLine($v['file'],$v['line']);
				if($v['class']){
					$err .= "\t".'Class: '.$v['class'].$v['type']."\n";
				}
				$err .= "\t".'Function: '.$v['function']."\n";
				if($code){
					$err .= "\t".'Line: '.$code."\n";
				}
				if($v['args'] && Config::$x['errorDetail'] > 1){
					$err .= "\t".'Arguments: '."\n";
					ob_start();
					var_export($v['args']);
					$err .= preg_replace(
							array("@^array \(\n@","@\n\)$@","@\n@"),
							array("\t\t",'',"\n\t\t"),
							ob_get_clean())."\n";
				}
			}
			if(Config::$x['errorDetail'] > 2){
				$err.= "\nServer Var:\n:".var_export($_SERVER,1);
				$err.= "\nRequest-----\nUri:".$_SERVER['REQUEST_URI']."\nVar:".var_export($_REQUEST,1);
				$err.= "\n\nFile includes:\n".var_export(Files::getIncluded(),1);
			}
			$err.= "\n";
		}
		
		$file = Config::$x['logLocation'];
		if(!file_exists($file) || filesize($file)>Tool::byteSize(Config::$x['maxLogSize'])){
			$mode = 'w';
		}else{
			$mode = 'a+';
		}
		$fh = fopen($file,$mode);
		fwrite($fh,$err);
		
		if(Config::$x['errorPage']){
			Config::loadUserFiles(Config::$x['errorPage']);
		}elseif(Config::$x['errorMessage']){
			if(is_array(Config::$x['errorMessage'])){
				$message = Config::$x['errorMessage'][rand(0,count(Config::$x['errorMessage'])-1)];
			}else{
				$message = Config::$x['errorMessage'];
			}
			echo $message;
		}
		if(Config::$x['displayErrors']){
			self::sendout($err);
		}
		exit;
		
	}
	static function abbreviateFilePath($path){
		return preg_replace(array('@'.Config::$x['instanceFolder'].'@','@'.Config::$x['systemFolder'].'@'),array('instance:','system:'),$path);
	}
	///print a variable and kill the script
	/** first cleans the output buffer in case there was one.  Echo in <pre> tag
	@param	var	any type of var that var_export prints
	*/
	static function end($var=null){
		$content=ob_get_clean();
		if($var){
			$content .= "\n".var_export($var,1);
		}
		self::sendout($content);
		exit;
	}
	///print a variable with file and line context, along with count
	/**
	@param	var	any type of var that print_r prints
	*/
	static $usleepOut = 0;///<usleep each out call
	static function out(){
		self::$out['i']++;
		$trace = debug_backtrace();
		foreach($trace as $part){
			if($part['file'] && __FILE__ != $part['file']){
				$trace = $part;
				break;
			}
		}
		
		$args = func_get_args();
		foreach($args as $var){
			$file = self::abbreviateFilePath($trace['file']);
			self::sendout("[".$file.':'.$trace['line']."] ".self::$out['i'].": ".var_export($var,true)."\n");
		}
		if(self::$usleepOut){
			usleep(self::$usleepOut);
		}
	}
	///exists after using self::out on inputs
	static function quit(){
		$args = func_get_args();
		call_user_func_array(array(self,'out'),$args);
		exit;
	}
	///Encapsulates in <pre> if determined script not being run on console (ie, is being run on web)
	static function sendout($output){
		if(Config::$x['inScript']){
			echo $output;
		}else{
			echo '<pre>'.$output.'</pre>';
		}
	}
}