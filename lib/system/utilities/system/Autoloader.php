<?
///Simple autoloader based on folder list.  Add memcached for heavy load sites.
class Autoloader{
	///folders to be checked recursively for class file
	/**@attention feel free to modify this variable to alter autoloader functionality on the fly*/
	static $resourceFolders;
	static $testedFolders;
	///load a class based on current autoloader resourceFolders
	/**
	@param	className	name of the class and the name of the file without the ".php" extension
	*/
	static function load($className){
		$excludePaths = array();
		self::$testedFolders = array();
		//go throught all possible paths until either finding the class or failing
		while(!class_exists($className,false)){
			$found = self::findClass($className,self::$resourceFolders,$excludePaths);
			if($found['location']){
				$excludePaths = $found['exclude'];
				require_once $found['location'];
			}elseif($found === false){
				break;
			}
		}
		if(class_exists($className,false)){
			return;
		}
		//this is the only autoloader and it has failed
		if(count(spl_autoload_functions()) == 1){
			$error = 'Attempt to autoload class "'.$className.'" has failed.  Tested folders: '."\n".implode("\n",self::$testedFolders);
			Debug::throwError($error,null,E_USER_ERROR);
		}
	}
	///finds a class looking in folders recursively
	/**
	@param	name	name of the class
	@param	folders	array of folders to check in
	*/
	static function findClass($name,$folders,$excludePaths){
		if(Config::$x['autoloadSection']){
			//if urltokens defined, try to get section and page based classes first
			if(class_exists('RequestHandler',false) && RequestHandler::$urlTokens){
				//check utility section and display utility section
				$sectionBases = array(Config::$x['instanceFolder'].'utilities/section/',Config::$x['instanceFolder'].'display/utilities/section/');
				foreach($sectionBases as $base){
					unset($previousFolder);
					$checkTokens = RequestHandler::$urlTokens;
					
					//check section folders
					while($checkTokens){
						$folder = $base.implode('/',$checkTokens).'/';
						$location = self::findClassInFolder($name,$folder,$excludePaths);
						$excludePaths[$folder] = true;
						if($location){
							return array('location'=>$location,'exclude'=>$excludePaths);
						}
						$previousFolder = $folder;
						array_pop($checkTokens);
					}
				}
			}
		}
		
		foreach($folders as $folder){
			$location = self::findClassInFolder($name,$folder,$excludePaths);
			
			$excludePaths[$folder] = true;
			if($location){
				return array('location'=>$location,'exclude'=>$excludePaths);
			}
		}
		return false;
	}
	///recursively checks a folder for a class
	/**
	@param	name	class name
	@param	folder	folder path
	*/
	static function findClassInFolder($name,$folder,$exclude=null){
		//potentially, the folder past in has already been checked
		if($exclude[$folder]){
			return;
		}
		self::$testedFolders[] = $folder;
		//see if there is a class file in the folder pass in
		if(is_file($folder.$name.'.php')){
			return $folder.$name.'.php';
		}
		
		if(is_dir($folder)){
			//see if the class file exists in subfolders of the folder passed in
			foreach(@scandir($folder) as $entry){
				if($entry != '.' && $entry != '..'){
					$newFolder = $folder.$entry;
					if($exclude[$newFolder]){
						continue;
					}
					if(is_dir($newFolder)){
						$location = self::findClassInFolder($name,$newFolder.'/');
						if($location){
							return $location;
						}
					}
				}
			}
		}
	}
	
	static $lastUndeployed;///< array of last undeployed autoloaders
	///Removes all autoloaders and puts them in the $lastUndeployed variable
	static function undeploy(){
		self::$lastUndeployed = spl_autoload_functions();
		foreach(self::$lastUndeployed as $autoloader){
			spl_autoload_unregister($autoloader);
		}
	}
	///Prepends an autoloader
	static function prepend($newAutoloader){
		$autoloaders = spl_autoload_functions();
		foreach($autoloaders as $autoloader){
			spl_autoload_unregister($autoloader);
		}
		spl_autoload_register($newAutoloader);
		foreach($autoloaders as $autoloader){
			spl_autoload_register($autoloader);
		}
	}
	
	///used to deploy an array of autoloaders
	/**
	@autloaders	array	array of autoloaders.  If null, defaults to self::$lastUndeployed
	*/
	static function deploy($autoloaders=null){
		if(!$autoloaders){
			$autoloaders = self::$lastUndeployed;
		}
		foreach($autoloaders as $autoloader){
			spl_autoload_register($autoloader);
		}
	}
	///used to repace current autoloaders with last undeployed autoloaders
	static function replace(){
		$autoloaders = spl_autoload_functions();
		foreach($autoloaders as $autoloader){
			spl_autoload_unregister($autoloader);
		}
		self::deploy();
	}
}
if(Config::$x['autoloadIncludes']){
	foreach(Config::$x['autoloadIncludes'] as $folder){
		if(is_dir($folder)){
			Autoloader::$resourceFolders[] = $folder;
		}
	}
}
Autoloader::$resourceFolders = Autoloader::$resourceFolders ? Autoloader::$resourceFolders : array();
