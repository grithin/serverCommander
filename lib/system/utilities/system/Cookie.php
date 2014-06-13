<?
class Cookie{
	
	static $data;///<variable used for automated saving of arbitrary data using encryption, used with setData and getData
	///used to set self::$data into the browsers cookies
	static function setData(){
		setcookie('data',Encryption::encrypt(serialize(self::$data)),0,'/');
	}
	///used to extract from cookie self::$data
	static function getData(){
		self::$data = unserialize(Encryption::decrypt($_COOKIE['data']));
	}
	///used to clear self::$data from the cookie and clear self::$data the variable
	static function clearData(){
		setcookie('data','',-1,'/');
		self::$data = null;
	}
	///set a cookie
	/**
	@param	key	the key of the cookie
	@param	value	the value of the cookie
	@param	options	key based option array that will override the default options
		- path
		- expire
		- domain
		- secure
		- httponly
	@note	this function will also set the corresponding $_COOKIE variable
	*/
	static function set($key,$value,$options=null){
		foreach(Config::$x['cookieDefaultOptions'] as $k=>$v){
			if(!isset($options[$k])){
				$options[$k] = $v;
			}
		}
		setcookie($key,$value,$options['expire'],$options['path'],$options['domain'],$options['secure'],$options['httpsonly']);
		$_COOKIE[$key] = $value;
	}
	///remove a cookie
	/**
	@param	key	the key of the cookie
	@param	options	key based option array that will override the default options
		- path
		- expire
		- domain
		- secure
		- httponly
	@note	this function will also unset the corresponding $_COOKIE variable
	*/
	static function remove($key,$options=null){
		foreach(Config::$x['cookieDefaultOptions'] as $k=>$v){
			if(!isset($options[$k])){
				$options[$k] = $v;
			}
		}
		setcookie($key,'',-1,$options['path'],$options['domain']);
		unset($_COOKIE[$key]);
	}
}