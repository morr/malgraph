<?php
require_once 'controllers/abstract.php';
class IndexController extends AbstractController {
	public function indexAction() {
		$this->view->userName = null;
	}

	public function wrongUserAction() {
		$this->view->userName = empty($_GET['u']) ? null : $_GET['u'];
	}

	public function aboutAction() {
	}

	public function privacyAction() {
	}

	public function netDownAction() {
	}
}
