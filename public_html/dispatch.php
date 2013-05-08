<?php
ini_set('memory_limit', '120M');
date_default_timezone_set('UTC');
if (!session_id())
{
	session_start();
}

require_once 'lib/router.php';
require_once 'lib/config.php';
ChibiConfig::load('lib/base.ini');
ChibiConfig::load('../conf/malgraph.ini');
if (file_exists('../conf/local.ini'))
{
	ChibiConfig::load('../conf/local.ini');
}

class Bootstrap extends ChibiBootstrap
{
	public function __construct()
	{
		$this->setNames();
		//basic settings
		ChibiRegistry::getHelper('head')->setTitle('MALgraph');
		ChibiRegistry::getHelper('head')->setKeywords(['malgraph', 'anime', 'manga', 'statistics', 'stats']);
		ChibiRegistry::getHelper('head')->setDescription('MALgraph - an extension of your MyAnimeList profile. Check your rating distribution, get anime or manga recommendations, and compare numerous stats with other kawaii Japanese otaku.');
		ChibiRegistry::getHelper('head')->setFavicon(ChibiRegistry::getHelper('url')->url('media/img/favicon.png'));

	}

	public function setNames()
	{
		ChibiRegistry::getView()->controllerName = ChibiConfig::getInstance()->chibi->runtime->controllerName;
		ChibiRegistry::getView()->actionName = ChibiConfig::getInstance()->chibi->runtime->actionName;
	}

	public function beforeWork()
	{
		$this->setNames();
		ChibiRegistry::getHelper('media')->addMedia([MediaHelper::CORE, MediaHelper::JSCROLLPANE]);
		if (file_exists($p = 'media/css/'. ChibiRegistry::getView()->controllerName . '.css')) { ChibiRegistry::getHelper('head')->addStylesheet(ChibiRegistry::getHelper('url')->url($p)); }
		if (file_exists($p = 'media/js/' . ChibiRegistry::getView()->controllerName . '.js')) { ChibiRegistry::getHelper('head')->addScript(ChibiRegistry::getHelper('url')->url($p)); }
		if (file_exists($p = 'media/css/' . ChibiRegistry::getView()->controllerName . '-' . ChibiRegistry::getView()->actionName . '.css')) { ChibiRegistry::getHelper('head')->addStylesheet(ChibiRegistry::getHelper('url')->url($p)); }
		if (file_exists($p = 'media/js/' . ChibiRegistry::getView()->controllerName . '-' . ChibiRegistry::getView()->actionName . '.js')) { ChibiRegistry::getHelper('head')->addScript(ChibiRegistry::getHelper('url')->url($p)); }
	}
}

$request = $_GET['request'];
$router = new ChibiRouter(dirname($_SERVER['SCRIPT_FILENAME']), new Bootstrap());
ChibiRegistry::set('router', $router);

require_once 'src/models/html.php';
//if it's stats controller, try load it from cache
if (isset($_GET['u']))
{
	echo (new HTMLCacheModel())->get($_GET)->getContents();
}
else
{
	$router->handleRequest($request);
}
