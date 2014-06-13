<?
///simple class to help with outputting form tags with post and pre filled data
/** All values are escaped*/
class Form {
	///adds class "field_input_NAME" to all form tags
	static $defaultClassPrefix;
	
	/// prefix form function with "_" to get temporary value override behavior.  In such a case, first argument is considered the overriding behavior.
	static function __callStatic($name,$arguments){
		$name = substr($name,1);
		//allow _func to be treated as func
		if(!method_exists(__class__,$name)){
			Debug::throwError('Bad Form class method');
		}
		$formerValueBehavior = self::$valueBehavior;
		self::$valueBehavior = array_shift($arguments);
		$return = call_user_func_array(array(__class__,$name),$arguments);
		self::$valueBehavior = $formerValueBehavior;
		return $return;
	}
	
	///the the of behavior for determining what the actual value of a form field should be
	static $valueBehavior = 'to_input';
	
	///an array of keys to values used in conjuction with TO_ARRAY to serve as override values for form fields
	static $values;
	
	///tries to find value using page input.
	/**  If not found: 
			if useArray = true, uses self::$values
			if useArrayy = false, uses param if not null, otherwise uses self::$values
	*/
	static function to_input($name, $value=null, $useArray=false){
		if (isset(Page::$in[$name])){
			return Page::$in[$name];
		}else{
			$matches = Http::getSpecialSyntaxKeys($name);
			if($matches && Arrays::isElement($matches,Page::$in)){
				return Arrays::getElementReference($matches,Page::$in);
			}else{
				if($useArray){
					return self::$values[$name];
				}else{
					if($value === null  && isset(self::$values[$name])){
						return self::$values[$name];
					}else{
						return self::to_param($name,$value);
					}
				}
			}
		}
	}
	///leaves value of $value to be $value (ie, does nothing)
	static function to_param($name,$value=null){
		return $value;
	}
	///tries to find value in array self::$values.  If not found, tries using page input
	static function to_array($name,$value=null){
		if(is_array(self::$values) && isset(self::$values[$name])){
			return self::$values[$name];
		}else{
			return self::to_input($name,$value);
		}
	}
	///tries to user input.  If not found, tries using array self::$values
	static function to_params($name,$value=null){
		return self::to_input($name,$value,true);
	}
	
	/// resolves the value for a given form field name
	static function resolveValue($name, $value=null, $behavior=null){
		$behavior = $behavior ? $behavior : self::$valueBehavior;
		if(!is_array($behavior)){
			$behavior = array(self,$behavior);
		}
		$value = call_user_func_array($behavior,array($name,$value));
		
		FieldIn::makeString($value);
		
		//PageTool integration with Form for field form output (use with FieldOut to format form fields)
		if(isset(PageTool::$fieldOut) && PageTool::$fieldOut[$name]){
			call_user_func_array(PageTool::$fieldOut[$name],array(&$value));
		}
		
		return $value;
	}
	///total number of fields generated.  Used when no name provided
	static $fieldCount = 0;
	static $additionalParsers;
	///used internally.  Generates the additional attributes provides for a field
	static function additionalAttributes($x,$name='default'){
		if(self::$additionalParsers){
			foreach(self::$additionalParsers as $parser){
				list($x,$name) = call_user_func($parser,$x,$name);
			}
		}
		$classes = explode(' ',$x['class']);
		if(self::$defaultClassPrefix){
			$defaultClass = self::$defaultClassPrefix.($name ? $name : self::$fieldCount);
			array_unshift($classes,$defaultClass);
		}
		unset($x['class']);
		if($classes){
			$additions[] = 'class="'.implode(' ',$classes).'"';
		}
		self::$fieldCount++;
		if($x['extra']){
			$additions[] = $x['extra'];
		}
		unset($x['extra']);
		
		$attributeTypes = array('id','title','alt','rows','placeholder');
		foreach($attributeTypes as $attribute){
			if($x[$attribute]){
				$additions[] = $attribute.'="'.$x[$attribute].'"';
				unset($x[$attribute]);
			}
		}
		if($x){
			//add onclick events
			foreach($x as $k=>$v){		
				if(strtolower(substr($k,0,2)) == 'on'){
					$additions[] = $k.'="'.$v.'"';
				}elseif(strtolower(substr($k,0,5) == 'data-')){
					$additions[] = $k.'="'.htmlspecialchars($v).'"';
				}
			}
		}
		
		if($additions){
			return ' '.implode(' ',$additions).' ';
		}
	}


