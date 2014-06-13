<?
/// logic unrelated to a specific request
/** @file */

$config['inScript'] = true;
require_once realpath(dirname(__FILE__)).'/config.php';
if(!$config['instanceLocation']){
	$config['instanceLocation'] = realpath(dirname(__FILE__));
}
$config['systemLocation'] = $config['systemLocation'] ? realpath($config['systemLocation']) : $config['instanceLocation'];

#I included because used by function "i"
require_once $config['systemLocation'].'/system/utilities/general/I.php';

#Tool, used by config
require_once $config['systemLocation'].'/system/utilities/general/Tool.php';

#Config setting
require_once $config['systemLocation'].'/system/utilities/system/Config.php';
Config::$x = $config;
Config::get();

date_default_timezone_set(Config::$x['timezone']);

#Autoloader
require_once Config::$x['systemFolder'].'utilities/system/Autoloader.php';
$autoloader = new Autoloader;
spl_autoload_register(array($autoloader,'load'));

set_error_handler(Config::$x['errorHandler'],Config::$x['errorsLogged']);

/*- load custom preload for various reasons:
	- defining custom error handler based on env mode
	- adding more autoloading functionality
*/
Config::loadUserFiles(Config::$x['preloaders'],'preloading');