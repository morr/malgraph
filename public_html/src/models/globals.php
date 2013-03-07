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
	private $usersCF;

	public function __construct() {
		$this->amData = [
			AMModel::TYPE_ANIME => new GlobalAnimeData(),
			AMModel::TYPE_MANGA => new GlobalMangaData(),
		];
		$this->usersCF = [
			AMModel::TYPE_ANIME => [],
			AMModel::TYPE_MANGA => [],
		];
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

	public function getCoolUsersForCF($type) {
		return $this->usersCF[$type];
	}



	public function addUser(UserEntry $user) {
		if (!$user->getID()) {
			return;
		}

		foreach (AMModel::getTypes() as $type) {
			$distro = $user->getList($type)->getScoreDistributionForCF();
			//filter out uninteresting sources
			if ($distro->getRatedCount() >= 50 and $distro->getStandardDeviation() >= 1.5) {
				$this->usersCF[$type] []= $user->getUserName();
			}
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

		foreach (AMModel::getTypes() as $type) {
			$name = $user->getUserName();
			$this->usersCF[$type] = array_filter($this->usersCF[$type], function($subName) use ($name) { return $subName != $name; });
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
		return null;
	}

	public function getReal($key) {
		$globalData = new GlobalData();
		$globalData->setGenerationTime(time());
		$globalData->setExpirationTime(null);
		return $globalData;
	}

	public static function getData() {
		return (new self())->get(null);
	}

	public static function putData($data) {
		return (new self())->put(null, $data);
	}

	private static $conn = null;
	private static function specialRead() {
		$host = ChibiConfig::getInstance()->sql->host;
		$user = ChibiConfig::getInstance()->sql->user;
		$pass = ChibiConfig::getInstance()->sql->password;
		$db = ChibiConfig::getInstance()->sql->database;
		$table = ChibiConfig::getInstance()->misc->globalsTable;
		$conn = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass);
		self::$conn = $conn;

		$sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (`id` INT NOT NULL, `data` MEDIUMBLOB NOT NULL, PRIMARY KEY (`id`))';
		$q = $conn->prepare($sql);
		$q->execute();

		$sql = 'LOCK TABLES ' . $table . ' WRITE';
		$q = $conn->prepare($sql);
		$q->execute();

		$sql = 'SELECT * FROM ' . $table;
		$q = $conn->prepare($sql);
		$q->execute();
		$q->bindColumn(1, $id);
		$q->bindColumn(2, $data);
		$row = $q->fetch(PDO::FETCH_BOUND);

		if ($row and $data) {
			$return = unserialize(gzuncompress($data));
		} else {
			$return = (new self())->getReal(null);

			$id = 1;
			$data = gzcompress(serialize($return));
			$sql = 'INSERT INTO ' . $table . '(id,data) VALUES(?,?)';
			$q = $conn->prepare($sql);
			$q->bindParam(1, $id);
			$q->bindParam(2, $data);
			$q->execute();
		}
		return $return;
	}

	public function getCached($key) {
		$return = self::specialRead();
		self::$conn = null;
		return $return;
	}

	private static function specialPut($return) {
		$conn = self::$conn;
		$table = ChibiConfig::getInstance()->misc->globalsTable;

		$data = gzcompress(serialize($return));
		$sql = 'UPDATE ' . $table . ' SET data=?';
		$q = $conn->prepare($sql);
		$q->bindParam(1, $data);
		$q->execute();

		$sql = 'UNLOCK TABLES';
		$q = $conn->prepare($sql);
		$q->execute();

		self::$conn = null;
		return true;
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
