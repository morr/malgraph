#!/usr/bin/php
<?php
require_once dirname(__FILE__) . '/public_html/lib/router.php';
ChibiConfig::load(dirname(__FILE__) . '/conf.ini');
$config = ChibiConfig::getInstance();
$config->chibi->runtime->rootFolder = dirname(__FILE__) . '/public_html';
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
		require_once dirname(__FILE__) . '/public_html/src/models/anime.php';
		$model = new AnimeModel();
		break;
	case 'manga':
		require_once dirname(__FILE__) . '/public_html/src/models/manga.php';
		$model = new MangaModel();
		break;
	case 'user':
		require_once dirname(__FILE__) . '/public_html/src/models/user.php';
		$model = new UserModel();
		break;
	default:
		die('Unknown type: ' . $type . PHP_EOL);
}

function get($model, $id) {
	$start = microtime(true);
	$entry = null;

	echo sprintf('%+16s: ', $id);
	try {
		$entry = $model->get($id);
	} catch (InvalidEntryException $x) {
	} catch (DownloadException $x) {
	}

	$end = microtime(true);
	echo sprintf('%05.3f %s' . PHP_EOL, $end - $start, empty($entry) ? 'Not OK' : 'OK');
	return $entry;
}

if (!empty($id2)) {
	for ($id = $id1; $id <= $id2; $id ++) {
		get($model, $id);
	}
} else {
	get($model, $id1);
}
