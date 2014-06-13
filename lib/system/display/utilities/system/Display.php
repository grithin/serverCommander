<?php
///For handling output and templates
class Display{
	///include alias file
	static $aliases;
	static function initialize(){
		if(!is_array(Config::$x['aliasesFiles'])){
			Config::$x['aliasesFiles'] = array(Config::$x['aliasesFiles']);
		}
		foreach(Config::$x['aliasesFiles'] as $file){
			$extract = Files::inc($file,null,null,array('aliases'));
			self::$aliases = Arrays::merge(self::$aliases,$extract['aliases']);
		}
	}
	
	///searches in the display logic folder and gets all logic for a page, starting with broader and going to narrower scope
	/**For calls with a page/template, it will look in the display/logic/template folder.  For calls without a page/template specified, it will assume controller display logic was desired and look in the display/logic/controller folder*/
	static function getDisplayLogic(){
		$base = Config::$x['instanceFolder'].'display/logic/controller/';
		$tokens = RequestHandler::$urlTokens;
		Files::inc($base.'logic.php',array('page'));
		while($tokens){
			$hierarchy[] = array_shift($tokens);
			$path = implode('/',$hierarchy);
			
			if(is_file($base.$path.'.php')){
				Files::inc($base.$path.'.php',array('page'));
			}elseif(is_file($base.$path.'/logic.php')){
				Files::inc($base.$path.'/logic.php',array('page'));
			}
		}
	}
	///Either get page specific section and page logic or just page logic (if hierarchy = false)
	/** potentially, you can pass an array as the heirarchy variable and the code will start at the end token instead of the base ...logic/template/ folder
	@param	page	the template page as used in getTemplate and show
	@param	hierarchy	whether to use the logic.php files in the parent folders of page
	*/
	static function getPageLogic($page,$hierarchy=false,$require=true){
		$base = Config::$x['instanceFolder'].'display/logic/template/';
		if(!$hierarchy){
			if($require){
				Files::req($base.$page.'.php',array('page'));
			}else{
				Files::inc($base.$page.'.php',array('page'));
			}
			
		}else{
			$tokens = explode('/',$page);
			Files::inc($base.'logic.php',array('page'));
			while($tokens){
				$hierarchy[] = array_shift($tokens);
				$path = implode('/',$hierarchy);
				
				if(is_file($base.$path.'.php')){
					Files::inc($base.$path.'.php',array('page'));
				}elseif(is_file($base.$path.'/logic.php')){
					Files::inc($base.$path.'/logic.php',array('page'));
				}
			}
		}
	}
	
