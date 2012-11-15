<?php
require_once 'controllers/abstract.php';
class IndexController extends AbstractController {
	public function indexAction() {
		$this->view->userName = null;
	}

	public function wrongUserAction() {
		$this->view->userName = @$_GET['user-name'];
	}

	public function aboutAction() {
	}

	public function privacyAction() {
	}
}
