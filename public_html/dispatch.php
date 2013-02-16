<?php
ini_set('memory_limit', '120M');

require 'lib/routers/basic.php';
if (file_exists('../conf')) {
	ChibiConfig::load('../conf');
}
if (!ChibiConfig::getInstance()) {
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Configuration directory not found';
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
