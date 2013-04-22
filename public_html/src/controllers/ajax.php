<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user/listservice.php';

class AjaxController extends ChibiController {
	const SENDER_SCORE = 'score';
	const SENDER_SCORE_TIME = 'score-time';
	const SENDER_SCORE_LENGTH = 'score-length';
	const SENDER_LENGTH = 'length';
	const SENDER_YEAR = 'year';
	const SENDER_DECADE = 'decade';
	const SENDER_CREATOR = 'creator';
	const SENDER_GENRE = 'genre';
	const SENDER_DAILY_ACTIVITY = 'daily-activity';
	const SENDER_MONTHLY_ACTIVITY = 'monthly-activity';
	const SENDER_FRANCHISES = 'franchises';
	const SENDER_SUB_TYPE = 'sub-type';
	const SENDER_MISMATCHED_EPS = 'mismatched-eps';
	const SENDER_UNKNOWN = 'unknown';

	public static function getSenders() {
		return [
			self::SENDER_SCORE,
			self::SENDER_SCORE_TIME,
			self::SENDER_SCORE_LENGTH,
			self::SENDER_LENGTH,
			self::SENDER_YEAR,
			self::SENDER_DECADE,
			self::SENDER_CREATOR,
			self::SENDER_GENRE,
			self::SENDER_DAILY_ACTIVITY,
			self::SENDER_MONTHLY_ACTIVITY,
			self::SENDER_SUB_TYPE,
			self::SENDER_FRANCHISES,
			self::SENDER_MISMATCHED_EPS,
			self::SENDER_UNKNOWN
		];
	}

	public function init() {
		parent::init();
		ChibiConfig::getInstance()->chibi->runtime->layoutName = 'ajax';
		$this->sessionHelper->close();

		//no user specified
		$userName = $this->inputHelper->getStringSafe('u');
		if (empty($userName)) {
			throw new Exception('User name not specified.');
		}
		ChibiRegistry::getView()->userName = $userName;

		//load anime-manga switch
		$am = $this->inputHelper->get('am');
		if ($am != AMModel::TYPE_MANGA) {
			$am = AMModel::TYPE_ANIME;
		}
		ChibiRegistry::getView()->am = $am;

		$modelUsers = new UserModel();
		try {
			$userEntry = $modelUsers->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);
		} catch (InvalidEntryException $e) {
			throw new Exception('Wrong user');
		} catch (DownloadException $e) {
			throw new Exception('Network down');
		}
		ChibiRegistry::getView()->user = $userEntry;

		if ($userEntry->getUserData()->isBlocked()) {
			throw new Exception('User is blocked');
		}

