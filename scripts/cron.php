#!/usr/bin/php
<?php
require_once dirname(__FILE__) . '/../public_html/lib/routers/basic.php';
ChibiConfig::load(dirname(__FILE__) . '/../conf.ini');
$config = ChibiConfig::getInstance();
$config->chibi->runtime->rootFolder = realpath(dirname(__FILE__) . '/../public_html/');
$router = new BasicRouter(dirname(__FILE__) . '/../public_html/');
require_once dirname(__FILE__) . '/../public_html/src/models/html.php';
ChibiRegistry::set('router', $router);
$model = new HTMLCacheModel();
foreach ($model->getKeys() as $key) {
	echo basename($model->keyToPath($key)) . ' ';
	$entry = $model->get($key, AbstractModel::CACHE_POLICY_FORCE_CACHE);
	if (!$entry->isFresh()) {
		echo 'out of date; deleting';
		$model->delete($key);
	} else {
		echo 'is fresh';
	}
	echo PHP_EOL;
}
