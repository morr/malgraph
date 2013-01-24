<?php
class UserAnimeHistory extends UserHistory {
	use AnimeModelDecorator;
}

class UserMangaHistory extends UserHistory {
	use MangaModelDecorator;
}


abstract class UserHistory {
	private $entries = [];

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
}
