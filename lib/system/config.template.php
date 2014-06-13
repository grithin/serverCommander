<?
///file used for altering the configuration of the framework instance
/** @file

-	At the point this file is called, there is only one framework set variable which is $config['envMode'], which is based on getenv('mode'), and can be used to dynamically change config here.
-	Normally, configuration options specifying files do require the file extensions.  Also, config file variables tend to be relative pathed in such a way that starting with a "/" in the file name makes the path absolute and starting without one makes the path relative to some folder (usually a folder having to do with the file)
-	Most included custom user files are included in a scope that is not the main scope, so if you want to access variables between scopes, you'll have to do something like make those variables global within the file that is included
-	Theoretically you can change the system and instance locations.  You might do so by building domain specific conditions in the public/index.php where the instance location is change for the site, and there is a common system location.
-	Config variable strings will be parsed for ::var:: where var is a previously set configuration variable; the ::var:: part will be replaced with the actual value of the config variable; just make sure everything is in the correct order.  Also, "systemLocation" can not have ::keyword::s.
*/


///just a name for the system to uniquely identify this instance in log files
$config['instanceName'] = 'iNsTaNcE nAmE';

//+	Path configs{
///path to directory in which system folder lies in
$config['systemLocation'] = null;
///actual path to the system folder
$config['systemFolder'] = '::systemLocation::/system/';
///path to directory in which instance folder lies in
$config['instanceLocation'] = null;
///actual path to the instance folder
$config['instanceFolder'] = '::instanceLocation::/instance/';

///path to display folder (in case you want to use some common templates folder)
$config['displayFolder'] = '::instanceFolder::display/';
///path to template folder (in case you want to use some common templates folder)
$config['templateFolder'] = '::displayFolder::templates/';
///path to template folder (in case you want to use some common templates folder)
$config['storageFolder'] = '::instanceFolder::storage/';
//+	}


//+	Log configs {
///log location
/** path is relative to instance folder*/
$config['logLocation'] = 'info/log';
///Max log size.  If you want only one error to show at a time, set this to 0
$config['maxLogSize'] = '0mb';
//+	}



//+	Request handler config {
///string or array to call for special request handler files with relative path to instance/requestHandler/
$config['requestHandlers'] = null;
///what file to call when page was not found; relative to instance/controllers/
$config['pageNotFound'] = '404page.php';
///what file to call when resource was not found; relative to instance/controllers/
$config['resourceNotFound'] = '404page.php';
///file to use for directory index page
/**when the RequestHandler is at the end of a path and hasn't called a controller with a page name, it will see if this file exists within the last directory and try to load it.  If this config equates to false, the RequestHandler will not use any index page*/
$config['useIndex'] = 'index.php';
///the starting url path token that indicates that the system should look in the public/instance directory
$config['urlInstanceFileToken'] = 'public';
///the starting url path token that indicates that the system should look in the public/system directory.  Must be different than instance token.
$config['urlSystemFileToken'] = 'tpfile';
///A parameter in the post or get that indicates a file is supposed to be downloaded instead of served.  Works for non-parse public directory files.  Additionally, serves to name the file if the param value is more than one character.
$config['downloadParamIndicator'] = 'download';
//+	}


//+	Error config {
///by default, errors are triggered with trigger_error.  "true" will use "throw new Exception()".
$config['throwErrors'] = false;
///custom error handler.  Defaults to system error handler.
$config['errorHandler'] = 'Debug::handleError';
///custom error handler.  Defaults to system error handler.
$config['errorsLogged'] = E_ALL & ~ E_NOTICE & ~ E_STRICT;
///error page relative path to instance/.  Just make sure the error page doesn't have any errors
$config['errorPage'] = '';
///Messages to display in liue of an error page.  Can be set to single string or an array of strings.  If an array, random is chosen.
$config['errorMessage'] = array(
		"System going down.  Someone forgot how to treat camels",
		"System going down.  The monkeys have been at the code again",
		"System going down.  You have compelled the site to stop working and the programmers to start",
		"System going down.  All progamers are currently busy",
		"System going down.  Too much crap, not enough TP"
	);
///Assumes system files won't error and thus excludes them from debug report
$config['debugAssumePerfection'] = true;
///Determines the level of detail provided in the error message, 0-3
$config['errorDetail'] = 3;
///Display errors
$config['displayErrors'] = false;
//+	}


