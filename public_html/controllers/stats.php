<?php
require_once 'controllers/abstract.php';
class StatsController extends AbstractController {
	public function init() {
		parent::init();
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

		if (!empty($_POST['am'])) {
			$am = $_POST['am'];
		} else {
			$am = UserModel::USER_LIST_TYPE_ANIME;
		}

		$this->forward($this->urlHelper->url('stats/profile', ['u' => $u, 'am' => $am]));
	}


	/*
	 * Set anime-manga switch
	 */
	public function switchAmAction() {
		if (empty($_REQUEST['am'])) {
			throw new Exception('Something went wrong.');
		}
		$am = $_REQUEST['am'];
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
		$am = $_GET['am'];
		if ($am != UserModel::USER_LIST_TYPE_MANGA and $am != UserModel::USER_LIST_TYPE_ANIME) {
			throw new Exception('Something went wrong.');
		}
		$this->view->am = $am;
	}

	public function profileAction() {
		$this->loadUsers();
	}

	public function jsonAction() {
		$this->loadUsers();
	}
}
