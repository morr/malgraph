<?php
class UserListSorters {
	public static function getByTitle() {
		return function(UserListEntry $entry1, UserListEntry $entry2) {
			return strcmp($entry1->getAMEntry()->getTitle(), $entry2->getAMEntry()->getTitle());
		};
	}

	public static function getByScore() {
		return function(UserListEntry $entry1, UserListEntry $entry2) {
			$x = $entry2->getScore() - $entry1->getScore();
			if ($x == 0) {
				$func = self::getByTitle();
				return $func($entry1, $entry2);
			}
			return $x;
		};
	}
}

class UserListFilters {
	public static function getNonPlanned() {
		return function (UserListEntry $entry) { return $entry->getStatus() != UserListENTRY::STATUS_PLANNED; };
	}

	public static function getCompleted() {
		return function (UserListEntry $entry) { return $entry->getStatus() == UserListENTRY::STATUS_COMPLETED; };
	}
}

class UserListService {
	public static function getMeanScore(array $entries) {
		$sum = 0;
		foreach ($entries as $entry) {
			$sum += $entry->getScore();
		}
		return $sum / max(1, count($entries));
	}

	public static function getTimeSpent(array $entries) {
		$sum = 0;
		foreach ($entries as $entry) {
			$sum += $entry->getCompletedDuration();
		}
		return $sum;
	}

	public static function getScoreDistribution(array $entries) {
		return new ScoreDistribution($entries);
	}

	public static function getScoreDurationDistribution(array $entries) {
		return new ScoreDurationDistribution($entries);
	}

	public static function getSubTypeDistribution(array $entries) {
		return new SubTypeDistribution($entries);
	}

	public static function getStatusDistribution(array $entries) {
		return new StatusDistribution($entries);
	}

	public static function getLengthDistribution(array $entries) {
		return new LengthDistribution($entries);
	}

	public static function getCreatorDistribution(array $entries) {
		return new CreatorDistribution($entries);
	}

	public static function getGenreDistribution(array $entries) {
		return new GenreDistribution($entries);
	}

	public static function getYearDistribution(array $entries) {
		return new YearDistribution($entries);
	}

	public static function getDecadeDistribution(array $entries) {
		return new DecadeDistribution($entries);
	}
}



class Distribution {
	protected $groups;
	protected $entries;

	const IGNORE_NULL_KEY = 1;

	public function __construct($nullGroupValue = null) {
		$this->groups = [];
		$this->entries = [];
		$this->keys = [];

		$this->nullGroupValue = $nullGroupValue;
	}

	protected function sortEntries() {
		foreach ($this->entries as $group => $entries) {
			uasort($entries, UserListSorters::getByTitle());
		}
	}

	protected function sortGroups() {
	}

	public function finalize() {
		$this->sortEntries();
		$this->sortGroups();
	}

	public function getNullGroupKey() {
		return $this->nullGroupValue;
	}

	protected function addGroup($key) {
		$this->keys[(string)$key] = $key;
		$this->groups[(string)$key] = 0;
		$this->entries[(string)$key] = [];
	}

	public function addToGroup($key, $entry) {
		if (!isset($this->groups[(string)$key])) {
			$this->keys[(string)$key] = $key;
			$this->groups[(string)$key] = 0;
			$this->entries[(string)$key] = [];
		}
		$this->groups[(string)$key] ++;
		$this->entries[(string)$key] []= $entry;
	}

	public function getGroupEntries($key) {
		if (!isset($this->entries[(string)$key])) {
			return null;
		}
		return $this->entries[(string)$key];
	}

	public function getGroupSize($key) {
		if (!isset($this->groups[(string)$key])) {
			return null;
		}
		return $this->groups[(string)$key];
	}



	public function getGroupsKeys($flags = 0) {
		$x = $this->keys;
		if ($flags & self::IGNORE_NULL_KEY) {
			unset($x[(string)$this->getNullGroupKey()]);
		}
		$x = array_values($x);
		return $x;
	}

	public function getGroupsEntries($flags = 0) {
		$x = $this->entries;
		if ($flags & self::IGNORE_NULL_KEY) {
			unset($x[(string)$this->getNullGroupKey()]);
		}
		$x = array_values($x);
		return $x;
	}

	public function getGroupsSizes($flags = 0) {
		$x = $this->groups;
		if ($flags & self::IGNORE_NULL_KEY) {
			unset($x[(string)$this->getNullGroupKey()]);
		}
		$x = array_values($x);
		return $x;
	}


	public function getLargestGroupSize($flags = 0) {
		$x = $this->getGroupsSizes($flags);
		return max($x);
	}

	public function getSmallestGroupSize($flags = 0) {
		$x = $this->getGroupsSizes($flags);
		return min($x);
	}

	public function getTotalSize($flags = 0) {
		$x = $this->groups;
		if ($flags & self::IGNORE_NULL_KEY) {
			unset($x[(string)$this->getNullGroupKey()]);
		}
		return array_sum($x);
	}
}


class ScoreDistribution extends Distribution {
	public function __construct(array $entries) {
		parent::__construct(0);
		foreach (range(10, 0) as $x) {
			$this->addGroup($x);
		}
		foreach ($entries as $entry) {
			$this->addToGroup($entry->getScore(), $entry);
		}
		$this->finalize();
	}

	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function getRatedCount() {
		return $this->getTotalSize(self::IGNORE_NULL_KEY);
	}

	public function getUnratedCount() {
		return $this->getGroupSize($this->getNullGroupKey());
	}

