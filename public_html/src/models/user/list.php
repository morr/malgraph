<?php
class UserAnimeList extends UserList {
	use AnimeModelDecorator;
}

class UserMangaList extends UserList {
	use MangaModelDecorator;
}

abstract class UserList {
	private $viewCount = 0;
	private $private = false;
	private $timeSpent = 0;
	private $entries = [];

	public function destroy() {
		$this->entries = null;
		unset($this->entries);
	}

	private $scoreDistribution = null;
	public function getScoreDistributionForCF() {
		return $this->scoreDistribution;
	}
	public function __construct() {
		$this->scoreDistribution = new ScoreDistribution();
		$this->scoreDistribution->disableEntries();
	}

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
		$filter = UserListFilters::getCompleted();
		if ($filter($e)) {
			$this->scoreDistribution->addEntry($e);
		}
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

}
