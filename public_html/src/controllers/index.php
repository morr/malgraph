<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user/listservice.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user.php';

class IndexController extends ChibiController {
	public function indexAction() {
		ChibiRegistry::getHelper('head')->addKeywords(['myanimelist', 'mal', 'rating', 'favorites', 'score']);
		ChibiRegistry::getView()->userName = null;
	}

	public function wrongUserAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - error: user not found');
		ChibiRegistry::getView()->userName = ChibiRegistry::getHelper('input')->getStringSafe('u');
	}

	public function blockedUserAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - error: user blocked');
		ChibiRegistry::getView()->userName = ChibiRegistry::getHelper('input')->getStringSafe('u');
	}

	public function aboutAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - about');
	}

	public function privacyAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - privacy policy');
	}

	public function netDownAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - network error');
	}

	public function wrongQueryAction() {
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - error: wrong query');
	}



	public function searchAction() {
		$action = ChibiRegistry::getHelper('input')->get('action-name');
		$userNames = ChibiRegistry::getHelper('input')->getStringSafe('user-names');
		$am = ChibiRegistry::getHelper('input')->getStringSafe('am');

		if (ChibiRegistry::getHelper('input')->get('submit') == 'compare') {
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
				$this->forward(ChibiRegistry::getHelper('mg')->constructUrl('index', 'wrong-query?' . $userName));
				return;
			}
		}

		$this->forward(ChibiRegistry::getHelper('mg')->constructUrl('stats', $action, [], $userNames, $am));
	}



	//hash for prevention of race condition in ajax-driven user cache refreshing
	public function getTokenAction() {
		header('Content-Type: text/plain; charset=utf-8');
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;

		if (!isset($_SESSION['unique-hash'])) {
			$_SESSION['unique-hash'] = md5('pepper-' . microtime(true) . mt_rand());
		}

		echo $_SESSION['unique-hash'];
		exit;
	}

	public function regenerateAction() {
		header('Content-Type: text/plain; charset=utf-8');
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;

		//confirm hash
		if ($_SESSION['unique-hash'] != ChibiRegistry::getHelper('input')->getStringSafe('unique-hash')) {
			echo 'invalid hash. (expected: ' . $_SESSION['unique-hash'] . ', got ' . ChibiRegistry::getHelper('input')->getStringSafe('unique-hash') . ')';
			return;
		}
		unset ($_SESSION['unique-hash']);

		//discard session information to speed up things
		ChibiRegistry::getHelper('session')->close();

		$key = ChibiRegistry::getHelper('input')->getStringSafe('user-name');
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
		ChibiRegistry::getHelper('head')->setTitle('MALgraph - global stats');
		ChibiRegistry::getHelper('head')->setDescription('Global community statistics' . MGHelper::$descSuffix);

		require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/globals.php';
		ChibiRegistry::getView()->globals = GlobalsModel::getData();
	}
}
