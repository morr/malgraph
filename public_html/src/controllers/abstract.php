<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user.php';

class AbstractController extends ChibiController {
	public function init() {
		date_default_timezone_set('UTC');
		if (!session_id()) {
			session_start();
		}

		$this->view = ChibiRegistry::getView();
		$this->sessionHelper = $this->view->sessionHelper = ChibiRegistry::getHelper('session');
		$this->inputHelper = $this->view->inputHelper = ChibiRegistry::getHelper('input');
		$this->mediaHelper = $this->view->mediaHelper = ChibiRegistry::getHelper('media');
		$this->mgHelper = $this->view->mgHelper = ChibiRegistry::getHelper('mg');

		$this->view->controllerName = ChibiConfig::getInstance()->chibi->runtime->controllerName;
		$this->view->actionName = ChibiConfig::getInstance()->chibi->runtime->actionName;

		//hash for prevention of race condition in ajax-driven user cache refreshing
		if (!isset($_SESSION['unique-hash'])) {
			$uniqueHash = md5('pepper-' . microtime(true) . mt_rand());
			$_SESSION['unique-hash'] = $uniqueHash;
		}
		$this->view->uniqueHash = $_SESSION['unique-hash'];

		//basic settings
		HeadHelper::setTitle('MALgraph');
		HeadHelper::setKeywords(['malgraph', 'anime', 'manga', 'statistics', 'stats']);
		HeadHelper::setDescription('MALgraph - an extension of your MyAnimeList profile. Check your rating distribution, get anime or manga recommendations, and compare numerous stats with other kawaii Japanese otaku.');
		HeadHelper::setFavicon(UrlHelper::url('media/img/favicon.png'));

		MediaHelper::addMedia([MediaHelper::CORE, MediaHelper::JSCROLLPANE]);
		if (file_exists($p = 'media/css/'. $this->view->controllerName . '.css')) { HeadHelper::addStylesheet(UrlHelper::url($p)); }
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '.js')) { HeadHelper::addScript(UrlHelper::url($p)); }
		if (file_exists($p = 'media/css/' . $this->view->controllerName . '-' . $this->view->actionName . '.css')) { HeadHelper::addStylesheet(UrlHelper::url($p)); }
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '-' . $this->view->actionName . '.js')) { HeadHelper::addScript(UrlHelper::url($p)); }
	}

}
