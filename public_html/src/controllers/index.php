<?php
require_once 'src/controllers/abstract.php';
class IndexController extends AbstractController {
	public function indexAction() {
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

	public function searchAction() {
		$action = $this->inputHelper->get('action-name');
		$userNames = $this->inputHelper->getStringSafe('user-names');
		$am = $this->inputHelper->getStringSafe('am');

		if ($this->inputHelper->get('submit') == 'compare') {
			$u = [reset($userNames), end($userNames)];
		} else {
			$u = [end($userNames)];
		}

		$this->forward($this->mgHelper->constructUrl('stats', $action, [], $u, $am));
	}

	public function regenerateAction() {
		//header('Content-Type: text/plain; charset=utf-8');
		//$this->config->chibi->runtime->layoutName = null;

		//discard session information to speed up things
		session_write_close();

		$key = $this->view->userNames;
		if (is_array($key)) {
			$key = reset($key);
		}
		if (empty($key)) {
			echo 'Empty user name.';
			return;
		}

		$modelUsers = new UserModel();
		try {
			$user = $modelUsers->get($key);
		} catch (Exception $e) {
		}

		echo $user['expires'];
	}


}
