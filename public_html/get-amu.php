#!/usr/bin/php
<?php
require_once 'lib/router.php';
ChibiConfig::load('../conf.ini');
$config = ChibiConfig::getInstance();
$config->chibi->runtime->rootFolder = dirname(__FILE__);
ChibiRegistry::getInstance()->loadHelper('mg');

if (count($argv) < 3) {
	die('Usage: ' . basename(__FILE__) . ' anime|manga id' . PHP_EOL);
}

array_shift($argv);
$type = array_shift($argv);
$id1 = array_shift($argv);
if (count($argv)) {
	$id2 = array_shift($argv);
} else {
	$id2 = null;
}

switch (strtolower($type)) {
	case 'anime':
		require_once 'models/anime.php';
		$model = new AnimeModel();
		break;
	case 'manga':
		require_once 'models/manga.php';
		$model = new MangaModel();
		break;
	case 'user':
		require_once 'models/user.php';
		$model = new UserModel();
		break;
	default:
		die('Unknown type: ' . $type . PHP_EOL);
}

function get($model, $id) {
	echo str_pad($id, 16, ' ', STR_PAD_LEFT) . ': ';
	$start = microtime(true);
	$ok = false;
	try {
		$entry = $model->get($id);
		$ok = true;
	} catch (InvalidEntryException $x) {
	}
	$end = microtime(true);
	printf('%05.3f ', $end - $start);
	if ($ok) {
		echo 'OK';
	} else {
		echo 'Not found';
	}
	echo PHP_EOL;
}

if (!empty($id2)) {
	for ($id = $id1; $id <= $id2; $id ++) {
		get($model, $id);
	}
} else {
	get($model, $id1);
}
