<?php
require_once 'controllers/abstract.php';
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
}
