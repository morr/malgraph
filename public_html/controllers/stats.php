<?php
require_once 'controllers/abstract.php';
class StatsController extends AbstractController {
	public function init() {
		parent::init();
		session_write_close();
	}

	public function searchAction() {
		if (empty($_POST['user-name'])) {
			throw new Exception('Empty user name.');
		}
		if ($_POST['submit'] == 'compare') {
			if (empty($_POST['active-user-name'])) {
				throw new Exception('Trying to compare an user to noone (?).');
			}
			$u = [$_POST['active-user-name'], $_POST['user-name']];
			if (count(array_unique($u)) == 1) {
				throw new Exception('Why would you want to compare yourself with&hellip; yourself?&hellip;');
			}
		} else {
			$u = [$_POST['user-name']];
		}

		$this->forward($this->urlHelper->url('stats/profile', ['u' => $u]));
	}

	/**
	 * Regenerate user from cache, if it has expired
	 */
	public function regenerateAction() {
		$key = $_GET['user-name'];
		if (empty($key)) {
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Empty user name.';
			return;
		}
		$key = $this->view->userName;
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
		if (empty($_GET['u'])) {
			throw new Exception('Empty user name.');
		}
		$this->view->userNames = $_GET['u'];
		if (!is_array($this->view->userNames)) {
			$this->view->userNames = array($this->view->userNames);
		}

		if (!empty($_SESSION['am'])) {
			$this->view->am = $_SESSION['am'];
		}
		if ($this->view->am != UserModel::USER_LIST_TYPE_MANGA) {
			$this->view->am = UserModel::USER_LIST_TYPE_ANIME;
		}

		$modelUsers = new UserModel();
		$this->view->users = [];
		foreach ($this->view->userNames as $userName) {
			$user = $modelUsers->get($userName);
			if (empty($user)) {
				$this->forward($this->urlHelper->url('index/wrong-user', ['user-name' => $userName]));
			}
			$this->view->users []= $user;
		}
	}

	public function profileAction() {
		$this->loadUsers();
	}

	public function jsonAction() {
		$this->loadUsers();
	}
}
