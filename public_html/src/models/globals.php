<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/abstract.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user/listservice.php';



class GlobalAnimeData extends GlobalAMData {
	use AnimeModelDecorator;
}

class GlobalMangaData extends GlobalAMData {
	use MangaModelDecorator;
}

abstract class GlobalAMData {
	protected $scoreDistribution = null;
	protected $dbSize = 0;

	public function __construct() {
		$this->scoreDistribution = new ScoreDistribution();
		$this->scoreDistribution->disableEntries();
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
	private $usersCF;
	private $allUsers = [];

	public function __construct() {
		$this->amData = [
			AMModel::TYPE_ANIME => new GlobalAnimeData(),
			AMModel::TYPE_MANGA => new GlobalMangaData(),
		];
		$this->usersCF = [
			AMModel::TYPE_ANIME => [],
			AMModel::TYPE_MANGA => [],
		];
	}

	public function getUserCount() {
		return count($this->allUsers);
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

	public function getCoolUsersForCF($type) {
		return $this->usersCF[$type];
	}



	public function addUser(UserEntry $user) {
		if (!$user->getID()) {
			return;
		}

		if (isset($this->allUsers[$user->getUserName()])) {
			return;
		}
		$this->allUsers[$user->getUserName()] = true;

		foreach (AMModel::getTypes() as $type) {
			$distro = $user->getList($type)->getScoreDistributionForCF();
			//filter out uninteresting sources
			if ($distro->getRatedCount() >= 50 and $distro->getStandardDeviation() >= 1.5) {
				$this->usersCF[$type] []= $user->getUserName();
			}
		}
		foreach (AMModel::getTypes() as $type) {
			$this->getAMData($type)->addUser($user);
		}

		$this->setGenerationTime(time());
	}

	public function delUser(UserEntry $user) {
		if (!$user->getID()) {
			return;
		}

		if (!isset($this->allUsers[$user->getUserName()])) {
			return;
		}
		unset($this->allUsers[$user->getUserName()]);

		foreach (AMModel::getTypes() as $type) {
			$name = $user->getUserName();
			$this->usersCF[$type] = array_filter($this->usersCF[$type], function($subName) use ($name) { return $subName != $name; });
		}
		foreach (AMModel::getTypes() as $type) {
			$this->getAMData($type)->delUser($user);
		}

		$this->setGenerationTime(time());
	}


}

class GlobalsModel extends AbstractModel {
	protected function keyToPath($key) {
		return null;
	}

	public function getReal($key) {
		$globalData = new GlobalData();
		$globalData->setGenerationTime(time());
		$globalData->setExpirationTime(null);
		return $globalData;
	}

	private static $handle = null;
	private static $writing = false;
	private static function setWriting($writing) {
		if ($writing != self::$writing) {
			self::$writing = $writing;
			self::$handle = null;
		}
	}

	private static function getFileHandle() {
		if (self::$handle !== null) {
			return self::$handle;
		}
		$path = ChibiConfig::getInstance()->misc->globalsCacheDir;
		$handle = fopen($path, 'c+b');
		if (!$handle) {
			return null;
		}
		if (!flock($handle, self::$writing ? LOCK_EX : LOCK_SH)) {
			fclose($handle);
			throw new LockException();
		}
		self::$handle = $handle;
		return $handle;
	}

	public function __construct() {
		register_shutdown_function(function() {
			$handle = self::$handle;//getFileHandle();
			if ($handle !== null) {
				flock($handle, LOCK_UN);
				fclose($handle);
				self::$handle = null;
			}
		});
	}

	public function getCached($key) {
		$handle = self::getFileHandle();
		if ($handle !== null) {
			fseek($handle, 0, SEEK_END);
			$fileSize = ftell($handle);
			fseek($handle, 0, SEEK_SET);
			if ($fileSize > 0) {
				$data = fread($handle, $fileSize);
				return unserialize(gzuncompress($data));
			}
		}
		return null;
	}

	public function put($key, $data) {
		$data = gzcompress(serialize($data));
		$handle = self::getFileHandle();
		fseek($handle, 0, SEEK_SET);
		ftruncate($handle, 0);
		fwrite($handle, $data);
		return true;
	}

	public static function getData() {
		return (new self())->get(null);
	}

	public static function putData($data) {
		return (new self())->put(null, $data);
	}

	public static function addUser(UserEntry $user) {
		self::setWriting(true);
		$data = self::getData();
		$data->addUser($user);
		self::putData($data);
		self::setWriting(false);
	}

	public static function delUser(UserEntry $user) {
		self::setWriting(true);
		$data = self::getData();
		$data->delUser($user);
		self::putData($data);
		self::setWriting(false);
	}
}
