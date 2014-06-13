<?
/// logic unrelated to a specific request
/** @file */

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

#pre session request handling; for file serving and such.
require_once Config::$x['systemFolder'].'requestHandling/RequestHandler.php';
RequestHandler::handle();