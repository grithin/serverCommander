<?
class InputFilter{
	///filter to integer
	static function toInt(&$value){
		$value = (int)$value;
	}
	///filter to absolute integer
	static function toAbsoluteInt(&$value){
		$value = abs($value);
	}
	///filter to float
	static function toDecimal(&$value){
		$value = (float)$value;
	}
	///filter all but digits
	static function toDigits(&$value){
		$value = preg_replace('@[^0-9]@','',$value);
	}
	static function regexReplace(&$value,$regex){
		$value = preg_replace($regex,'',$value);
	}
	static function toUrl(&$value){
		$value = trim($value);
		if(substr($value,0,4) != 'http'){
			$value = 'http://'.$value;
		}
	}
	static function toString(&$value){
		while(is_array($value)){
			$value = array_shift($value[0]);
		}
	}
	static function trim(&$value){
		$value = trim($value);
	}
	static function toDate(&$value){
		$value = i()->Time($value,Config::$x['inOutTimezone'])->setZone(Config::$x['timezone'])->date();
	}
	static function toDatetime(&$value){
		$value = i()->Time($value,Config::$x['inOutTimezone'])->setZone(Config::$x['timezone'])->datetime();
	}
	
	static $stripTagsAllowableTags;
	static $stripTagsAllowableAttributes;
	static function stripTags(&$value,$allowableTags=null,$allowableAttributes=null,$callback=null){
		self::$stripTagsAllowableTags = Arrays::stringArray($allowableTags);
		self::$stripTagsAllowableAttributes = $allowableAttributes;
		
		$value = preg_replace_callback('@(</?)([^>]+)(>|$)@',array(self,'stripTagsCallback'),$value);
	}
	static function stripTagsCallback($match){
		preg_match('@^[a-z]+@i',$match[2],$tagMatch);
		if($tagMatch){
			$tag = $match[0];
			$tagName = $tagMatch[0];
			if(!in_array($tagName,self::$stripTagsAllowableTags)){
				return '';
			}
			
			if($match[1] == '<'){
				//allow some appropriate attributes on opening tags
				$attributes = self::getAttributes($match[0],self::$stripTagsAllowableAttributes);
				
				if(substr($match[2],-1) == '/'){
					$close = ' />';
				}else{
					$close = '>';
				}
				if($callback){
					call_user_func_array($callback,array(&$tagMatch[0],&$attributes));
				}
				return '<'.$tagMatch[0].($attributes ? ' '.implode(' ',$attributes) : '').$close;
			}else{
				if($callback){
					call_user_func_array($callback,array(&$tagMatch[0]));
				}
				return '</'.$tagMatch[0].'>';
			}
		}else{
			return '';
		}
	}
	static function getAttributes($tag,$attributes){
		$attributes = Arrays::stringArray($attributes);
		$collected = array();
		foreach($attributes as $attribute){
			preg_match('@'.$attribute.'=([\'"]).+?\1@i',$tag,$match);
			if($match){
				$collected[] = $match[0];
			}
		}
		return $collected;
	}
}