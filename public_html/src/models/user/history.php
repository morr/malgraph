<?php
class UserAnimeHistory extends UserHistory {
	public function getType() {
		return AMModel::TYPE_ANIME;
	}
}

class UserMangaHistory extends UserHistory {
	public function getType() {
		return AMModel::TYPE_MANGA;
	}
}


abstract class UserHistory {
	private $entries = [];

	public abstract function getType();

	public function getEntries($callback = null) {
		if ($callback === null) {
			return $this->entries;
		} else {
			return array_filter($this->entries, $callback);
		}
	}

	public function getEntriesByDaysAgo($daysBack) {
		$day = date('Y-m-d', mktime(-24 * $daysBack));
		return $this->getEntries(function (UserHistoryEntry $a) use ($day) {
			return $a->getDate() == $day;
		});
	}

	public function addEntry(UserHistoryEntry $e) {
		$this->entries []= $e;
	}

	public function getAMModel() {
		static $model = null;
		if ($model === null) {
			switch ($this->getType()) {
				case AMModel::TYPE_ANIME:
					$model = new AnimeModel();
					break;
				case AMModel::TYPE_MANGA:
					$model = new MangaModel();
					break;
				default:
					throw new Exception('Bad type');
			}
		}
		return $model;
	}
}