	/// create a <select> tag
	/**
	@param	name attribute of tag
	@param	value attribute of tag
	@param	options	array array(value=>text,value=>text) key to value where key is the option value and value is the option text.  I did it this way because in the backend the values are usually the keys;
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	@note	this is a simplified version which doesn't have option groups or freeform options.  I might introduce the more complicated version later
	*/
	static function select($name, $options, $value = null, $x = null){
		$values = self::resolveValue($name, $value);
		if(!is_array($values)){
			$values = array($values);
		}
		//makes an array where values are turned into an array of keys (= value) where each element is true
		$values = array_fill_keys($values, true);
		
		$specialX = Arrays::separate(array('none','noneValue'),$x);
		
		//create an array specifying the selected options
		$detailedOptions = array();
		//allow for empty array
		if($options){
			foreach($options as $k=>$v){
				if(is_array($v)){
					$detailedOptions[$k] = $v;
				}else{
					$detailedOptions[$k] = array('display'=>$v);
				}
				if($values[$k]){
					$detailedOptions[$k]['selected'] = true;
				}
			}
		}
		
		$field =  '<select name="'.$name.'" '.self::additionalAttributes($x,$name).'>';
		if($specialX['none']){
			$value = 0;
			if (isset($specialX['noneValue'])){
				$value = $specialX['noneValue'];
			}
			$field .= '<option value="'.$value.'">'.$specialX['none'].'</option>';
		}
		if($detailedOptions){
			foreach ($detailedOptions as $k=>$details){
				if($x['capitalize']){
					$details['display'] = ucwords($details['display']);
				}
				$field .= '<option '.
					($details['selected']?'selected=1':null)
					.' value="'.htmlspecialchars($k).'" '.
					($details['x'] ? self::additionalAttributes($details['x']) : '').
					'>'.htmlspecialchars($details['display']).'</option>';
				
			}
		}
		return $field.'</select>';
	}

	/// create a <input type="radio"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	checked indicates whether field is checked or not
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function radio($name, $option, $checked=null, $x=null){
		$checked = $checked ? $option : $checked;
		$value = self::resolveValue($name,$checked);//ie, if checked, pass in name of option as value, otherwise, pass in the blank value to serve as referenced variable
		return '<input type="radio" name="'.$name.'" '.($value == $option ?' checked':null).self::additionalAttributes($x,$name).' value="'.htmlspecialchars($option).'" />';
	}
	/// create an <input type="checkbox"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	checked indicates whether field is checked or not
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	@note	the only value is 1, because checkboxes should probably be unique in the name and values other than 1 are unnecessary
	*/
	static function checkbox($name, $checked=null, $x=null){
		$value = self::resolveValue($name,$checked);
		$on = self::hasValue($value) && $value != '0';
		return '<input type="checkbox" name="'.$name.'" '.($on?' checked="1" ':null).self::additionalAttributes($x,$name).' value=1 />';
	}

	/// create an <input type="text"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function text($name, $value=null, $x=null){
		$value = self::resolveValue($name,$value);
		return '<input type="text" name="'.$name.'" '.(self::hasValue($value)?' value="'.htmlspecialchars($value).'" ':null).self::additionalAttributes($x,$name).'/>';
	}

	/// create an <input type="file"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function file($name, $value=null, $x=null){
		$value = self::resolveValue($name,$value);
		return '<input type="file" name="'.$name.'" '.(self::hasValue($value)?' value="'.htmlspecialchars($value).'" ':null).self::additionalAttributes($x,$name).'/>';
	}

	/// create a <textarea> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function textarea($name, $value=null, $x=null){
		$value = self::resolveValue($name,$value);
		return '<textarea name="'.$name.'" '.self::additionalAttributes($x,$name).'>'.(self::hasValue($value)?htmlspecialchars($value):null).'</textarea>';
	}

	/// create an <input type="password"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function password($name, $value=null, $x=null){
		$value = self::resolveValue($name,$value);
		return '<input type="password" name="'.$name.'"'.(self::hasValue($value)?'value="'.htmlspecialchars($value).'" ':null).self::additionalAttributes($x,$name).'/>';
	}

	/// create an <input type="hidden"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	*/
	static function hidden($name,$value=null,$x=null){
		$value = self::resolveValue($name,$value);
		return '<input type="hidden" name="'.$name.'" '.(self::hasValue($value)?'value="'.htmlspecialchars($value).'" ':null).self::additionalAttributes($x,$name).'/>';
	}

	/// create an <input type="submit"> element
	/** checked if "checked" = true or if resolvedValue = value
	@param	name attribute of tag
	@param	value attribute of tag
	@param	x	list of options including "id", "class", "on[click|mouseover|...]", "alt", "title" and "extra", which is included in the tag
	@note	value and name are switchedin param position because it is more likely the value will be desired input than the name.
	*/
	static function submit($value=null,$name=null,$x=null){
		if(!$name){
			$name = $value;
		}
		return '<input type="submit" name="'.$name.'" '.(self::hasValue($value)?'value="'.htmlspecialchars($value).'" ':null).self::additionalAttributes($x,$name).'/>';
	}
	///used to determine if a value actually exists
	static function hasValue($value){
		if($value || $value === '0' || $value === 0){
			return true;
		}
	}
}

Form::$defaultClassPrefix = Config::$x['defaultFormFieldClassPrefix'];
