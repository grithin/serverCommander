<?
///supplementing php's inability to change new intances; used to allow chaining of instance construction with instance methods
/**Instantiate class; use with function i();  ex: 
	- i()->Time('-1 day')->datetime()
	- i()->Time->datetime()

*/
class I{
	function __call($name,$params){
		$reflection = new ReflectionClass($name);
		return $reflection->newInstanceArgs($params);
	}
	function __get($name){
		$reflection = new ReflectionClass($name);
		return $reflection->newInstanceArgs();
	}
}
///used to get new instantiator class instance
function i(){
	return new I;
}
