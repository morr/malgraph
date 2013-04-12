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
			$this->view->messageType = $_SESSION['message-type'];
			$this->view->message = $_SESSION['message'];
			unset($_SESSION['message']);
		}
	}


	private function error($message) {
		$_SESSION['message-type'] = 'error';
		$_SESSION['message'] = $message;
		$this->forward(UrlHelper::url('a/index'));
		exit;
	}

	private function success($message) {
		$_SESSION['message-type'] = 'success';
		$_SESSION['message'] = $message;
		$this->forward(UrlHelper::url('a/index'));
		exit;
	}



	public function removeHTMLCacheAction() {
		$model = new HTMLCacheModel();
		$k = 0;
		foreach ($model->getKeys() as $key) {
			$k += $model->delete($key);
		}
		$this->success($k . ' HTML cache file(s) removed');
	}



	public function resetGlobalsAction() {
		$modelUsers = new UserModel();
		foreach ($modelUsers->getKeys() as $key) {
			$modelUsers->delete($key);
		}

		$host = ChibiConfig::getInstance()->sql->host;
		$user = ChibiConfig::getInstance()->sql->user;
		$pass = ChibiConfig::getInstance()->sql->password;
		$db = ChibiConfig::getInstance()->sql->database;
		$table = ChibiConfig::getInstance()->misc->globalsTable;
		$conn = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass);

		$sql = 'DROP TABLE ' . $table;
		$q = $conn->prepare($sql);
		$q->execute();

		$this->success('Table removed ' . $conn->errorInfo()[2]);
	}



	public function amAction() {
		if (empty($_GET['am-id'])) {
			$this->error('ID cannot be empty.');
		}
		$ids = [];
		foreach (explode(',', $_GET['am-id']) as $tmp) {
			$id = trim($tmp);
			if (preg_match('/^(\d+)(\.\.|-)(\d+)$/', $id, $matches)) {
				$id1 = $matches[1];
				$id2 = $matches[3];
				$ids = array_merge($ids, range($id1, $id2));
			} else {
				$ids []= $id;
			}
		}
		$ids = array_unique($ids);
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
			case 'refresh':
				$start = microtime(true);
				foreach ($ids as $id) {
					$model->get($id, AbstractModel::CACHE_POLICY_FORCE_REAL);
				}
				$time = microtime(true) - $start;
				$this->success(count($ids) . ' title(s) refreshed in ' . sprintf('%.02f', $time) . 's');
				break;
			default:
				throw new Exception('Unknown action: ' . $_GET['action']);
		}
	}



	public function userAction() {
		if (empty($_GET['user-name'])) {
			$this->error('User name cannot be empty.');
		}
		$userName = $_GET['user-name'];
		$model = new UserModel(true);
		if (empty($_GET['action'])) {
			throw new Exception('Undefined action.');
		}

		switch ($_GET['action']) {
			case 'remove-html-cache':
				HTMLCacheModel::deleteUser($userName);
				$this->success($userName . ' cache deleted OK');
				break;
			case 'toggle-block':
				if (!$model->cacheExists($userName)) {
					$this->error($userName . ' does not exist in DB');
				}
				$user = $model->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);
				$user->getUserData()->setBlocked(!$user->getUserData()->isBlocked());
				$model->put($userName, $user);
				HTMLCacheModel::deleteUser($userName);
				if ($user->getUserData()->isBlocked()) {
					$this->success($userName . ' is now blocked');
				} else {
					$this->success($userName . ' is no longer blocked');
				}
				break;
			case 'refresh':
				$start = microtime(true);
				try {
					$model->get($userName, AbstractModel::CACHE_POLICY_FORCE_REAL);
				} catch (InvalidEntryException $e) {
					$this->error($userName . ' does not even exist on MAL&hellip;');
				}
				$time = microtime(true) - $start;
				$this->success($userName . ' refreshed in ' . sprintf('%.02f', $time) . 's');
				break;
			default:
				throw new Exception('Unknown action: ' . $_GET['action']);
		}
	}
}