	public function getMeanScore() {
		$mean = 0;
		$totalSize = 0;
		foreach ($this->groups as $key => $size) {
			if ($key == $this->getNullGroupKey()) {
				continue;
			}
			$mean += $key * $size;
			$totalSize += $size;
		}
		if ($totalSize == 0) {
			return null;
		}
		return $mean / max(1, $totalSize);
	}

	public function getStandardDeviation() {
		$standardDeviation = 0;
		$meanScore = $this->getMeanScore();
		foreach ($this->groups as $score => $size) {
			if ($score != $this->getNullGroupKey()) {
				$standardDeviation += $size * pow($score - $meanScore, 2);
			}
		}
		$standardDeviation /= max(1, $this->getRatedCount() - 1);
		$standardDeviation = sqrt($standardDeviation);
		return $standardDeviation;
	}
}

class ScoreDurationDistribution extends ScoreDistribution {
	public function addToGroup($key, $entry) {
		if (!isset($this->groups[$key])) {
			$this->groups[$key] = 0;
			$this->entries[$key] = [];
		}
		$this->groups[$key] += $entry->getCompletedDuration();
		$this->entries[$key] []= $entry;
	}

	public function getTotalTime() {
		return $this->getTotalSize();
	}
}

class SubTypeDistribution extends Distribution {
	public function __construct(array $entries) {
		parent::__construct(0);
		foreach ($entries as $entry) {
			$this->addToGroup($entry->getAMEntry()->getSubType(), $entry);
		}
		$this->finalize();
	}
}

class StatusDistribution extends Distribution {
	public function __construct(array $entries) {
		parent::__construct(0);
		foreach ($entries as $entry) {
			$this->addToGroup($entry->getStatus(), $entry);
		}
		$this->finalize();
	}
}

class LengthDistribution extends Distribution {
	protected function sortGroups() {
		ksort($this->keys, SORT_NUMERIC);
		ksort($this->groups, SORT_NUMERIC);
		ksort($this->entries, SORT_NUMERIC);
	}

	public function __construct(array $entries) {
		parent::__construct(0);
		if (count($entries) == 0) {
			return;
		}
		$type = reset($entries)->getType();
		switch ($type) {
			case AMModel::TYPE_ANIME:
				$thresholds = [1, 6, 13, 26, 52, 100];
				break;
			case AMModel::TYPE_MANGA:
				$thresholds = [1, 10, 25, 50, 100, 200];
				break;
		}

		$thresholds = array_reverse($thresholds);
		$thresholds []= 0;

		foreach ($entries as $entry) {
			switch ($type) {
				case AMModel::TYPE_ANIME:
					$length = $entry->getAMEntry()->getEpisodeCount();
					break;
				case AMModel::TYPE_MANGA:
					$length = $entry->getAMEntry()->getChapterCount();
					break;
				default:
					continue;
			}
			$group = '?';
			if ($length > 0) {
				foreach ($thresholds as $i => $threshold) {
					//var_dump($length . ' vs ' . $threshold);
					if ($length > $threshold) {
						if ($i  == 0) {
							$group = strval($threshold + 1) . '+';
						} else {
							$a = $thresholds[$i - 1];
							$b = $threshold + 1;
							if ($a == $b or $threshold == 0) {
								$group = strval($a);
							} else {
								$group = strval($b) . '-' . strval($a);
							}
						}
						break;
					}
				}
			}
			//echo '; group: ' . $group . '<br>';

			$this->addToGroup($group, $entry);
		}
		$this->finalize();
	}
}

class CreatorDistribution extends Distribution {
	protected function sortEntries() {
		foreach ($this->entries as $group => $entries) {
			uasort($entries, UserListSorters::getByScore());
		}
	}

	public function __construct(array $entries) {
		parent::__construct(0);
		if (count($entries) == 0) {
			return;
		}
		$type = reset($entries)->getType();
		foreach ($entries as $entry) {
			switch ($type) {
				case AMModel::TYPE_ANIME:
					$creators = $entry->getAMEntry()->getProducers();
					break;
				case AMModel::TYPE_ANIME:
					$creators = $entry->getAMEntry()->getAuthors();
					break;
			}
			foreach ($creators as $creator) {
				$this->addToGroup($creator, $entry);
			}
		}
		$this->finalize();
	}
}

class GenreDistribution extends Distribution {
	protected function sortEntries() {
		foreach ($this->entries as $group => $entries) {
			uasort($entries, UserListSorters::getByScore());
		}
	}

	public function __construct(array $entries) {
		parent::__construct(0);
		foreach ($entries as $entry) {
			$genres = $entry->getAMEntry()->getGenres();
			foreach ($genres as $genre) {
				$this->addToGroup($genre, $entry);
			}
		}
		$this->finalize();
	}
}

class YearDistribution extends Distribution {
	public static function getYear($entry) {
		$yearA = intval(substr($entry->getAMEntry()->getAiredFrom(), 0, 4));
		$yearB = intval(substr($entry->getAMEntry()->getAiredTo(), 0, 4));
		if (!$yearA and !$yearB) {
			return null;
		} elseif (!$yearA) {
			$year = $yearB;
		} elseif (!$yearB) {
			$year = $yearA;
		} else {
			//$year = ($yearA + $yearB) >> 1;
			$year = $yearA;
		}
		return $year;
	}

	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function __construct(array $entries) {
		parent::__construct(null);
		foreach ($entries as $entry) {
			$year = self::getYear($entry);
			$this->addToGroup($year, $entry);
		}
		$this->finalize();
	}
}

class DecadeDistribution extends Distribution {
	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function __construct(array $entries) {
		parent::__construct(null);
		foreach ($entries as $entry) {
			$year = YearDistribution::getYear($entry);
			$decade = floor($year / 10) * 10;
			$this->addToGroup($decade, $entry);
		}
		$this->finalize();
	}
}
