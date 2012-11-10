<?php
require_once 'controllers/abstract.php';
class IndexController extends AbstractController {
	public function indexAction() {
		$this->view->userName = null;
	}

	public function searchAction() {
		if (empty($_POST['username'])) {
			throw new Exception('Empty user name.');
		}
		$this->forward($this->urlHelper->url('stats/index', array('user-name' => $_POST['username'])));
	}

	public function wrongUserAction() {
	}
}
