<?php
ini_set('memory_limit', '120M');

require 'lib/routers/basic.php';
$configPaths = array(
	'conf.ini',
	'../conf.ini',
);
foreach ($configPaths as $p) {
	if (file_exists($p)) {
		ChibiConfig::load($p);
	}
}
if (!ChibiConfig::getInstance()) {
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Configuration file not found - tried: ' . PHP_EOL;
	echo join(PHP_EOL, $configPaths);
	die;
}

$request = $_GET['request'];
$router = new BasicRouter(dirname($_SERVER['SCRIPT_FILENAME']));
ChibiRegistry::set('router', $router);

require_once 'src/models/html.php';
//if it's stats controller, try load it from cache
if (isset($_GET['u'])) {
	echo (new HTMLCacheModel())->get($_GET)->getContents();
} else {
	$router->handleRequest($request);
}
