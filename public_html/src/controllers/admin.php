<?php
require_once 'src/controllers/abstract.php';
class AdminController extends AbstractController {
	public $message;

	private function loggedIn() {
		return isset($_SESSION['loggedIn']);
	}

	private function logOut() {
		if (!$this->loggedIn()) {
			return false;
		}
		unset($_SESSION['loggedIn']);
		return true;
	}

	private function logIn($credentials) {
		if ($this->loggedIn()) {
			return false;
		}
		if (sha1($credentials) == ChibiConfig::getInstance()->misc->adminPassword) {
			$_SESSION['loggedIn'] = true;
			return true;
		}
		return false;
	}



	public function init() {
		parent::init();

		if (!$this->loggedIn() and $this->view->actionName != 'login') {
			$this->forward(UrlHelper::url('/a/login'));
		}
	}

	public function loginAction() {
		//przyjmij input od usera
		if (isset($_POST['password'])) {
			$entered = $_POST['password'];
		} elseif (isset($_COOKIE['password'])) {
			$entered = $_COOKIE['password'];
		} else {
			return;
		}

		$this->logIn($entered);
		if ($this->loggedIn()) {
			//setcookie('password', $entered, time() + 3600 * 24 * 30, '/');
			$this->forward(UrlHelper::url('/a/index'));
			$this->mgHelper->log('Correct password');
		} else {
			$this->view->entered = $entered;
			$this->mgHelper->log('Wrong password: ' . $entered);
		}
	}

	public function logoutAction() {
		//setcookie('password', '', time() - 3600, '/');
		$this->logOut();
		$this->forward(UrlHelper::url(''));
	}



	public function indexAction() {
		if (!empty($_SESSION['message'])) {
			$this->view->message = $_SESSION['message'];
			unset($_SESSION['message']);
		}
	}


	private function success($message) {
		$_SESSION['message'] = $message;
		$this->forward(UrlHelper::url('a/index'));
		exit();
	}



	public function amAction() {
		if (empty($_GET['am-id'])) {
			throw new Exception('ID cannot be empty.');
		}
		$ids = $_GET['am-id'];
		$ids = explode(',', $ids);
		if (empty($_GET['am-model'])) {
			throw new Exception('Undefined model.');
		} else {
			switch (strtolower($_GET['am-model'])) {
				case 'anime':
					$model = new AnimeModel();
					break;
				case 'manga':
					$model = new MangaModel();
					break;
				default:
					throw new Exception('Unknown model: ' . $_GET['am-model']);
			}
		}

		switch ($_GET['action']) {
			case 'remove':
				$c1 = 0;
				$c2 = 0;
				foreach ($ids as $id) {
					if (!$model->cacheExists($id)) {
						$c2 ++;
					} else {
						$model->delete($id);
						$c1 ++;
					}
				}
				if ($c2 > 0) {
					$this->success($c1 . ' entries deleted OK');
				} else {
					$this->success($c1 . ' entries deleted OK, ' . $c2 . ' were already deleted');
				}
				break;
			case 'refresh':
				$start = microtime(true);
				foreach ($ids as $id) {
					$model->get($id, AbstractModel::CACHE_POLICY_FORCE_REAL);
				}
				$time = microtime(true) - $start;
				$this->success(count($ids) . ' entries refreshed in ' . sprintf('%.02f', $time) . 's');
				break;
			default:
				throw new Exception('Unknown action: ' . $_GET['action']);
		}
	}



	public function userAction() {
		if (empty($_GET['user-name'])) {
			throw new Exception('User name cannot be empty.');
		}
		$userName = $_GET['user-name'];
		$model = new UserModel(true);
		if (empty($_GET['action'])) {
			throw new Exception('Undefined action.');
		}

		switch ($_GET['action']) {
			case 'remove':
				if (!$model->cacheExists($userName)) {
					$this->success($userName . ' was already deleted');
				}
				$model->delete($userName);
				$this->success($userName . ' deleted OK');
				break;
			case 'toggle-block':
				$user = $model->get($userName);
				$user->getUserData()->setBlocked(!$user->getUserData()->isBlocked());
				$model->put($userName, $user);
				if ($user->getUserData()->isBlocked()) {
					$this->success($userName . ' marked is now blocked');
				} else {
					$this->success($userName . ' marked is no longer blocked');
				}
				break;
			case 'refresh':
				$start = microtime(true);
				$model->get($userName, AbstractModel::CACHE_POLICY_FORCE_REAL);
				$time = microtime(true) - $start;
				$this->success($userName . ' refreshed in ' . sprintf('%.02f', $time) . 's');
				break;
			default:
				throw new Exception('Unknown action: ' . $_GET['action']);
		}
	}
}
?>