	///used to get the content of a single template file
	/**
	@param	template	string path to template file relative to the templateFolder.  .php is appended to this path.
	@param	vars	variables to extract and make available to the template file
	@return	output from a template
	*/
	static function getTemplate($template,$vars=null){
		#try to include page logic if it exists - note, php filesystem caching should deal with non-existent files well (no double checking on double call)
		self::getPageLogic($template,false,false);
		
		ob_start();
		$vars['thisTemplate'] = $template;
		Files::req(Config::$x['templateFolder'].$template.'.php',array('page'),$vars);
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	///Used to allow display logic to override template choices made in the controller
	static $showArgs;
	///used as the primary method to show a collection of templates.  @attention parameters are the same as the Display::get function
	static function show(){
		self::$showArgs = func_get_args();
		if(Config::$x['showPreHooks']){
			foreach(Config::$x['showPreHooks'] as $hook){
				call_user_func($hook);
			}
		}
		$output = call_user_func_array(array('self','get'),self::$showArgs);
		if(Config::$x['showPostHooks']){
			foreach(Config::$x['showPostHooks'] as $hook){
				call_user_func_array($hook,array(&$output));
			}
		}
		echo $output;
	}
	
	///calls show then dies
	static function end(){
		call_user_func_array(array(self,'show'),func_get_args());
		exit;
	}
	
	
	static private $callInstance = 0;
	///used to get a collection of templates without displaying them
	/**
	@param	templates	has the following forms:
		-	single template string
		-	comma seperated list of templates
		-	array with each element being a template
		-	an array of structured template arrays:
		@verbatim
array(
	array('templateFile','templateName',$subTemplates),
	array('templateFile2','templateName2',$subTemplates2),
)
		@endverbatim
		where subtempltes follow the same pattern as templates.  In the case of subtemplates, named ouput of each subtemplate along with the total previous output of the subtemplates is passed to the supertemplate.  The output of each subtemplate is passed by name in a $templates array, and the total output is available under the variable $input.
	@param	level	used internally
	@param	instance	used internally
	@return output from the templates
	*/
	static function get($templates,$level=0,$instance=0){
		static $addedTemplates;
		//Allowing multiple calls to the get function at 0 level simultaneously (possibly in display logic)
		if($level == 0){
			if(!is_array($templates)){
				$templates = self::parseTemplateString($templates);
			}
			$templates = self::parseAliases($templates,self::$aliases);
			
			$instance = self::$callInstance;
			self::$callInstance++;
		}
		$addedTemplates[$instance][$level] = array();
		//temporary fix
		if(!is_array($templates)){
			$templates = preg_split('@[\s,]+@',$templates);
		}
		while($templates){
			$template = array_pop($templates);
			if(is_array($template)){
				if($template[2]){
					$output = self::get($template[2],$level + 1,$instance);
					if($template[0]){
						$output = self::getTemplate($template[0],array('templates'=>$addedTemplates[$instance][$level+1],'input'=>$output));
					}
					
					Arrays::addOnKey($template[0],$output,$addedTemplates[$instance][$level]);
				}else{
					$output = self::getTemplate($template[0]);
					Arrays::addOnKey($template[0],$output,$addedTemplates[$instance][$level]);
				}
			}else{
				$output = self::getTemplate($template);
				Arrays::addOnKey($template,$output,$addedTemplates[$instance][$level]);
			}
			$totalOutput .= $output;
		}
		
		if($level == 0){
			self::$callInstance--;
		}
		
		return $totalOutput;
	}
	
	
	/**
		3 forms exceptable.

		Multiline:
			$templateString = '
				standardPage
					standardFullPage
						emailLogs';
						
		Single line:
			#$templateString = 'standardPage		standardFullPage			emailLogs';
			#$templateString = 'standardPage,,standardFullPage,,,emailLogs';
	*/
	static function parseTemplateString($templateString){
		#single line, break into multiline
		if(!preg_match('/\n/',$templateString)){
			#ensure start template is at level 1
			$templateString = preg_replace('@^[ \n]*([^\s,])@',"\t".'$1',$templateString);
			#converge seperation
			$templateString = preg_replace('@,@',"\t",$templateString);
			#conform newline spacing
			$templateString = preg_replace('@\t+@',"\n".'$0',$templateString);
		}
		#remove start space and newlines
		$templateString = preg_replace('@^[ \n]*@','',$templateString);

		#remove excessive tabbing (items are now separated by newlines on all cases)
		preg_match('@^\t+@',$templateString,$match);
		if($match){
			$excessiveTabs =  str_repeat('\t',strlen($match[0]));
			$templateString = preg_replace('@(^|[^\t])'.$excessiveTabs.'@','$1',$templateString);
		}

		$templates = explode("\n",$templateString);
		$array = self::generateTemplatesArray($templates);
		
		#replace !current with current controller to template location
		$array = Arrays::replaceAll('!current',implode('/',RequestHandler::$parsedUrlTokens),$array);
		
		#set !children to work with parseAliases
		$array = Arrays::replaceAllParents('!children','!children',$array,2);
		return $array;
	}
	///internal use for parseTemplateString
	static function generateTemplatesArray($templates,$depth=0,&$position=0){
		$templatesArray = array();
		$totalTemplates = count($templates);
		for(; $position < $totalTemplates; $position++){
			$template = $templates[$position];
			preg_match('@(\t*)([^\t]+$)@',$template,$match);
			$templateDepth = strlen($match[1]);
			$templateId = $match[2];
			
			#add sub templates
			if($templateDepth > $depth){
				#add subtemplates to previous template
				$templatesArray[count($templatesArray) - 1][2] = self::generateTemplatesArray($templates,$templateDepth,$position);
			}
			#return to parent
			elseif($templateDepth < $depth){
				return $templatesArray;
			}
			#in the same depth, add to array
			else{
				list($templateFile,$templateName) = explode(':',$templateId);
				$templatesArray[] = array($templateFile,$templateName);
			}
		}
		return $templatesArray;
	}
	///replaces aliases identified with @ALIAS_NAME in template array and defined in the alias file
	static function parseAliases(&$array){
		foreach($array as &$item){
			//is aliased, find replacement
			if($item[0][0] == '@'){
				$item = self::replaceTemplateAlias(substr($item[0],1),$item[2]);
			}
			if($item[2][0] == '@'){
				$item = self::replaceTemplateAlias(substr($item[0],1));
			}
			if(is_array($item[2])){
				$item[2] = self::parseAliases($item[2]);
			}
		}
		unset($item);
		
		return $array;
	}
	static function replaceTemplateAlias($alias,$children=null){
		$tree = self::$aliases[$alias];
		if(!$tree){
			Debug::throwError('Could not find alias: '.$alias);
		}
		if(!is_array($tree)){
			$tree = Arrays::at(self::parseTemplateString($tree),0);
		}
		
		if($children){
			$tree = Arrays::replaceAll('!children',$children,$tree);
		}
		
		return $tree;
	}
	
	///page css
	static $css = array();
	///page css put at the end after self::$css
	static $lastCss = array();
	///page js found at top of page
	static $topJs = array();
	///page js found at bottom of page
	static $bottomJs = array();
	///page js put at the end after self::$bottomJs
	static $lastJs = array();
	///determines whether addTag prepends or appends.  Note, mutli file addTag calls will be reverse sorted.
	static $tagPrepend = false;
	///used internally.
	/**
	@param	type	indicates whether tag is css, lastCss, js, or lastJs
	@param args	additional args taken as files.  Each file in the passed parameters has the following special syntax:
		-starts with http(s): no modding done
		-starts with "/": no modding done
		-starts with "inline:": file taken to be inline css or js.  Code is wrapped in tags before output.
		-starts with none of the above: file put in path /instanceToken/type/file; ex: "/public/css/main.css"
		-if array, consider first element the tag naming key and the second element the file; Used for ensuring only one tag item of a key, regardless of file, is included.
	@note	if you don't want to have some tag be unique (ie, you want to include the same js multiple times), don't use this function; instead, just set tag variable (like $bottomJs) directly
	*/
	static function addTag($type){
		if(in_array($type,array('css','lastCss'))){
			$uniqueIn = array('css','lastCss');
			$folder = 'css';
		}else{
			$uniqueIn = array('topJs','bottomJs','lastJs');
			$folder = 'js';
		}
		$files = func_get_args();
		array_shift($files);
		if($files){
			if(self::$tagPrepend){
				krsort($files);
			}
			$typeArray =& self::$$type;
			foreach($files as $file){
				if(is_array($file)){
					$key = $file[0];
					$file = $file[1];
				}
				
				if(preg_match('@^inline:@',$file)){
					$typeArray[] = $file;
				}
				//user is adding it, so assume css is at instance unless it starts with http or /
				else{
					if(substr($file,0,1) != '/' && !preg_match('@^http(s)?:@',$file)){
						$file = '/'.Config::$x['urlInstanceFileToken'].'/'.$folder.'/'.$file;
					}
					foreach($uniqueIn as $unique){
						Arrays::remove(self::$$unique,$file);
					}
					if(!$key){
						if(self::$tagPrepend){
							array_unshift($typeArray,$file);
						}else{
							$typeArray[] = $file;
						}
					}else{
						$typeArray[$key] = $file;
						unset($key);
					}
				}
			}
		}
		
	}
	///Adds to the $css array and overrides duplicate elements.  Each argument considered css file.  See self::addTag for args details
	static function addCss(){
		$args =	func_get_args();
		array_unshift($args,'css');
		call_user_func_array(array('self','addTag'),$args);
	}
	///Adds css that will come after the regularly added css
	static function addLastCss(){
		$args =	func_get_args();
		array_unshift($args,'lastCss');
		call_user_func_array(array('self','addTag'),$args);
	}
	///Adds to the $js array and overrides duplicate elements.  Each argument considered js file.  See self::addTag for args details
	static function addTopJs(){
		$args =	func_get_args();
		array_unshift($args,'topJs');
		call_user_func_array(array('self','addTag'),$args);
	}
	///Adds to the $bottomJs array and overrides duplicate elements.  Each argument considered js file.  See self::addTag for args details
	/**
	The point of putting JS at the bottom is that often the js doesn't immediately have an effect on the page display, yet, if put at the top, the browser will wait until the javascript is loading to load the rest of the page.  This has to be balanced with the fact that some utility javascript at the top of the page is needed for inline javascript that uses those utilities to work.  So, really, it comes done to preference.
	*/
	static function addBottomJs(){
		$args =	func_get_args();
		array_unshift($args,'bottomJs');
		call_user_func_array(array('self','addTag'),$args);
	}
	///Adds js that will come after the regularly added js
	static function addLastJs(){
		$args =	func_get_args();
		array_unshift($args,'lastJs');
		call_user_func_array(array('self','addTag'),$args);
	}
	
	///used by getCss to turn css array into html string
	static function cssArrayToString($array,$urlQuery){
		foreach($array as $file){
			if(preg_match('@^inline:@',$file)){
				$css[] = '<style type="text/css">'.substr($file,7).'</style>';
			}else{
				if($urlQuery){
					$file = Http::appendsUrl($urlQuery,$file);
				}
				$css[] = '<link rel="stylesheet" type="text/css" href="'.$file.'"/>';
			}
		}
		return implode("\n",$css);
	}
	///Outputs css style tags with self::$css
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	static function getCss($urlQuery=null){
		if(self::$css){
			$css = self::cssArrayToString(self::$css,$urlQuery);
		}
		if(self::$lastCss){
			$css .= self::cssArrayToString(self::$lastCss,$urlQuery);
		}
		return $css;
	}
	///used by getCss to turn css array into html string
	static function jsArrayToString($array,$urlQuery){
		foreach($array as $file){
			//Intended to be used for plain script
			if(preg_match('@^inline:@',$file)){
				$js[] = '<script type="text/javascript">'.substr($file,7).'</script>';
			}else{
				if($urlQuery){
					$file = Http::appendsUrl($urlQuery,$file);
				}
				$js[] = '<script type="text/javascript" src="'.$file.'"></script>';
			}
		}
		return implode("\n",$js);
	}
	
	///	Outputs js script tags with self::$topJs
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	static function getTopJs($urlQuery=null){
		if(self::$topJs){
			$js = self::jsArrayToString(self::$topJs,$urlQuery);
		}
		return $js;
	}
	
	///	Outputs js script tags with self::$bottomJs and self::$lastJs
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	static function getBottomJs($urlQuery=null){
		if(self::$bottomJs){
			$js = self::jsArrayToString(self::$bottomJs,$urlQuery);
		}
		if(self::$lastJs){
			$js .= self::jsArrayToString(self::$lastJs,$urlQuery);
		}
		return $js;
	}
	
	///Accumulated page json
	static $json = null;
	///prints the self::$json into the tp.json object.  Requires the previous declaration of tp js object on the page
	static function getJson(){
		echo '<script type="text/javascript">tp.json = '.json_encode(self::$json).';</script>';
	}
	///print out the ajax then quit
	/**
	@param	ajax	ajax content to print out
	@param	type	"xml" or "json"
	*/
	static function ajaxOut($ajax,$type='xml'){
		if($type == 'xml'){
			header('Content-type: text/xml; charset=utf-8');
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			echo $ajax;
		}elseif($type == 'json'){
			header('Content-type: application/json');
			echo $ajax;
		}
		exit;
	}
	
	static function sendFile($path,$saveAs=null){
		//Might potentially remove ".." from path, but it has already been removed by the time the request gets here by server or browser.  Still removing for precuation
		$path = Files::removeRelative($path);
		
		if(is_file($path)){
			
			//get mimetype
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($finfo, $path);
			finfo_close($finfo);
			
			/* file -ib command does not determine what type of text a text file is.  So, use the type that the browser was expecting*/
			if(substr($mime,0,5) == 'text/'){
				$mime = array_shift(explode(',',$_SERVER['HTTP_ACCEPT']));
			}
			
			header('Content-Type: '.$mime);
			if($saveAs){
				header('Content-Description: File Transfer');
				if(strlen($saveAs) > 1){
					$fileName = $saveAs;
				}else{
					$fileName = array_pop(explode('/',$path));
				}
				header('Content-Disposition: attachment; filename="'.preg_replace('@"@','\"',$fileName).'"');
			}
			
			echo file_get_contents($path);
		}elseif(Config::$x['resourceNotFound']){
			Config::loadUserFiles(Config::$x['resourceNotFound'],'controllers');
		}else{
			Debug::throwError('Request handler encountered unresolvable file.  Searched at '.$path);
		}
		exit;
	}
	//simple standard logic to generate page title
	static function pageTitle(){
		return Tool::capitalize(Tool::camelToSeparater(implode(' ',RequestHandler::$parsedUrlTokens),' '));
	}
}
Display::initialize();