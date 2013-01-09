<?php
class UserAnimeList extends UserList {
	public function getType() {
		return AMModel::TYPE_ANIME;
	}
}

class UserMangaList extends UserList {
	public function getType() {
		return AMModel::TYPE_MANGA;
	}
}

abstract class UserList {
	private $viewCount = 0;
	private $private = false;
	private $timeSpent = 0;
	private $entries = [];

	public abstract function getType();

	public function getViewCount() {
		return $this->viewCount;
	}

	public function setViewCount($viewCount) {
		$this->viewCount = $viewCount;
	}

	public function isPrivate() {
		return $this->private;
	}

	public function setPrivate($private) {
		$this->private = $private;
	}

	public function addEntry(UserListEntry $e) {
		$this->entries[$e->getID()] = $e;
	}

	public function getEntries($callback = null) {
		if ($callback === null) {
			return $this->entries;
		} else {
			return array_filter($this->entries, $callback);
		}
	}

	public function getEntryByID($id) {
		if (!isset($this->entries[$id])) {
			return null;
		}
		return $this->entries[$id];
	}

	public function getTimeSpent() {
		return $this->timeSpent;
	}

	public function setTimeSpent($timeSpent) {
		$this->timeSpent = $timeSpent;
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
