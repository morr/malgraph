<?php
require_once 'controllers/abstract.php';
class StatsController extends AbstractController {
	public function init() {
		parent::init();
		session_write_close();
	}

	public function regenerateAction() {
		$key = $this->view->userName;
		$modelUsers = new UserModel();
		$modelUsers->allowUpdate(true);
		$user = $modelUsers->get($key);
		$this->config->chibi->runtime->layoutName = null;
		header('Content-Type: text/plain; charset=utf-8');
		echo $user['expires'];
	}

	private function loadUser() {
		$modelUsers = new UserModel();
		$this->view->user = $modelUsers->get($this->view->userName);
		if (empty($this->view->user)) {
			$this->forward($this->urlHelper->url('index/wrong-user', array('user-name' => $this->view->userName)));
			//throw new Exception('User "' . $this->view->user['userName'] . '" not found.');
		}
	}

	public function indexAction() {
		$this->loadUser();
	}

	public function jsonAction() {
		$this->loadUser();
	}
}
