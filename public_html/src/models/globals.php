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
		$model = $this->getAMModel();
		foreach ($model->getKeys() as $id) {
			$entry = $model->get($id, AbstractModel::CACHE_POLICY_FORCE_CACHE);
			if ($entry->isDead()) {
				continue;
			}
			$this->dbSize ++;
		}
	}

	public function getDBSize() {
		return $this->dbSize;
	}

	public function addEntry(UserListEntry $entry) {
		$filter = UserListFilters::getCompleted();
		if ($filter($entry)) {
			$this->scoreDistribution->addEntry($entry);
		}
	}

	public function finalize() {
	}

	public function getScoreDistribution() {
		return $this->scoreDistribution;
	}
}

class GlobalData extends AbstractModelEntry {
	private $amData;
	private $userCount;

	public function __construct() {
		$this->amData[AMModel::TYPE_ANIME] = new GlobalAnimeData();
		$this->amData[AMModel::TYPE_MANGA] = new GlobalMangaData();

		$this->userCount = 0;
		$model = new UserModel();
		foreach ($model->getKeys() as $id) {
			//$entry = $model->get($id, AbstractModel::CACHE_POLICY_FORCE_CACHE);
			$this->userCount ++;
		}
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
		/*$goal = 500;
		$users = [];
		$goal = min($goal, count($allUsers));

		/*while (count($users) < $goal) {
			$userName = next($allUsers);
			$allUsers[mt_rand() % count($allUsers)];
			if (isset($users[$userName])) {
				continue;
			}*/
		foreach ($allUsers as $userName) {
			$user = $modelUsers->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);

			/*
			// ignore users with profile younger than one year
			list($year, $month, $day) = explode('-', $user['join-date']);
			if (time() - mktime(0, 0, 0, $month, $day, $year) < 365 * 24 * 3600) {
				continue;
			}
			*/

			// all the work with single user goes here
			foreach (AMModel::getTypes() as $type) {
				$amData = $globalData->getAMData($type);
				$entries = $user->getList($type)->getEntries();
				foreach ($entries as $entry) {
					$amData->addEntry($entry);
				}
				$amData->finalize();
			}

			$users[$userName] = true;
		}

		return $globalData;
	}

	public static function getData() {
		return (new self())->get(null);
	}

}
