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

		//stylesheets
		HeadHelper::addStylesheet('http://fonts.googleapis.com/css?family=Open+Sans|Ubuntu');
		HeadHelper::addStylesheet(UrlHelper::url('media/css/bootstrap.min.css'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/jquery.jscrollpane.css'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/core.css'));
		if (file_exists($p = 'media/css/'. $this->view->controllerName . '.css')) {
			HeadHelper::addStylesheet(UrlHelper::url($p));
		}
		if (file_exists($p = 'media/css/' . $this->view->controllerName . '-' . $this->view->actionName . '.css')) {
			HeadHelper::addStylesheet(UrlHelper::url($p));
		}

		//scripts
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.min.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.mousewheel.min.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.jscrollpane.min.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.ui.position.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/core.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/glider.js'));
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '.js')) {
			HeadHelper::addScript(UrlHelper::url($p));
		}
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '-' . $this->view->actionName . '.js')) {
			HeadHelper::addScript(UrlHelper::url($p));
		}

	}

}
