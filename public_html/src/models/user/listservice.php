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

	public static function getSubType($subType) {
		return function (UserListEntry $entry) use ($subType) { return $entry->getAMEntry()->getSubType() == $subType; };
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

	public static function evaluateDistribution(Distribution $dist) {
		$values = [];
		$allEntries = $dist->getAllEntries();
		$meanScore = self::getMeanScore($allEntries);
		foreach ($dist->getGroupsKeys() as $key) {
			$entry = [];
			$scoreDist = new ScoreDistribution($dist->getGroupEntries($key));
			$localMeanScore = $scoreDist->getRatedCount() * $scoreDist->getMeanScore() + $scoreDist->getUnratedCount() * $meanScore;
			$localMeanScore /= (float)max(1, $dist->getGroupSize($key));
			$weight = $dist->getGroupSize($key) / max(1, $dist->getLargestGroupSize());
			$weight = 1 - pow(1 - pow($weight, 8. / 9.), 2);
			$value = $meanScore + ($localMeanScore - $meanScore) * $weight;
			$values[(string) $key] = $value;
		}
		return $values;
	}

	public static function getMonthPeriod(UserListEntry $entry) {
		$finishedA = explode('-', $entry->getStartDate());
		$finishedB = explode('-', $entry->getFinishDate());
		$yearA = intval($finishedA[0]);
		$yearB = intval($finishedB[0]);
		$monthA = isset($finishedA[1]) ? intval($finishedA[1]) : false;
		$monthB = isset($finishedB[1]) ? intval($finishedB[1]) : false;
		if ($yearB > 1900 and $monthB) {
			$monthPeriod = sprintf('%04d-%02d', $yearB, $monthB);
		} elseif ($yearA > 1900 and $monthA) {
			$monthPeriod = sprintf('%04d-%02d', $yearA, $monthA);
		} else {
			$monthPeriod = '?';
		}
		return $monthPeriod;
	}

	public static function getFranchises(array $entries, $filter = 'default') {
		$all = [];

		$franchises = [];
		$checked = [];
		foreach ($entries as $entry) {
			if (isset($checked[$entry->getID()])) {
				continue;
			}
			$actualFranchise = $entry->getAMEntry()->getFranchise();
			$franchise = null;
			$add  = false;
			//check if any id was set anywhere. sadly, anime relations on mal can be one-way.
			foreach ($actualFranchise->entries as $franchiseEntry) {
				$id = $franchiseEntry->getID();
				if (isset($checked[$id])) {
					$franchise = $checked[$id];
				}
			}
			if ($franchise === null) {
				$franchise = $actualFranchise;
				$franchise->ownEntries = [];
				$add = true;
			}
			foreach ($actualFranchise->entries as $franchiseEntry) {
				$id = $franchiseEntry->getID();
				if (isset($entries[$id])) {
					$franchise->ownEntries[$id] = $entries[$id];
					$checked[$id] = $franchise;
				}
			}
			$franchise->meanScore = UserListService::getMeanScore($franchise->ownEntries);
			if ($add) {
				$franchises []= $franchise;
			}
		}

		//remove groups with less than 2 titles
		if ($filter == 'default') {
			$filter = function($f) { return count($f->ownEntries) > 1; };
		} elseif ($filter === null) {
			$filter = function($f) { return count($f->ownEntries) > 0; };
		}
		if (!empty($filter)) {
			$franchises = array_filter($franchises, $filter);
		}

		uasort($franchises, function($a, $b) { return $b->meanScore > $a->meanScore ? 1 : -1; });
		return $franchises;
	}

	public static function getMismatchedEntries(array $entries) {
		$entriesMismatched = [];
		foreach ($entries as $entry) {
			if ($entry->getType() == AMModel::TYPE_ANIME) {
				$a = $entry->getCompletedEpisodes();
				$b = $entry->getAMEntry()->getEpisodeCount();
			} else {
				$a = $entry->getCompletedChapters();
				$b = $entry->getAMEntry()->getChapterCount();
			}
			if ($a != $b and ($b > 0 or $entry->getAMEntry()->getStatus() == AMEntry::STATUS_PUBLISHING) and $entry->getStatus() == UserListEntry::STATUS_COMPLETED) {
				$entriesMismatched []= $entry;
			}
		}
		return $entriesMismatched;
	}
}



abstract class Distribution {
	protected $groups = [];
	protected $entries = [];
	protected $keys = [];

	public function disableEntries() {
		$this->entries = null;
	}

	public function entriesEnabled() {
		return $this->entries !== null;
	}

	const IGNORE_NULL_KEY = 1;
	const IGNORE_EMPTY_GROUPS = 2;

	public function __construct(array $entries = []) {
		if (!empty($entries)) {
			foreach ($entries as $entry) {
				$this->addEntry($entry);
			}
			$this->finalize();
		}
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
		if (!isset($this->keys[(string)$key])) {
			$this->keys[(string)$key] = $key;
			$this->groups[(string)$key] = 0;
			if ($this->entries !== null) {
				$this->entries[(string)$key] = [];
			}
		}
	}

	public function addToGroup($key, $entry, $weight = 1) {
		if (!isset($this->groups[(string)$key])) {
			$this->keys[(string)$key] = $key;
			$this->groups[(string)$key] = 0;
			if ($this->entriesEnabled()) {
				$this->entries[(string)$key] = [];
			}
		}
		$this->groups[(string)$key] += $weight;
		if ($this->entriesEnabled()) {
			$this->entries[(string)$key] []= $entry;
		}
	}

