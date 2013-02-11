<?php
ini_set('memory_limit', '120M');

require 'lib/routers/basic.php';
if (file_exists('../conf.ini')) {
	ChibiConfig::load('../conf.ini');
}
if (file_exists('../local.ini')) {
	foreach (parse_ini_file('../local.ini', true) as $section => $values) {
		foreach ($values as $key => $value) {
			ChibiConfig::getInstance()->$section->$key = $value;
		}
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
