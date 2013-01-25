#!/usr/bin/php
<?php
require_once dirname(__FILE__) . '/../public_html/lib/router.php';
ChibiConfig::load(dirname(__FILE__) . '/../conf.ini');
$config = ChibiConfig::getInstance();
$config->chibi->runtime->rootFolder = dirname(__FILE__) . '/../public_html';
require_once dirname(__FILE__) . '/../public_html/src/models/abstract.php';

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
if (count($argv)) {
	$cachePolicy = array_shift($argv);
	switch (strtolower($cachePolicy)) {
		case 'cache':
		case 'cached':
			$cachePolicy = AbstractModel::CACHE_POLICY_FORCE_CACHE;
			break;
		case 'real':
			$cachePolicy = AbstractModel::CACHE_POLICY_FORCE_REAL;
			break;
		default:
			die('Unknown cache policy: ' . $cachePolicy . PHP_EOL);
	}
} else {
	$cachePolicy = AbstractModel::CACHE_POLICY_DEFAULT;
}

switch (strtolower($type)) {
	case 'anime':
		require_once dirname(__FILE__) . '/../public_html/src/models/am.php';
		$model = new AnimeModel();
		break;
	case 'manga':
		require_once dirname(__FILE__) . '/../public_html/src/models/am.php';
		$model = new MangaModel();
		break;
	case 'user':
		require_once dirname(__FILE__) . '/../public_html/src/models/user.php';
		$model = new UserModel();
		break;
	default:
		die('Unknown type: ' . $type . PHP_EOL);
}

function get($model, $cachePolicy, $id) {
	$start = microtime(true);
	$entry = null;

	echo sprintf('%+16s: ', $id);
	try {
		$entry = $model->get($id, $cachePolicy);
	} catch (InvalidEntryException $x) {
	} catch (DownloadException $x) {
	}

	$end = microtime(true);
	echo sprintf('%05.3f %s' . PHP_EOL, $end - $start, empty($entry) ? 'Not OK' : 'OK');
	return $entry;
}

if (!empty($id2)) {
	for ($id = $id1; $id <= $id2; $id ++) {
		get($model, $cachePolicy, $id);
	}
} else {
	get($model, $cachePolicy, $id1);
}
