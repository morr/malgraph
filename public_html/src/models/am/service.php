<?php
class AMService {
	private static $excludedCreators = null;
	private static $excludedGenres = null;

	public static function init() {
		self::$excludedCreators = ChibiRegistry::getHelper('mg')->loadJSON(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->excludedCreatorsDefFile);
		self::$excludedGenres = ChibiRegistry::getHelper('mg')->loadJSON(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->excludedGenresDefFile);
	}

	public static function getAiredSeason(AMEntry $entry) {
		list(, $monthA) = explode('-', $entry->getAiredFrom() . '-');
		list(, $monthB) = explode('-', $entry->getAiredTo() . '-');
		$seasons = [
			'Spring' => [3, 4, 5],
			'Summer' => [6, 7, 8],
			'Fall' => [9, 10, 11],
			'Winter' => [12, 1, 2],
		];
		$month = null;
		if ($monthA or $monthB) {
			if (!$monthA) {
				$month = $monthB;
			} else {
				$month = $monthA;
			}
		}
		$season = null;
		foreach ($seasons as $s => $months) {
			if (in_array($month, $months)) {
				$season = $s;
				break;
			}
		}
		if (!$season) {
			return self::getAiredYear($entry);
		}
		return $season . ' ' . self::getAiredYear($entry);
	}

	public static function getAiredYear(AMEntry $entry) {
		$yearA = intval(substr($entry->getAiredFrom(), 0, 4));
		$yearB = intval(substr($entry->getAiredTo(), 0, 4));
		if (!$yearA and !$yearB) {
			return 0;
		} elseif (!$yearA) {
			return $yearB;
		}
		return $yearA;
	}

	public static function getAiredDecade(AMEntry $entry) {
		$year = self::getAiredYear($entry);
		$decade = floor($year / 10) * 10;
		return $decade;
	}

	public static function creatorForbidden($creator) {
		return in_array($creator->getID(), self::$excludedCreators[$creator->getType()]);
	}

	public static function genreForbidden($genre) {
		return in_array($genre->getID(), self::$excludedGenres[$genre->getType()]);
	}
}

AMService::init();
