<?
class Controller{
	static function req($path){
		$file = Config::userFileLocation($path,'controllers').'.php';
		return files::req($file,array('page'));
	}
}


