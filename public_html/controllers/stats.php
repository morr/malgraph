<?php
require_once 'controllers/abstract.php';
class StatsController extends AbstractController {
	public function indexAction() {
		$this->registry->loadModel('user');
		$model = new UserModel();
		$this->view->user = $model->get($this->view->userName);
	}
}
