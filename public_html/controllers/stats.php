<?php
require_once 'controllers/abstract.php';
class StatsController extends AbstractController {
	public function init() {
		parent::init();
	}

	private function sanitizeAM($am) {
		if ($am != UserModel::USER_LIST_TYPE_MANGA) {
			$am = UserModel::USER_LIST_TYPE_ANIME;
		}
		return $am;
	}

	private function sanitizeAction($a) {
		if (!in_array($a, ['profile', 'list', 'score', 'act', 'fav', 'misc', 'sug', 'json'])) {
			$a = 'profile';
		}
		return $a;
	}


	/*
	 * Redirect to comparison mode from top search field
	 */
	public function searchAction() {
		if (empty($_POST['user-name'])) {
			throw new Exception('Empty user name.');
		}
		if ($_POST['submit'] == 'compare') {
			if (empty($_POST['active-user-name'])) {
				throw new Exception('Trying to compare an user to noone (?).');
			}
			$u = [$_POST['active-user-name'], $_POST['user-name']];
		} else {
			$u = [$_POST['user-name']];
		}

		$am = null;
		if (!empty($_POST['am'])) {
			$am = $_POST['am'];
		}
		$am = $this->sanitizeAM($am);

		$action = null;
		if (!empty($_POST['action'])) {
			$action = $_POST['action'];
		}
		$action = $this->sanitizeAction($action);

		$this->forward($this->urlHelper->url('stats/' . $action, ['u' => $u, 'am' => $am]));
	}


	/*
	 * Set anime-manga switch
	 */
	public function switchAmAction() {
		if (empty($_REQUEST['am'])) {
			throw new Exception('Something went wrong.');
		}
		$am = $this->sanitizeAM($_REQUEST['am']);
		$this->forward($this->urlHelper->url('stats/' . $_REQUEST['action'], ['u' => $_REQUEST['u'], 'am' => $am]));
	}


	/**
	 * Regenerate user from cache, if it has expired
	 */
	public function regenerateAction() {
		//discard session information to speed up things
		session_write_close();

		$key = $_GET['user-name'];
		if (empty($key)) {
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Empty user name.';
			return;
		}

		$modelUsers = new UserModel();
		$modelUsers->allowUpdate(true);
		$user = $modelUsers->get($key);
		$this->config->chibi->runtime->layoutName = null;
		header('Content-Type: text/plain; charset=utf-8');
		echo $user['expires'];
	}


	/*
	 * Load all users information
	 */
	private function loadUsers() {
		//no user specified
		if (empty($_GET['u'])) {
			throw new Exception('Empty user name.');
		}
		$userNames = $_GET['u'];
		//user is not an array
		if (!is_array($userNames)) {
			$userNames = array($userNames);
		}
		//primary user appears more than once
		if (count(array_keys($userNames, $userNames[0])) > 1) {
			throw new Exception('Why would you want to compare yourself with&hellip; yourself?&hellip;');
		}
		//make users unique
		$userNames = array_unique($userNames);
		//make sure only two users are compared
		if (count($userNames) > 2) {
			throw new Exception('Sorry. We haven\'t implemented this.');
		}
		$this->view->userNames = $userNames;

		$modelUsers = new UserModel();
		$this->view->users = [];
		foreach ($this->view->userNames as $userName) {
			$user = $modelUsers->get($userName);
			if (empty($user)) {
				$this->forward($this->urlHelper->url('index/wrong-user', ['user-name' => $userName]));
			}
			$this->view->users []= $user;
		}

		//load anime-manga switch
		if (empty($_GET['am'])) {
			throw new Exception('Something went wrong.');
		}
		$am = $this->sanitizeAM($_GET['am']);
		$this->view->am = $am;
	}

	private function loadEntries() {
		$models = [];
		$models[UserModel::USER_LIST_TYPE_ANIME] = new AnimeModel();
		$models[UserModel::USER_LIST_TYPE_MANGA] = new MangaModel();
		foreach ($this->view->users as $i => $u) {
			foreach ($models as $am => $model) {
				$entries = $u[$am]['entries'];
				$nentries = [];
				foreach ($entries as $k => $e) {
					$key = $e['id'];
					$e2 = $model->get($key);
					if (!empty($e2)) {

						//add additional info
						if ($am == UserModel::USER_LIST_TYPE_MANGA) {
							$duration = 10;
							$length = $e['chapters-completed'];
						} else {
							$duration = $e2['duration'];
							$length = $e['episodes-completed'];
						}
						$e['total-duration'] = $length * $duration;

						$nentries[$key] = ['user' => $e, 'full' => $e2];
					}
				}
				$this->view->users[$i][$am]['entries'] = $nentries;
			}
		}
	}

	private function loadUniqueness() {
		foreach ($this->view->users as $i => &$u) {
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				$e['user']['unique'] = true;
				$e['others'] = [];
			}
		}

