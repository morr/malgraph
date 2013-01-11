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
	}

	public function privacyAction() {
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
			if (!preg_match('/^=?[-_0-9A-Za-z]{2,}$/', $userName)) {
				$this->forward($this->mgHelper->constructUrl('index', 'wrong-query?' . $userName));
				return;
			}
		}

		$this->forward($this->mgHelper->constructUrl('stats', $action, [], $userNames, $am));
	}



	public function regenerateAction() {
		header('Content-Type: text/plain; charset=utf-8');
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;

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
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/highcharts.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/themes/mg.js'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/infobox.css'));
		HeadHelper::setTitle('MALgraph - global stats');
		HeadHelper::setDescription('Global community statistics' . MGHelper::$descSuffix);

		// load stats from cache
		$path = ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/' . ChibiConfig::getInstance()->misc->globalsCacheFile;
		if (file_exists($path) and ((time() - filemtime($path)) < 24 * 3600)) {
			$globals = json_decode(file_get_contents($path), true);
		} else {
			// regenerate cache
			$modelUsers = new UserModel(true);
			$goal = 500;
			$users = [];
			$allUsers = $modelUsers->getKeys();
			$goal = min($goal, count($allUsers));

			$globals = [];
			foreach (AMModel::getTypes() as $am) {
				$globals[$am] = [
					'dist-score' => array_fill_keys(range(10, 0), 0)
				];
			}

			while (count($users) < $goal) {
				$userName = $allUsers[mt_rand() % count($allUsers)];
				if (isset($users[$userName])) {
					continue;
				}
				$user = $modelUsers->get($userName);

				/*
				// ignore users with profile younger than one year
				list($year, $month, $day) = explode('-', $user['join-date']);
				if (time() - mktime(0, 0, 0, $month, $day, $year) < 365 * 24 * 3600) {
					continue;
				}
				*/

				// all the work with single user goes here
				foreach (array_keys($globals) as $am) {
					$filter = UserListFilters::getCompleted();
					$entries = $user->getList($am)->getEntries($filter);
					foreach ($entries as $e) {
						$globals[$am]['dist-score'][$e->getScore()] ++;
					}
				}

				$users[$userName] = true;
			}

			// postprocess global stats
			foreach (AMModel::getTypes() as $am) {
				$globals[$am]['rated'] = array_sum($globals[$am]['dist-score']);
				$globals[$am]['unrated'] = $globals[$am]['dist-score'][0];
				$globals[$am]['mean-score'] = array_sum(array_map(function($s) use (&$globals, $am) { return $globals[$am]['dist-score'][$s] * $s; }, array_keys($globals[$am]['dist-score']))) / max(1, $globals[$am]['rated']);
				unset ($globals[$am]['dist-score'][0]);
			}

			file_put_contents($path, json_encode($globals));
		}

		$this->view->globals = $globals;
	}
}
