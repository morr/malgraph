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
	public static function combine() {
		$filters = func_get_args();
		return function (UserListEntry $entry) use ($filters) {
			foreach ($filters as $f) {
				if (!$f($entry)) {
					return false;
				}
			}
			return true;
		};
	}
	public static function getScore($score) {
		return function (UserListEntry $entry) use ($score) { return $entry->getScore() == $score; };
	}

	public static function getGenre($genreId) {
		return function (UserListEntry $entry) use ($genreId) {
			foreach ($entry->getAMEntry()->getGenres() as $genre) {
				if ($genreId == $genre->getID()) {
					return true;
				}
			}
			return false;
		};
	}

	public static function getCreator($creatorId) {
		return function (UserListEntry $entry) use ($creatorId) {
			foreach ($entry->getAMEntry()->getCreators() as $creator) {
				if ($creatorId == $creator->getID()) {
					return true;
				}
			}
			return false;
		};
	}

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
		$count = 0;
		foreach ($entries as $entry) {
			if ($entry->getScore()) {
				$sum += $entry->getScore();
				$count ++;
			}
		}
		return $sum / max(1, $count);
	}

	public static function getTimeSpent(array $entries) {
		$sum = 0;
		foreach ($entries as $entry) {
			$sum += $entry->getCompletedDuration();
		}
		return $sum;
	}

	public static function getMonthPeriod(UserListEntry $entry) {
		$finishedB = explode('-', $entry->getStartDate());
		$finishedA = explode('-', $entry->getFinishDate());
		$yearA = intval($finishedA[0]);
		$yearB = intval($finishedB[0]);
		$monthA = isset($finishedA[1]) ? intval($finishedA[1]) : false;
		$monthB = isset($finishedB[1]) ? intval($finishedB[1]) : false;
		if ($yearB and $monthB) {
			$monthPeriod = sprintf('%04d-%02d', $yearB, $monthB);
		} elseif ($yearA and $monthA) {
			$monthPeriod = sprintf('%04d-%02d', $yearA, $monthA);
		} else {
			$monthPeriod = '?';
		}
		return $monthPeriod;
	}

	public static function getAiredYear(UserListEntry $entry) {
		$yearA = intval(substr($entry->getAMEntry()->getAiredFrom(), 0, 4));
		$yearB = intval(substr($entry->getAMEntry()->getAiredTo(), 0, 4));
		if (!$yearA and !$yearB) {
			return 0;
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

	public static function getAiredDecade(UserListEntry $entry) {
		$year = self::getAiredYear($entry);
		$decade = floor($year / 10) * 10;
		return $decade;
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



abstract class Distribution {
	protected $groups = [];
	protected $entries = [];
	protected $keys = [];

	const IGNORE_NULL_KEY = 1;

	public function __construct(array $entries = []) {
		foreach ($entries as $entry) {
			$this->addEntry($entry);
		}
		$this->finalize();
	}

	public abstract function addEntry(UserListEntry $entry);

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
		return null;
	}

	protected function addGroup($key) {
		$this->keys[(string)$key] = $key;
		$this->groups[(string)$key] = 0;
		$this->entries[(string)$key] = [];
	}

	public function addToGroup($key, $entry, $weight = 1) {
		if (!isset($this->groups[(string)$key])) {
			$this->keys[(string)$key] = $key;
			$this->groups[(string)$key] = 0;
			$this->entries[(string)$key] = [];
		}
		$this->groups[(string)$key] += $weight;
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
	public function __construct(array $entries = []) {
		foreach (range(10, 0) as $x) {
			$this->addGroup($x);
		}
		parent::__construct($entries);
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup($entry->getScore(), $entry);
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
	public function addEntry(UserListEntry $entry) {
		$this->addToGroup($entry->getScore(), $entry, $entry->getCompletedDuration());
	}

	public function getTotalTime() {
		return $this->getTotalSize();
	}
}

class SubTypeDistribution extends Distribution {
	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup($entry->getAMEntry()->getSubType(), $entry);
	}
}

class StatusDistribution extends Distribution {
	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup($entry->getStatus(), $entry);
	}
}

class LengthDistribution extends Distribution {
	protected function sortGroups() {
		ksort($this->keys, SORT_NUMERIC);
		ksort($this->groups, SORT_NUMERIC);
		ksort($this->entries, SORT_NUMERIC);
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$type = $entry->getType();
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

		switch ($type) {
			case AMModel::TYPE_ANIME:
				$length = $entry->getAMEntry()->getEpisodeCount();
				break;
			case AMModel::TYPE_MANGA:
				$length = $entry->getAMEntry()->getChapterCount();
				break;
			default:
				return;
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

		$this->addToGroup($group, $entry);
	}
}

class CreatorDistribution extends Distribution {
	protected function sortEntries() {
		foreach ($this->entries as $group => $entries) {
			uasort($entries, UserListSorters::getByScore());
		}
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$type = $entry->getType();
		$excluded = json_decode(file_get_contents(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->excludedCreatorsDefFile), true);
		$excludedIDs = array_map(function($e) { return $e['id']; }, $excluded[$type]);
		$creators = $entry->getAMEntry()->getCreators();
		foreach ($creators as $creator) {
			if (!in_array($creator->getID(), $excludedIDs)) {
				$this->addToGroup($creator, $entry);
			}
		}
	}
}

class GenreDistribution extends Distribution {
	protected function sortEntries() {
		foreach ($this->entries as $group => $entries) {
			uasort($entries, UserListSorters::getByScore());
		}
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$type = $entry->getType();
		$excluded = json_decode(file_get_contents(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->excludedGenresDefFile), true);
		$excludedIDs = array_map(function($e) { return $e['id']; }, $excluded[$type]);
		$genres = $entry->getAMEntry()->getGenres();
		foreach ($genres as $genre) {
			if (!in_array($genre->getID(), $excludedIDs)) {
				$this->addToGroup($genre, $entry);
			}
		}
	}
}

class YearDistribution extends Distribution {
	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup(UserListService::getAiredYear($entry), $entry);
	}
}

class DecadeDistribution extends Distribution {
	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup(UserListService::getAiredDecade($entry), $entry);
	}
}