	public function getGroupEntries($key) {
		if (!$this->entriesEnabled()) {
			return null;
		}
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
		if ($flags & self::IGNORE_EMPTY_GROUPS) {
			$x = array_filter($x, function($key) { return $this->getGroupSize($key) > 0; });
		}
		$x = array_values($x);
		return $x;
	}

	public function getGroupsEntries($flags = 0) {
		if (!$this->entriesEnabled()) {
			return null;
		}
		$keys = $this->getGroupsKeys($flags);
		$x = [];
		foreach ($keys as $key) {
			$x[(string) $key] = $this->getGroupEntries($key);
		}
		return $x;
	}

	public function getAllEntries($flags = 0) {
		$groups = self::getGroupsEntries($flags);
		if ($groups === null) {
			return null;
		}
		$x = [];
		foreach ($groups as $key => $entries) {
			foreach ($entries as $entry) {
				$x[$entry->getID()] = $entry;
			}
		}
		return $x;
	}

	public function getGroupsSizes($flags = 0) {
		$keys = $this->getGroupsKeys($flags);
		$x = [];
		foreach ($keys as $key) {
			$x[(string) $key] = $this->getGroupSize($key);
		}
		$x = array_values($x);
		return $x;
	}



	public function getLargestGroupSize($flags = 0) {
		$x = $this->getGroupsSizes($flags);
		if (empty($x)) {
			return 0;
		}
		return max($x);
	}

	public function getLargestGroupKey($flags = 0) {
		return array_search($this->getLargestGroupSize($flags), $this->groups);
	}

	public function getSmallestGroupSize($flags = 0) {
		$x = $this->getGroupsSizes($flags);
		return min($x);
	}

	public function getSmallestGroupKey($flags = 0) {
		return array_search($this->getSmallestGroupSize($flags), $this->groups);
	}

	public function getTotalSize($flags = 0) {
		return array_sum($this->getGroupsSizes($flags));
	}
}


class ScoreDistribution extends Distribution {
	public function __construct(array $entries = []) {
		foreach (range(10, 0) as $x) {
			$this->addGroup($x);
		}
		parent::__construct($entries);
	}

	public function getNullGroupKey() {
		return 0;
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
		$f = function($a, $b) {
			if ($b == '?') {
				return -1;
			} elseif ($a == '?') {
				return 1;
			} else {
				return intval($a) - intval($b);
			}
		};
		uksort($this->keys, $f);
		uksort($this->groups, $f);
		uksort($this->entries, $f);
	}

	public function getNullGroupKey() {
		return 0;
	}

	public static function getThresholds($type) {
		switch ($type) {
			case AMModel::TYPE_ANIME: return [1, 6, 13, 26, 52, 100];
			case AMModel::TYPE_MANGA: return [1, 10, 25, 50, 100, 200];
		}
		 throw new Exception('Invalid type');
	}


	public function addEntry(UserListEntry $entry) {
		$type = $entry->getType();
		$thresholds = self::getThresholds($type);
		$thresholds = array_reverse($thresholds);
		$thresholds []= 0;

		switch ($type) {
			case AMModel::TYPE_ANIME: $length = $entry->getAMEntry()->getEpisodeCount(); break;
			case AMModel::TYPE_MANGA: $length = $entry->getAMEntry()->getChapterCount(); break;
			default: return;
		}
		$group = '?';
		if ($length > 0) {
			foreach ($thresholds as $i => $threshold) {
				if ($length > $threshold) {
					if ($i == 0) {
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

		$this->addToGroup((string)$group, $entry);
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
		$creators = $entry->getAMEntry()->getCreators();
		foreach ($creators as $creator) {
			if (!AMService::creatorForbidden($creator)) {
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
		$genres = $entry->getAMEntry()->getGenres();
		foreach ($genres as $genre) {
			if (!AMService::genreForbidden($genre)) {
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

	public function addEmptyYears() {
		if (!empty($this->keys)) {
			$min = $max = reset($this->keys);
			while (list($i,) = each($this->keys)) {
				if ($min > $i) {
					$min = $i;
				} elseif ($max < $i) {
					$max = $i;
				}
			}
			for ($i = $min + 1; $i < $max; $i ++) {
				$this->addGroup($i);
			}
		}
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup(AMService::getAiredYear($entry->getAMEntry()), $entry);
	}
}

class DecadeDistribution extends Distribution {
	protected function sortGroups() {
		krsort($this->keys, SORT_NUMERIC);
		krsort($this->groups, SORT_NUMERIC);
		krsort($this->entries, SORT_NUMERIC);
	}

	public function addEmptyDecades() {
		if (!empty($this->keys)) {
			$min = $max = reset($this->keys);
			while (list($i,) = each($this->keys)) {
				if ($min > $i) {
					$min = $i;
				} elseif ($max < $i) {
					$max = $i;
				}
			}
			for ($i = $min + 10; $i < $max; $i += 10) {
				$this->addGroup($i);
			}
		}
	}

	public function getNullGroupKey() {
		return 0;
	}

	public function addEntry(UserListEntry $entry) {
		$this->addToGroup(AMService::getAiredDecade($entry->getAMEntry()), $entry);
	}
}