//+	Session config {
///date in the past after which inactive sessions should be considered expired
$config['sessionExpiry'] = '-1 year';
///folder to keep the file sessions if file sessions are used.  Php must be able to write to the folder.
$config['sessionFolder'] = '::instanceFolder::storage/sessions/';
///determines whether to use the database for session data
$config['sessionUseDb'] = true;
///determines which table in the database to use for session data
$config['sessionDbTable'] = 'session';
///the time at which the cookie is set to expire.
$config['sessionCookieExpiry'] = strtotime('+1 year');
///cookie expiry refresh probability; the denominator that an existing session cookie will be updated with the current sessionCookieExpiry on page load.  0, false, null = don't refresh
$config['sessionCookieExpiryRefresh'] = 100;
//+	}




//+	Encryption config {
///the cipher to use for the framework encyption class
$config['cryptCipher'] = MCRYPT_RIJNDAEL_128;
///the cipher mode to use for the framework encyption class
$config['cryptMode'] = MCRYPT_MODE_ECB;
///the cipher key to use for the framework encyption class.  Clearly, the safest thing would be to keep it as the default
$config['cryptKey'] = 'php.framework.thoughtpush.com';
//+	}


///a list of directories to search recursively
$config['autoloadIncludes'] = array(
		'::systemFolder::utilities/general/',
		'::systemFolder::utilities/system/',
		'::instanceFolder::utilities/general/',
		'::instanceFolder::utilities/system/',
		'::instanceFolder::utilities/instance/',
		'::instanceFolder::utilities/section/',
		'::systemFolder::display/utilities/general/',
		'::systemFolder::display/utilities/system/',
		'::instanceFolder::display/utilities/general/',
		'::instanceFolder::display/utilities/system/',
		'::instanceFolder::display/utilities/instance/',
		'::instanceFolder::display/utilities/section/'
	);
///tells autoloader to attempt to autoload class from the utility/section folder in broadening scope, starting with the last urlToken and end at utility/section
/**This variable will cause the autoloader to do some interesting things
	- check every folder in the sections folder up to and not including the section folder.  So, if the page tokens were blogs,cars,chevy, the autloader would check:
		- instance/utilities/blog/cars/chevy
		- instance/utilities/blog/cars
		- instance/utilities/blog/
		- instance/display/utilities/blog/cars/chevy
		- instance/display/utilities/blog/cars
		- instance/display/utilities/blog/
	After this, it would then check the normal autoloadIncludes folders, excluding everything that was already checked
	- for all scoped section folders checked, if the folder doesn't exist, try to get the file "FOLDER.php".  This allows the scenario where you have multiple pages in a section all with the same class, "pageTools", but with different file names.  As such, the controllers for all the pages can just use the generic name "page" to refer to that specific page's utility functions
	
*/
$config['autoloadSection'] = true;

//+	Display related config {
///used to make the display::show function call other functions before parsing templates.  No arguments passed.
/**
@note, you can modify the Display::show() arguments by modifying Display::$showArgs;
*/
$config['showPreHooks'] = array(array('Cookie','setData'),array('Display','getDisplayLogic'));
///used to make the display::show function call other functions before outputing parsed templates.  Output passed by reference.
$config['showPostHooks'] = array();
///indicates whether framework should use the default class for all form fields generated throught Form
$config['defaultFormFieldClassOn'] = true;
///used for @name like shortcuts in Display::get template array.  See example in system/display/aliases.php
#$config['aliasesFiles'] = '::instanceFolder::display/aliases.php';
//+	}

//+	CRUD related config {
//what to call when CRUD model encounters a bad id
$config['CRUDbadIdCallback'] = 'badId';
//+	}

///email unique identifier; 
/**when sending an email, you have to generate a message id.  To prevent collisions, this id will be used in addition to some random string*/
$config['emailUniqueId'] = 'php.framework.thoughtpush.com';

///cookie default options for use by Cookie class
$config['cookieDefaultOptions'] = array(
		'expire' => 0,
		'path'	=> '/',
		'domain' => null,
		'secure' => null,
		'httpsonly'=> null
	);


///time zone (db, internal functions)
$config['timezone'] = 'UTC';
//input taken from and output given to user
$config['inOutTimezone'] = 'America/Los_Angeles';

///string or array to call for special preloader files with relative path to instance/preloading/
$config['preloaders'] = '../../siteConfig.php';

///what should be included in Page::$in.  Note, if both get and post are included, variables will be aggregated not overwritten
$config['pageIn'] = array('get','post');
///whether to parse input using php '[]' special syntax
$config['pageInPHPStyle'] = true;