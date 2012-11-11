#!/usr/bin/php
<?php
require_once 'lib/router.php';
ChibiConfig::load('../conf.ini');
$config = ChibiConfig::getInstance();
$config->chibi->runtime->rootFolder = dirname(__FILE__);
ChibiRegistry::getInstance()->loadHelper('mg');

require_once 'models/anime.php';
require_once 'models/manga.php';

if (count($argv) < 3) {
	die('Usage: ' . basename(__FILE__) . ' anime|manga id' . PHP_EOL);
}

list(, $type, $id) = $argv;
switch ($type) {
	case 'anime':
		$model = new AnimeModel();
		break;
	case 'manga':
		$model = new MangaModel();
		break;
	default:
		die('Unknown type: ' . $type . PHP_EOL);
}
$model->get($id);