		if (count($this->view->users) > 1) {
			$sortFuncs['unique'] = function($a, $b) { return $a['user']['unique'] - $b['user']['unique']; };
			foreach ($this->view->users as $i => &$u) {
				foreach ($this->view->users as $j => &$u2) {
					if ($i == $j) {
						continue;
					}
					foreach ($u[$this->view->am]['entries'] as $k => &$e) {
						$key = $e['full']['id'];
						$l2 = &$u2[$this->view->am]['entries'];
						if (!empty($l2[$key])) {
							$e2 = $l2[$key];
							$e['others'] []= &$e2;
						}
					}
				}
				foreach ($u[$this->view->am]['entries'] as $k => &$e) {
					$e['user']['unique'] = empty($e['others']);
				}
			}
		}
	}

	private function sort(array &$subject, $defaultSortColumn = null, array $customColumns = null) {
		//get sort column
		$sortColumn = null;
		if (isset($_GET['sort-column'])) {
			$sortColumn = $_GET['sort-column'];
		} elseif ($defaultSortColumn != null) {
			$sortColumn = $defaultSortColumn;
		}
		$this->view->sortColumn = $sortColumn;

		//get sort direction
		$sortDir = 0;
		if (isset($_GET['sort-dir'])) {
			$sortDir = intval($_GET['sort-dir']);
		}
		$this->view->sortDir = $sortDir;

		if (empty($subject)) {
			return;
		}

		//some common sorting flavours
		$defs = [];
		$defs['score'] = [0, function($e) { return $e['user']['score']; }];
		$defs['status'] = [1, function($e) {
			//sort statuses like MAL order
			$statuses = array_flip([
				UserModel::USER_LIST_STATUS_WATCHING,
				UserModel::USER_LIST_STATUS_COMPLETED,
				UserModel::USER_LIST_STATUS_ONHOLD,
				UserModel::USER_LIST_STATUS_DROPPED,
				UserModel::USER_LIST_STATUS_PLANNED,
				UserModel::USER_LIST_STATUS_UNKNOWN,
			]);
			return $statuses[$e['user']['status']];
		}];
		$defs['length'] = [0, function($e) { return $e['full']['type'] == UserModel::USER_LIST_TYPE_MANGA ? $e['full']['volumes'] : $e['full']['episodes']; }];
		$defs['title'] = [1, function($e) { return strtolower($e['full']['title']); }];
		$defs['unique'] = [1, function($e) { return $e['user']['unique']; }];

		//load custom sorting flavours
		if (!empty($customColumns)) {
			foreach ($customColumns as $k => $def) {
				$defs[$k] = $def;
			}
		}

		//do sort
		$sort = array_fill_keys(array_keys($defs), []);
		$sortDirs = [];
		foreach ($defs as $key => $def) {
			list($defDefaultDir, $defFunc) = $def;
			$sortDirs[$key] = $defDefaultDir;
		}

		foreach ($subject as $k => &$e) {
			foreach ($defs as $defK => $def) {
				list($defDefaultDir, $defFunc) = $def;
				$sort[$defK][$k] = $defFunc($e);
			}
		}
		if (empty($defs[$sortColumn])) {
			$sortColumn = array_keys($defs)[0];
		}

		array_multisort($sort[$sortColumn], $sortDirs[$sortColumn] ^ $sortDir ? SORT_ASC : SORT_DESC, $sort['title'], SORT_ASC, $subject);
	}



	public function profileAction() {
		$this->loadUsers();
	}



	public function listAction() {
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/table.css'));

		$this->loadUsers();
		$this->loadEntries();
		$this->loadUniqueness();

		foreach ($this->view->users as $i => &$u) {
			$this->sort($u[$this->view->am]['entries'], 'score');
		}
	}



	public function scoreAction() {
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/table.css'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));

		$this->loadUsers();
		$this->loadEntries();
		$this->loadUniqueness();

		//prepare info for view
		foreach ($this->view->users as $i => &$u) {
			$scoreDist = array_fill_keys(range(10, 0), 0);
			$scoreTimeDist = array_fill_keys(range(10, 0), 0);
			$scoreInfo = [];
			$scoreInfo['total'] = 0;
			$scoreInfo['planned'] = 0;
			$scoreInfo['unrated-titles'] = [];
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				if ($e['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED) {
					$scoreInfo['planned'] ++;
					continue;
				}
				$scoreInfo['total'] ++;
				$scoreDist[$e['user']['score']] ++;
				$scoreTimeDist[$e['user']['score']] += $e['user']['total-duration'];
				if ($e['user']['score'] == 0) {
					$scoreInfo['unrated-titles'] []= &$e;
				}
			}

			//conert minutes to hours
			foreach ($scoreTimeDist as $k => &$v) {
				$v /= 60.0;
			}

			//calculate rated and unrated count
			$scoreInfo['unrated'] = $scoreDist[0];
			$scoreInfo['rated'] = array_sum($scoreDist) - $scoreDist[0];
			$scoreInfo['unrated-total-time'] = $scoreTimeDist[0];
			$scoreInfo['rated-total-time'] = array_sum($scoreTimeDist) - $scoreTimeDist[0];

			//calculate mean
			$scoreInfo['mean'] = 0;
			foreach ($scoreDist as $score => $count) {
				$scoreInfo['mean'] += $score * $count;
			}
			$scoreInfo['mean'] /= max(1, $scoreInfo['rated']);

			//calculate standard deviation
			$scoreInfo['std-dev'] = 0;
			foreach ($u[$this->view->am]['entries'] as &$e) {
				if ($e['user']['score'] > 0) {
					$scoreInfo['std-dev'] += pow($e['user']['score'] - $scoreInfo['mean'], 2);
				}
			}
			$scoreInfo['std-dev'] /= max(1, $scoreInfo['rated'] - 1);
			$scoreInfo['std-dev'] = sqrt($scoreInfo['std-dev']);

			$u[$this->view->am]['score-info'] = $scoreInfo;
			$u[$this->view->am]['score-dist'] = $scoreDist;
			$u[$this->view->am]['score-time-dist'] = $scoreTimeDist;
			$this->sort($u[$this->view->am]['score-info']['unrated-titles'], 'length');
		}
	}




	public function jsonAction() {
		$this->loadUsers();
		$this->loadEntries();
	}
}
