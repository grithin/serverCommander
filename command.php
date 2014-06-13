<?
require_once 'lib/preloader.php';
$root = realpath(dirname(__FILE__)).'/';

$args = getopt('s:c:a:',array('servers:','command:'));
$servers = $args['servers'] ? $args['servers'] : $args['s'];
$command = $args['command'] ? $args['command'] : $args['c'];
$action = $args['a'];

if(!$command && !$action){
	die("Usage (make sure to quote values):\n\t-c --command= : command id or text\n\t-s --servers= : server ids, comma separated\n\t-a : action. 'show servers' or 'show commands'\nNote: output written to lib/instance/info/log\n");
}

Db::initialize(array('dsn'=>'sqlite:'.$root.'db.sqlite3'));

//+	show commands {
if($action){
	switch($action){
		case 'show servers':
			var_export(Db::rows('server','1=1'));
		break;
		case 'show commands':
			var_export(Db::rows('server','1=1'));
		break;
	}
	exit;
}
//+	}

//+	validations {
if(Tool::isInt($command)){
	$command = Db::row('command',$command);
	if(!$command){
		die('Command id not matched');
	}
	if(!$servers){
		$servers = $command['default_servers'];
	}
	$command = $command['command'];
}

$servers = explode(',',$servers);
foreach($servers as $server){
	if($server = Db::row('server',$server)){
		$inDbServers[] = $server;
	}
}
if(!$inDbServers){
	die('No matching server ids');
}
//+	}

//write command to file (to avoid need for escape) and append "expect done" for expect to expect
file_put_contents('/tmp/serverCommander.command',$command."\necho 'expect done'");
//loop through servers, applying command
foreach($inDbServers as $server){
	$options = array();
	if($server['key_file']){
		$options[] = '-i '.$server['key_file'];
	}
	if($server['port']){
		$options[] = '-p '.$server['port'];
	}
	if(!$server['host']){
		$server['host'] = $server['ip'];
	}
	$sshCommand = 'send "ssh '.implode(' ',$options).' '.$server['user'].'@'.$server['host'].' < /tmp/serverCommander.command\n"';
	$expect = "set timeout 120\nspawn bash\n".$sshCommand."\nexpect {\n\t\"expect done\" {send \"exit\\n\"}\n\t\"Password:\" {send \"".$server['password']."\"}\n}\nsend \"exit\\n\"\nexpect eof";
	file_put_contents('/tmp/serverCommander.expect',$expect);
	exec('expect /tmp/serverCommander.expect',$out);
	Debug::toLog($out);
}
//clean up
unlink('/tmp/serverCommander.command');
unlink('/tmp/serverCommander.expect');