		$sender = ChibiRegistry::getHelper('input')->getStringSafe('sender');
		if (!in_array($sender, self::getSenders())) {
			$sender = self::SENDER_UNKNOWN;
		}
		ChibiRegistry::getView()->sender = $sender;
	}




	public function ajaxAction() {
		$filter = null;
		$list = ChibiRegistry::getView()->user->getList(ChibiRegistry::getView()->am);
		$sort = true;

		switch (ChibiRegistry::getView()->sender) {
			case self::SENDER_SCORE:
				$score = $this->inputHelper->getInt('score');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getScore($score);
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->score = $score;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				break;

			case self::SENDER_SCORE_TIME:
				$score = $this->inputHelper->getInt('score');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getScore($score);
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->score = $score;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				break;

			case self::SENDER_SCORE_LENGTH:
			case self::SENDER_LENGTH:
				$length = $this->inputHelper->getStringSafe('length');
				$filter1 = UserListFilters::getNonPlanned();
				if (ChibiRegistry::getView()->sender == self::SENDER_LENGTH) {
					$filter2 = function($entry) { return $entry->getAMEntry()->getSubType() != AnimeEntry::SUBTYPE_MOVIE; };
					$filter = UserListFilters::combine($filter1, $filter2);
				} else {
					$filter = $filter1;
				}
				$entries = $list->getEntries($filter);
				$lengthDistribution = new LengthDistribution($entries);
				$entries = $lengthDistribution->getGroupEntries($length);
				ChibiRegistry::getView()->length = $length;
				ChibiRegistry::getView()->entries = $entries;
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_YEAR:
				$year = $this->inputHelper->getInt('year');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = function(UserListEntry $entry) use ($year) {
					return AMService::getAiredYear($entry->getAMEntry()) == $year;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->year = $year;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_DECADE:
				$decade = $this->inputHelper->getInt('decade');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = function(UserListEntry $entry) use ($decade) {
					return AMService::getAiredDecade($entry->getAMEntry()) == $decade;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->decade = $decade;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_SUB_TYPE:
				$subType = $this->inputHelper->getStringSafe('sub-type');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getSubType($subType);
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->subType = $subType;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_CREATOR:
				$creator = $this->inputHelper->getInt('creator');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getCreator($creator);
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				if (!empty(ChibiRegistry::getView()->entries)) {
					foreach (reset(ChibiRegistry::getView()->entries)->getAMEntry()->getCreators() as $creatorEntry) {
						if ($creatorEntry->getID() == $creator) {
							ChibiRegistry::getView()->creator = $creatorEntry;
						}
					}
				} else {
					foreach ($list->getAMModel()->getKeys() as $key) {
						foreach ($list->getAMModel()->get($key, AbstractModel::CACHE_POLICY_FORCE_CACHE)->getCreators() as $creatorEntry) {
							if ($creatorEntry->getID() == $creator) {
								ChibiRegistry::getView()->creator = $creatorEntry;
							}
						}
					}
				}
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_GENRE:
				$genre = $this->inputHelper->getInt('genre');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getGenre($genre);
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				if (!empty(ChibiRegistry::getView()->entries)) {
					foreach (reset(ChibiRegistry::getView()->entries)->getAMEntry()->getGenres() as $genreEntry) {
						if ($genreEntry->getID() == $genre) {
							ChibiRegistry::getView()->genre = $genreEntry;
						}
					}
				} else {
					foreach ($list->getAMModel()->getKeys() as $key) {
						foreach ($list->getAMModel()->get($key, AbstractModel::CACHE_POLICY_FORCE_CACHE)->getGenres() as $genreEntry) {
							if ($genreEntry->getID() == $genre) {
								ChibiRegistry::getView()->genre = $genreEntry;
							}
						}
					}
				}
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_DAILY_ACTIVITY:
				$daysAgo = $this->inputHelper->getInt('days-ago');
				ChibiRegistry::getView()->daysAgo = $daysAgo;
				ChibiRegistry::getView()->entries = ChibiRegistry::getView()->user->getHistory(ChibiRegistry::getView()->am)->getEntriesByDaysAgo($daysAgo);
				$sort = false;
				break;

			case self::SENDER_MONTHLY_ACTIVITY:
				$monthPeriod = $this->inputHelper->getStringSafe('month');
				$filter1 = UserListFilters::getCompleted();
				$filter2 = function(UserListEntry $e) use ($monthPeriod) {
					return UserListService::getMonthPeriod($e) == $monthPeriod;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				ChibiRegistry::getView()->monthPeriod = $monthPeriod;
				ChibiRegistry::getView()->entries = $list->getEntries($filter);
				ChibiRegistry::getView()->meanScore = UserListService::getMeanScore(ChibiRegistry::getView()->entries);
				break;

			case self::SENDER_FRANCHISES:
				$filter = UserListFilters::getNonPlanned();
				$entries = $list->getEntries($filter);
				ChibiRegistry::getView()->entries = UserListService::getFranchises($entries);
				$sort = false;
				break;

			case self::SENDER_MISMATCHED_EPS:
				$filter = UserListFilters::getNonPlanned();
				$entries = $list->getEntries($filter);
				ChibiRegistry::getView()->entries = UserListService::getMismatchedEntries($entries);
				break;
		}

		if ($sort) {
			uasort(ChibiRegistry::getView()->entries, UserListSorters::getByTitle());
		}
	}
}
