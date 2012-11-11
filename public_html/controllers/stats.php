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
						$nentries[$key] = ['user' => $e, 'full' => $e2];
					}
				}
				$this->view->users[$i][$am]['entries'] = $nentries;
			}
		}
	}


	public function profileAction() {
		$this->loadUsers();
	}

	public function listAction() {
		$this->loadUsers();
		$this->loadEntries();

		$sortFuncs = [
			'score' => function($a, $b) { return $b['user']['score'] - $a['user']['score']; },
			'title' => function($a, $b) { return strcasecmp($a['full']['title'], $b['full']['title']); },
		];

		//get sort column
		$sortColumn = null;
		if (isset($_GET['sort-column'])) {
			$sortColumn = $_GET['sort-column'];
		}
		if (!isset($sortFuncs[$sortColumn])) {
			$sortColumn = 'score';
		}
		$this->view->sortColumn = $sortColumn;

		//get sort direction
		$sortDir = 0;
		if (isset($_GET['sort-dir'])) {
			$sortDir = intval($_GET['sort-dir']);
		}
		$this->view->sortDir = $sortDir;

		//make sort func
		$sortFunc = $sortFuncs[$sortColumn];
		if ($sortDir) {
			$sortFunc = function($a, $b) use ($sortFunc) { return $sortFunc($b, $a); };
		}

		//do sort
		foreach ($this->view->users as &$user) {
			uasort($user[$this->view->am]['entries'], $sortFunc);
		}
	}

	public function jsonAction() {
		$this->loadUsers();
		$this->loadEntries();
	}
}
