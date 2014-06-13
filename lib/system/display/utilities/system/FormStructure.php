<?php
class FormStructure{
	//Form method names and FormStructure method names don't conflict, so assume non FormStructure method names are Form method names, and encapsulate in formStructure where first argument is display param
	static function __callStatic($method,$arguments){
		$display = array_shift($arguments);
		$input = call_user_func_array(array('Form',$method),$arguments);
		return self::fieldColumns($arguments[0],$display,$input);
		
	}
	static function fieldColumns($name,$display,$input){
		return '<td data-fieldDisplay="'.$name.'" data-fieldContainer="'.$name.'">'.$display.'</td><td data-fieldContainer="'.$name.'">'.$input.'</td>';
	}
}
