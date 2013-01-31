<?php
require_once 'src/controllers/abstract.php';
require_once 'src/models/user/listservice.php';

class IndexController extends AbstractController {
	public function indexAction() {
		HeadHelper::addKeywords(['myanimelist', 'mal', 'rating', 'favorites', 'score']);
		$this->view->userName = null;
	}

	public function wrongUserAction() {
		$this->view->userName = $this->inputHelper->getStringSafe('u');
	}

	public function blockedUserAction() {
		$this->view->userName = $this->inputHelper->getStringSafe('u');
	}

	public function aboutAction() {
		HeadHelper::setTitle('MALgraph - about');
	}

	public function privacyAction() {
		HeadHelper::setTitle('MALgraph - privacy policy');
	}

	public function netDownAction() {
	}

	public function wrongQueryAction() {
	}



	public function searchAction() {
		$action = $this->inputHelper->get('action-name');
		$userNames = $this->inputHelper->getStringSafe('user-names');
		$am = $this->inputHelper->getStringSafe('am');

		if ($this->inputHelper->get('submit') == 'compare') {
			$userNames = [reset($userNames), end($userNames)];
			if (strpos(end($userNames), ',') !== false) {
				$userNames = array_slice(explode(',', end($userNames)), -2);
			}
		} else {
			$userNames = [end($userNames)];
			if (strpos(end($userNames), ',') !== false) {
				$userNames = array_slice(explode(',', end($userNames)), -2);
			}
		}

		foreach ($userNames as &$userName) {
			$userName = trim($userName);
			if (!preg_match('/^=?[-_0-9A-Za-z]{2,16}$/', $userName)) {
				$this->forward($this->mgHelper->constructUrl('index', 'wrong-query?' . $userName));
				return;
			}
		}

		$this->forward($this->mgHelper->constructUrl('stats', $action, [], $userNames, $am));
	}



	public function regenerateAction() {
		header('Content-Type: text/plain; charset=utf-8');
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;

		//confirm hash
		if ($_SESSION['unique-hash'] != $this->inputHelper->getStringSafe('unique-hash')) {
			echo 'invalid hash. (expected: ' . $_SESSION['unique-hash'] . ', got ' . $this->inputHelper->getStringSafe('unique-hash') . ')';
			return;
		}
		unset ($_SESSION['unique-hash']);

		//discard session information to speed up things
		$this->sessionHelper->close();

		$key = $this->inputHelper->getStringSafe('user-name');
		if (is_array($key)) {
			$key = reset($key);
		}
		if (empty($key)) {
			echo 'Empty user name.';
			return;
		}

		$modelUsers = new UserModel();
		try {
			$user = $modelUsers->get($key, AbstractModel::CACHE_POLICY_DEFAULT);
		} catch (Exception $e) {
		}

		echo $user->getExpirationTime();
	}



	public function globalsAction() {
		MediaHelper::addMedia([MediaHelper::HIGHCHARTS, MediaHelper::INFOBOX]);
		HeadHelper::setTitle('MALgraph - global stats');
		HeadHelper::setDescription('Global community statistics' . MGHelper::$descSuffix);

		require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/globals.php';
		$this->view->globals = GlobalsModel::getData();
	}
}
