#!/usr/bin/php
<?php
chdir(dirname(__FILE__) . '/../public_html');

require_once 'lib/router.php';
ChibiConfig::load('lib/base.ini');
ChibiConfig::load('../conf/malgraph.ini');
if (file_exists('../conf/local.ini'))
{
	ChibiConfig::load('../conf/local.ini');
}

ChibiConfig::getInstance()->chibi->runtime->rootFolder = realpath(dirname(__FILE__) . '/../public_html/');
$router = new ChibiRouter(dirname(__FILE__) . '/../public_html/');

require_once 'src/models/html.php';
ChibiRegistry::set('router', $router);

$model = new HTMLCacheModel();
foreach ($model->getKeys() as $key)
{
	echo basename($model->keyToPath($key)) . ' ';
	$entry = $model->get($key, AbstractModel::CACHE_POLICY_FORCE_CACHE);
	if (!$entry->isFresh())
	{
		echo 'out of date; deleting';
		$model->delete($key);
	}
	else
	{
		echo 'is fresh';
	}
	echo PHP_EOL;
}
