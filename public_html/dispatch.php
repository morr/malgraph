<?php
require 'lib/routers/basic.php';
$request = $_GET['request'];
$configPaths = array(
	'conf.ini',
	'../conf.ini',
);
foreach ($configPaths as $p) {
	if (file_exists($p)) {
		new BasicRouter(dirname($_SERVER['SCRIPT_FILENAME']), $p, $request);
		die;
	}
}
header('Content-Type: text/plain; charset=utf-8');
echo 'Configuration file not found - tried: ' . PHP_EOL;
echo join(PHP_EOL, $configPaths);
die;
