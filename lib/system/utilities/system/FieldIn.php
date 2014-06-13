<?
class FieldIn{
	static $fieldTypes = array(
		'phone' => 'f:toDigits,!v:filled,v:phone',
		'zip' => '!v:filled,v:zip',
		'name' => 'f:regexReplace|@[^a-z \']@i,f:trim,!v:filled',
		'email' => '!v:filled,v:isEmail',
		'password' => '!v:filled,v:lengthRange|3;50',
		'userBirthdate' => '!v:filled,v:date,v:age|18;130',
		'ip4' => array('f:trim','!v:filled',array('!v:matchRegex','@[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}@','ip4 format')),
	);
	///potentially some input vars are arrays.  To prevent errors in functions that expect field values to be strings, this function is here.
	static function makeString(&$value){
		if(is_array($value)){
			$valueCopy = $value;
			$value = self::getString(array_shift($valueCopy));
		}
	}
	///note, this can be a waste of resources; a reference $value going in is remade on assignment from the return of this function, so use makeString on references instead
	static function getString($value){
		if(is_array($value)){
			return self::getString(array_shift($value));
		}
		return $value;
	}
}