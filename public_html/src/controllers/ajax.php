<?php
require_once 'src/controllers/abstract.php';
require_once 'src/models/user/listservice.php';

class AjaxController extends AbstractController {
	const REASON_SCORE = 'score';
	const REASON_SCORE_TIME = 'score-time';
	const REASON_YEAR = 'year';
	const REASON_DECADE = 'decade';
	const REASON_CREATOR = 'creator';
	const REASON_GENRE = 'genre';
	const REASON_DAILY_ACTIVITY = 'daily-activity';
	const REASON_MONTHLY_ACTIVITY = 'monthly-activity';
	const REASON_UNKNOWN = 'unknown';

	public static function getReasons() {
		return [
			self::REASON_SCORE,
			self::REASON_SCORE_TIME,
			self::REASON_YEAR,
			self::REASON_DECADE,
			self::REASON_CREATOR,
			self::REASON_GENRE,
			self::REASON_DAILY_ACTIVITY,
			self::REASON_MONTHLY_ACTIVITY,
			self::REASON_UNKNOWN
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
		$this->view->userName = $userName;

		//load anime-manga switch
		$am = $this->inputHelper->get('am');
		if ($am != AMModel::TYPE_MANGA) {
			$am = AMModel::TYPE_ANIME;
		}
		$this->view->am = $am;

		$modelUsers = new UserModel();
		try {
			$userEntry = $modelUsers->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);
		} catch (InvalidEntryException $e) {
			throw new Exception('Wrong user');
		} catch (DownloadException $e) {
			throw new Exception('Network down');
		}
		$this->view->user = $userEntry;

		if ($userEntry->getUserData()->isBlocked()) {
			throw new Exception('User is blocked');
		}

		$reason = ChibiRegistry::getHelper('input')->getStringSafe('reason');
		if (!in_array($reason, self::getReasons())) {
			$reason = self::REASON_UNKNOWN;
		}
		$this->view->reason = $reason;
	}




	public function ajaxAction() {
		$filter = null;
		$list = $this->view->user->getList($this->view->am);

		switch ($this->view->reason) {
			case self::REASON_SCORE:
				$score = $this->inputHelper->getInt('score');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getScore($score);
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->score = $score;
				$this->view->entries = $list->getEntries($filter);
				break;
			case self::REASON_SCORE_TIME:
				$score = $this->inputHelper->getInt('score');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getScore($score);
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->score = $score;
				$this->view->entries = $list->getEntries($filter);
				break;
			case self::REASON_YEAR:
				$year = $this->inputHelper->getInt('year');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = function(UserListEntry $entry) use ($year) {
					return UserListService::getAiredYear($entry) == $year;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->year = $year;
				$this->view->entries = $list->getEntries($filter);
				$this->view->meanScore = UserListService::getMeanScore($this->view->entries);
				break;
			case self::REASON_DECADE:
				$decade = $this->inputHelper->getInt('decade');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = function(UserListEntry $entry) use ($decade) {
					return UserListService::getAiredDecade($entry) == $decade;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->decade = $decade;
				$this->view->entries = $list->getEntries($filter);
				$this->view->meanScore = UserListService::getMeanScore($this->view->entries);
				break;
			case self::REASON_CREATOR:
				$creator = $this->inputHelper->getInt('creator');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getCreator($creator);
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->entries = $list->getEntries($filter);
				if (!empty($this->view->entries)) {
					foreach (reset($this->view->entries)->getAMEntry()->getCreators() as $creatorEntry) {
						if ($creatorEntry->getID() == $creator) {
							$this->view->creator = $creatorEntry;
						}
					}
				}
				$this->view->meanScore = UserListService::getMeanScore($this->view->entries);
				break;
			case self::REASON_GENRE:
				$genre = $this->inputHelper->getInt('genre');
				$filter1 = UserListFilters::getNonPlanned();
				$filter2 = UserListFilters::getGenre($genre);
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->genre = $genre;
				$this->view->entries = $list->getEntries($filter);
				if (!empty($this->view->entries)) {
					foreach (reset($this->view->entries)->getAMEntry()->getGenres() as $genreEntry) {
						if ($genreEntry->getID() == $genre) {
							$this->view->genre = $genreEntry;
						}
					}
				}
				$this->view->meanScore = UserListService::getMeanScore($this->view->entries);
				break;
			case self::REASON_DAILY_ACTIVITY:
				$daysAgo = $this->inputHelper->getInt('days-ago');
				$this->view->daysAgo = $daysAgo;
				$this->view->entries = $this->view->user->getHistory($this->view->am)->getEntriesByDaysAgo($daysAgo);
				break;
			case self::REASON_MONTHLY_ACTIVITY:
				$monthPeriod = $this->inputHelper->getStringSafe('month');
				$filter1 = UserListFilters::getCompleted();
				$filter2 = function(UserListEntry $e) use ($monthPeriod) {
					return UserListService::getMonthPeriod($e) == $monthPeriod;
				};
				$filter = UserListFilters::combine($filter1, $filter2);
				$this->view->monthPeriod = $monthPeriod;
				$this->view->entries = $list->getEntries($filter);
				break;
		}
	}
}
