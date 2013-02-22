<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/abstract.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user/listservice.php';

class LockException extends Exception { }


class GlobalAnimeData extends GlobalAMData {
	use AnimeModelDecorator;
}

class GlobalMangaData extends GlobalAMData {
	use MangaModelDecorator;
}

abstract class GlobalAMData {
	protected $scoreDistribution = null;
	protected $dbSize = null;

	public function __construct() {
		$this->scoreDistribution = new ScoreDistribution();
		$this->scoreDistribution->disableEntries();
		$this->dbSize = 0;
	}

	public function getDBSize() {
		return $this->dbSize;
	}

	public function getScoreDistribution() {
		return $this->scoreDistribution;
	}


	protected function updateDBSize() {
		$this->dbSize = 0;
		$model = AMModel::factory($this->getType());
		$x = microtime(true);
		foreach ($model->getKeys() as $key) {
			/*if ($model->get($key, AbstractModel::CACHE_POLICY_FORCE_CACHE)->isValid())*/ {
				$this->dbSize ++;
			}
		}
	}

	public function addUser(UserEntry $user) {
		$filter = UserListFilters::getCompleted();
		foreach ($user->getList($this->getType())->getEntries($filter) as $entry) {
			$this->scoreDistribution->addEntry($entry);
		}
		$this->updateDBSize();
	}

	public function delUser(UserEntry $user) {
		$filter = UserListFilters::getCompleted();
		foreach ($user->getList($this->getType())->getEntries($filter) as $entry) {
			$this->scoreDistribution->addToGroup($entry->getScore(), $entry, -1); //heh
		}
	}
}

class GlobalData extends AbstractModelEntry {
	private $amData;
	private $userCount;

	public function __construct() {
		$this->amData[AMModel::TYPE_ANIME] = new GlobalAnimeData();
		$this->amData[AMModel::TYPE_MANGA] = new GlobalMangaData();

		$this->userCount = 0;
	}

	public function getUserCount() {
		return $this->userCount;
	}

	public function isFresh() {
		return true;
	}

	public function getAnimeData() {
		return $this->getAMData(AMModel::TYPE_ANIME);
	}

	public function getMangaData() {
		return $this->getAMData(AMModel::TYPE_MANGA);
	}

	public function getAMData($type) {
		return $this->amData[$type];
	}



	public function addUser(UserEntry $user) {
		if (!$user->getID()) {
			return;
		}
		$this->userCount ++;
		foreach (AMModel::getTypes() as $type) {
			$this->getAMData($type)->addUser($user);
		}
		$this->setGenerationTime(time());
	}

	public function delUser(UserEntry $user) {
		if (!$user->getID()) {
			return;
		}
		$this->userCount --;
		foreach (AMModel::getTypes() as $type) {
			$this->getAMData($type)->delUser($user);
		}
		$this->setGenerationTime(time());
	}


}

class GlobalsModel extends AbstractModel {
	protected function keyToPath($key) {
		return ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/' . ChibiConfig::getInstance()->misc->globalsCacheFile;
	}

	public function getReal($key) {
		$globalData = new GlobalData();
		$globalData->setGenerationTime(time());
		$globalData->setExpirationTime(null);
		$modelUsers = new UserModel();
		$allUsers = $modelUsers->getKeys();

		return $globalData;
	}

	public static function getData() {
		return (new self())->get(null);
	}

	public static function putData($data) {
		return (new self())->put(null, $data);
	}

	private static $fp = null;
	private static function specialRead() {
		$path = (new self())->keyToPath(null);
		$fp = fopen($path, 'r+b');
		self::$fp = $fp;
		if (flock($fp, LOCK_EX)) {
			fseek($fp, 0, SEEK_END);
			$size = ftell($fp);
			fseek($fp, 0, SEEK_SET);
			if ($size == 0) {
				$return = (new self())->getReal(null);
			} else {
				$data = fread($fp, $size);
				$return = unserialize($data);
			}
		} else {
			fclose($fp);
			self::$fp = null;
			throw new LockException('Couldn\'t acquire lock for ' . $path);
		}
		return $return;
	}

	private static function specialPut($data) {
		$fp = self::$fp;
		if (empty(self::$fp)) {
			throw new LockException('Lost the lock!');
		}
		fseek($fp, 0, SEEK_SET);
		ftruncate($fp, 0);
		fwrite($fp, serialize($data));
		fflush($fp);
		flock($fp, LOCK_UN);
		fclose($fp);
	}

	public static function addUser(UserEntry $user) {
		$data = self::specialRead();
		$data->addUser($user);
		self::specialPut($data);
	}

	public static function delUser(UserEntry $user) {
		$data = self::specialRead();
		$data->delUser($user);
		self::specialPut($data);
	}
}
