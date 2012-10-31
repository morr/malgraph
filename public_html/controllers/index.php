<?php
require_once 'controllers/abstract.php';
class IndexController extends AbstractController {
	public function indexAction() {
		$this->view->something = 'hello';
		$this->headHelper->setTitle('Title of this page');
	}

	public function otherAction() {
	}
}
