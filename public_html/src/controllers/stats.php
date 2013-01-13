<?php
require_once 'src/controllers/abstract.php';
require_once 'src/models/user/listservice.php';

class StatsController extends AbstractController {
	public function init() {
		parent::init();
		$this->sessionHelper->close();

		//no user specified
		$userNames = $this->inputHelper->getStringSafe('u');
		if (empty($userNames)) {
			throw new Exception('User name not specified.');
		}
		//user is not an array
		$userNames = explode(',', $userNames);
		foreach ($userNames as $userName) {
			if (empty($userName)) {
				throw new Exception('User name cannot be empty.');
			}
		}
		//make users unique
		$userNames = array_unique($userNames);
		$this->view->userNames = $userNames;

		//load anime-manga switch
		$am = $this->inputHelper->get('am');
		if ($am != AMModel::TYPE_MANGA) {
			$am = AMModel::TYPE_ANIME;
		}
		$this->view->am = $am;

		if (count($this->view->userNames) > 2) {
			throw new Exception('Sorry. We haven\'t implemented this.');
		}

		//try to load requested users
		$anons = [];
		$modelUsers = new UserModel(true);
		$this->view->users = [];
		foreach ($this->view->userNames as $userName) {
			try {
				$userEntry = $modelUsers->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);
			} catch (InvalidEntryException $e) {
				$this->sessionHelper->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward($this->mgHelper->constructUrl('index', 'wrong-user'));
				return;
			} catch (DownloadException $e) {
				$this->forward($this->mgHelper->constructUrl('index', 'net-down'));
				return;
			}
			if ($userEntry->getUserData()->isBlocked()) {
				$this->sessionHelper->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward($this->mgHelper->constructUrl('index', 'blocked-user'));
			}
			$this->view->users []= $userEntry;
		}

		//set meta
		$titles = [
			'profile' => ['{nick}&rsquo;s profile', '{nicks_amp}'],
			'list' => ['{nick} - list ({am})', '{nicks_amp} - lists ({am})'],
			'rati' => ['{nick} - rating stats ({am})', '{nicks_amp} - rating stats ({am})'],
			'acti' => ['{nick} - activity ({am})', '{nicks_amp} - activity ({am})'],
			'achi' => ['{nick} - achievements ({am})', '{nicks_amp} - achievements ({am})'],
			'sug' => ['{nick} - suggestions ({am})', '{nicks_amp} - suggestions ({am})'],
			'favs' => ['{nick} - favorites ({am})', '{nicks_amp} - favorites ({am})'],
			'misc' => ['{nick} - misc stats ({am})', '{nicks_amp} - misc stats ({am})'],
		];
		$descriptions = [
			'profile' => ['{nick}&rsquo;s profile', 'Comparison of {nicks_and}&rsquo;s profiles'],
			'list' => ['{nick}&rsquo;s {am} list', 'Comparison of {nicks_and}&rsquo;s {am} lists'],
			'rati' => ['{nick}&rsquo;s {am} rating statistics', 'Comparison of {nicks_and}&rsquo;s {am} rating statistics'],
			'acti' => ['{nick}&rsquo;s {am} activity', 'Comparison of {nicks_and}&rsquo;s {am} activity'],
			'achi' => ['{nick}&rsquo;s {am} achievements', 'Comparison of {nicks_and}&rsquo;s {am} achievements'],
			'sug' => ['{nick}&rsquo;s {am} suggestions', 'Comparison of {nicks_and}&rsquo;s {am} suggestions'],
			'favs' => ['{nick}&rsquo;s {am} favorites', 'Comparison of {nicks_and}&rsquo;s {am} favorites'],
			'misc' => ['{nick}&rsquo;s {am} misc stats', 'Comparison of {nicks_and}&rsquo;s {am} misc stats'],
		];
		$tokens = [
			'nick' => $this->view->users[0]->getPublicName(),
			'nicks_amp' => implode(' & ', array_map(function($user) { return $user->getPublicName(); }, $this->view->users)),
			'nicks_and' => implode(' and ', array_map(function($user) { return $user->getPublicName(); }, $this->view->users)),
			'am' => $this->mgHelper->amText()
		];

		if (isset($titles[$this->view->actionName])) {
			$title = 'MALgraph - ';
			$description = '';
			if (count($this->view->users) > 1) {
				$title .= $titles[$this->view->actionName][1];
				$description .= $descriptions[$this->view->actionName][1];
			} else {
				$title .= $titles[$this->view->actionName][0];
				$description .= $descriptions[$this->view->actionName][0];
			}
			$title = $this->mgHelper->replaceTokens($title, $tokens);
			$description = $this->mgHelper->replaceTokens($description, $tokens);

			HeadHelper::setTitle($title);
			HeadHelper::setDescription($description);
			HeadHelper::addKeywords(['profile', 'list', 'achievements', 'ratings', 'activity', 'favorites', 'suggestions', 'recommendations']);
			foreach ($this->view->userNames as $userName) {
				if ($userName{0} != '=') {
					HeadHelper::addKeywords([$userName]);
				}
			}
		}
	}


	public function profileAction() {
		foreach ($this->view->users as $u) {
			$filter = null;//UserListFilters::getNonPlanned();
			$entries = $u->getList($this->view->am)->getEntries($filter);
			$this->view->subTypeDistribution[$u->getID()] = UserListService::getSubTypeDistribution($entries);
			$this->view->statusDistribution[$u->getID()] = UserListService::getStatusDistribution($entries);
			$this->view->lengthDistribution[$u->getID()] = UserListService::getLengthDistribution($entries);

			switch ($this->view->am) {
				case AMModel::TYPE_ANIME:
					$this->view->completedEpisodes[$u->getID()] = array_sum(array_map(function($entry) { return $entry->getCompletedEpisodes(); }, $entries));
					break;
				case AMModel::TYPE_MANGA:
					$this->view->completedChapters[$u->getID()] = array_sum(array_map(function($entry) { return $entry->getCompletedChapters(); }, $entries));
					$this->view->completedVolumes[$u->getID()] = array_sum(array_map(function($entry) { return $entry->getCompletedVolumes(); }, $entries));
					break;
			}
		}
	}



	public function listAction() {
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.tablesorter.min.js'));
	}



	public function achiAction() {
		foreach ($this->view->users as $i => $u) {
			$groups = [];
			foreach ($u->getList($this->view->am)->getEntries() as $entry) {
				$groups[$entry->getStatus()] []= $entry;
			}

			$achievements = [];

			$achList = json_decode(file_get_contents(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->achDefFile), true);
			if ($this->view->am == AMModel::TYPE_ANIME) {
				$model = new AnimeModel();
				$AM = 'anime';
			} else {
				$model = new MangaModel();
				$AM = 'manga';
			}

			//achievments from json
			foreach ($achList[$AM] as $group => $groupData) {
				$achIds = array();
				foreach ($groupData['titles'] as $title) {
					$achIds []= $title['id'];
				}
				$entriesOwned = array();
				if (!empty($groups[UserListEntry::STATUS_COMPLETED])) {
					foreach ($groups[UserListEntry::STATUS_COMPLETED] as $e) {
						if (in_array($e->getID(), $achIds)) {
							$entriesOwned []= $e;
						}
					}
				}
				uasort($entriesOwned, UserListSorters::getByTitle());
				//give corresponding achievement (make sure it has correct threshold)
				uasort($groupData['achievements'], function($a, $b) { return $a['threshold'] > $b['threshold'] ? -1 : 1; });
				foreach ($groupData['achievements'] as $ach) {
					if (count($entriesOwned) >= $ach['threshold']) {
						$ach['entries'] = $entriesOwned;
						$achievements []= $ach;
						break;
					}
				}
			}

			//achievement for mean score
			if ($this->view->am == AMModel::TYPE_ANIME) {
				$count = 0;
				$sum = 0;
				$filter = UserListFilters::getNonPlanned();
				foreach ($u->getList($this->view->am)->getEntries($filter) as $e) {
					if ($e->getScore() > 0) {
						$sum += $e->getScore();
						$count ++;
					}
				}
				$scoreMean = $sum / max(1, $count);

				if (($scoreMean < 5) && ($scoreMean > 0)) {
					$achievements []= [
						'id' => 'anime-suffering',
						'title' => 'Watching anime is suffering',
						'desc' => 'Mean score lower than 5. Someone has to counterweigh the fanboys, right?'
					];
				}
				if ($scoreMean >= 8.5) {
					$achievements []= [
						'id' => 'anime-ilovethem',
						'title' => 'I love Chinese cartoons',
						'desc' => 'Mean score higher than 8.5. How about using the whole scale and re-rating stuff? That could make you look less like a fanboy&hellip;'
					];
				}
			}

			$files = scandir(implode(DIRECTORY_SEPARATOR, [ChibiConfig::getInstance()->chibi->runtime->rootFolder, 'media', 'img', 'ach']));

			foreach ($achievements as &$ach) {
				$ach['path'] = null;
				foreach ($files as $f) {
					if (preg_match('/' . $ach['id'] . '[^0-9a-zA-Z_-]/', $f)) {
						$ach['path'] = $f;
					}
				}
			}
			unset($ach);

			//sort by achievement related titles count
			uasort($achievements, function($a, $b) {
				if (empty($a['entries'])) {
					return - 1;
				} elseif (empty($b['entries'])) {
					return 1;
				}
				return count($a['entries']) - count($b['entries']);
			});

			$this->view->achievements[$u->getID()] = $achievements;
		}
	}



	public function ratiAction() {
		HeadHelper::addScript(UrlHelper::url('media/js/popups.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/highcharts.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/themes/mg.js'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/popups.css'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/infobox.css'));
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.farbtastic.js'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/jquery.farbtastic.css'));

		//prepare info for view
		$this->view->scoreDistribution = [];

		foreach ($this->view->users as $userEntry) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $userEntry->getList($this->view->am)->getEntries($filter);

			$this->view->scoreDistribution[$userEntry->getID()] = UserListService::getScoreDistribution($entries);
			$this->view->scoreDurationDistribution[$userEntry->getID()] = UserListService::getScoreDurationDistribution($entries);
		}
	}



	public function ratiImgAction() {
		//prepare info for view
		if (count($this->view->users) > 1) {
			throw new Exception('Only one user is supported here');
		}
		$user = $this->view->users[0];
		foreach (array(AMModel::TYPE_ANIME, AMModel::TYPE_MANGA) as $am) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $user->getList($am)->getEntries($filter);
			$this->view->scoreDistribution[$am] = UserListService::getScoreDistribution($entries);
		}

		//define some handy constants
		define('IMAGE_TYPE_ANIME', 1);
		define('IMAGE_TYPE_MANGA', 2);
		define('IMAGE_TYPE_ANIME_MANGA', 3);

		define('COLOR_BARS_1', 0);
		define('COLOR_BARS_2', 1);
		define('COLOR_BAR_GUIDES_1', 2);
		define('COLOR_BAR_GUIDES_2', 3);
		define('COLOR_BACKGROUND', 4);
		define('COLOR_BACKGROUND2', 5);
		define('COLOR_FONT_DARK', 6);
		define('COLOR_FONT_LIGHT', 7);
		define('COLOR_TITLE', 8);
		define('COLOR_LOGO', 9);

		//get input data from GET
		$this->view->user = reset($this->view->users);
		if (!empty($_GET['type'])) {
			switch ($_GET['type']) {
				case '2': $this->view->imageType = IMAGE_TYPE_MANGA; break;
				case '3': $this->view->imageType = IMAGE_TYPE_ANIME_MANGA; break;
				default: $this->view->imageType = IMAGE_TYPE_ANIME; break;
			}
		}

		$this->view->colors = [
			COLOR_BARS_1 =>       array('a' => 0x00, 'r' => 0xa4, 'g' => 0xc0, 'b' => 0xf4),
			COLOR_BARS_2 =>       array('a' => 0x00, 'r' => 0x13, 'g' => 0x45, 'b' => 0x9a),
			COLOR_BAR_GUIDES_1 => array('a' => 0xee, 'r' => 0xa4, 'g' => 0xc0, 'b' => 0xf4),
			COLOR_BAR_GUIDES_2 => array('a' => 0xee, 'r' => 0x13, 'g' => 0x45, 'b' => 0x9a),
			COLOR_BACKGROUND =>   array('a' => 0xff, 'r' => 0xff, 'g' => 0xff, 'b' => 0xff),
			COLOR_FONT_DARK =>    array('a' => 0x00, 'r' => 0x00, 'g' => 0x00, 'b' => 0x00),
			COLOR_FONT_LIGHT =>   array('a' => 0xaa, 'r' => 0x00, 'g' => 0x00, 'b' => 0x00),
			COLOR_TITLE =>        array('a' => 0x00, 'r' => 0x57, 'g' => 0x7f, 'b' => 0xc2),
		];

		$defs = [
			COLOR_BARS_1 => 'bar1',
			COLOR_BARS_2 => 'bar2',
			COLOR_BAR_GUIDES_1 => 'line1',
			COLOR_BAR_GUIDES_2 => 'line2',
			COLOR_BACKGROUND => 'back',
			COLOR_BACKGROUND2 => 'back2',
			COLOR_FONT_DARK => 'font1',
			COLOR_FONT_LIGHT => 'font2',
			COLOR_TITLE => 'title',
			COLOR_LOGO => 'logo'
		];

		//sanitize input
		foreach ($defs as $key => $constant) {
			if (isset($_GET[$constant])) {
				$value = $_GET[$constant];
				if (strlen($value) != 8) {
					throw new Exception('Wrong length for ' . $constant . ' (expected 8 characters)');
				}
				$value = array_map('hexdec', str_split($value, 2));
				list($a, $r, $g, $b) = $value;
				$this->view->colors[$key] = array('a' => $a, 'r' => $r, 'g' => $g, 'b' => $b);
			}
		}

		if (empty($this->view->colors[COLOR_BACKGROUND2])) {
			$this->view->colors[COLOR_BACKGROUND2] = $this->view->colors[COLOR_BACKGROUND];
		}
		if (empty($this->view->colors[COLOR_LOGO])) {
			$this->view->colors[COLOR_LOGO] = $this->view->colors[COLOR_TITLE];
		}

		//finally render image
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;
		header('Content-type: image/png');
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		$this->view->render();

		//refresh user data AFTER rendering image
		//because this is an image, we don't send ajax refresh requests
		//and since they can stop visiting the site except for this image,
		//we gotta do an update HERE, where we present image to given user
		$modelUsers = new UserModel();
		try {
			$user = $modelUsers->get($user->getUserName(), UserModel::CACHE_POLICY_DEFAULT);
		} catch (Exception $e) {
		}
	}



	public function actiAction() {
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/highcharts.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/themes/mg.js'));
		HeadHelper::addStylesheet(UrlHelper::url('media/css/infobox.css'));

		foreach ($this->view->users as $i => $u) {
			$actiInfo = [
				'month-periods' => [],
				'day-periods' => [],
				'total-time' => 0,
				'mean-time' => 0,
			];

			//count completed within month periods
			$monthPeriod = false;
			$monthPeriodMin = false;
			$monthPeriodMax = false;

			$filter = UserListFilters::getCompleted();
			$entries = $u->getList($this->view->am)->getEntries($filter);
			foreach ($entries as $e) {
				$monthPeriod = UserListService::getMonthPeriod($e);
				if (!isset($actiInfo['month-periods'][$monthPeriod])) {
					$actiInfo['month-periods'][$monthPeriod] = [
						'duration' => 0,
						'entries' => []
					];
				}
				$actiInfo['month-periods'][$monthPeriod]['entries'] []= $e;
				if ($monthPeriod != '?') {
					if ($monthPeriodMin === false or strcmp($monthPeriod, $monthPeriod) < 0) {
						$monthPeriodMin = $monthPeriod;
					}
					if ($monthPeriodMax === false or strcmp($monthPeriod, $monthPeriodMax) > 0) {
						$monthPeriodMax = $monthPeriod;
					}
				}
				$actiInfo['month-periods'][$monthPeriod]['duration'] += $e->getCompletedDuration();
			}

			//add empty month periods so graph has no gaps
			if (!empty($monthPeriodMin)) {
				list($yearMin, $monthMin) = explode('-', $monthPeriodMin);
				$yearMax = date('Y');
				$monthMax = date('m');
				$keys = [];
				for ($month = $monthMin; $month <= 12; $month ++) {
					$keys []= sprintf('%04d-%02d', $yearMin, $month);
				}
				for ($year = $yearMin + 1; $year < $yearMax; $year ++) {
					for ($month = 1; $month <= 12; $month ++) {
						$keys []= sprintf('%04d-%02d', $year, $month);
					}
				}
				for ($month = 1; $month <= $monthMax; $month ++) {
					$keys []= sprintf('%04d-%02d', $yearMax, $month);
				}
				foreach ($keys as $key) {
					if (!isset($actiInfo['month-periods'][$key])) {
						$actiInfo['month-periods'][$key] = [
							'duration' => 0,
							'entries' => []
						];
					}
				}
				uksort($actiInfo['month-periods'], function($a, $b) {
					if ($a == '?') {
						return -1;
					} elseif ($b == '?') {
						return 1;
					}
					return strcmp($a, $b);
				});
			}

			//add some random information
			$actiInfo['total-time'] = array_sum(array_map(function($mp) { return $mp['duration']; }, $actiInfo['month-periods']));

			list($year, $month, $day) = explode('-', $u->getUserData()->getJoinDate());
			$earliest = mktime(0, 0, 0, $month, $day, $year);
			$entires = $u->getList($this->view->am)->getEntries();
			foreach ($entries as $e) {
				foreach ([$e->getStartDate(), $e->getFinishDate()] as $k) {
					$f = explode('-', $k);
					if (count($f) != 3) {
						continue;
					}
					$year = intval($f[0]);
					$month = intval($f[1]);
					$day = intval($f[2]);
					$time = mktime(0, 0, 0, $month, $day, $year);
					if ($time < $earliest) {
						$earliest = $time;
					}
				}
			}
			$actiInfo['earliest-time'] = $earliest;
			$actiInfo['mean-time'] = $actiInfo['total-time'] / ((time() - $earliest) / (24. * 3600.0));

			//day periods
			$models = [];
			$models[AMModel::TYPE_ANIME] = new AnimeModel();
			$models[AMModel::TYPE_MANGA] = new MangaModel();
			for ($daysBack = 21; $daysBack >= 0; $daysBack --) {
				$dayPeriod = [];
				foreach ($u->getHistory($this->view->am)->getEntriesByDaysAgo($daysBack) as $entry) {
					$dayPeriod []= $entry;
				}
				$actiInfo['day-periods'][$daysBack] = $dayPeriod;
			}

			$this->view->actiInfo[$u->getID()] = $actiInfo;
		}
	}



	public function favsAction() {
		HeadHelper::addScript(UrlHelper::url('media/js/jquery.tablesorter.min.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/highcharts.js'));
		HeadHelper::addScript(UrlHelper::url('media/js/highcharts/themes/mg.js'));

		foreach ($this->view->users as $user) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $user->getList($this->view->am)->getEntries($filter);

			ChibiRegistry::getHelper('benchmark')->benchmark('start');
			$this->view->favCreators[$user->getID()] = UserListService::getCreatorDistribution($entries);
			ChibiRegistry::getHelper('benchmark')->benchmark('creators');
			$this->view->favGenres[$user->getID()] = UserListService::getGenreDistribution($entries);
			ChibiRegistry::getHelper('benchmark')->benchmark('genres');
			$this->view->favYears[$user->getID()] = UserListService::getYearDistribution($entries);
			ChibiRegistry::getHelper('benchmark')->benchmark('years');
			$this->view->favDecades[$user->getID()] = UserListService::getDecadeDistribution($entries);
			ChibiRegistry::getHelper('benchmark')->benchmark('decades');

			$this->view->yearScores[$user->getID()] = [];
			foreach ($this->view->favYears[$user->getID()]->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $this->view->favYears[$user->getID()]->getGroupEntries($key);
				$this->view->yearScores[$user->getID()][$key] = UserListService::getMeanScore($subEntries);
			}
			ChibiRegistry::getHelper('benchmark')->benchmark('years');

			$this->view->decadeScores[$user->getID()] = [];
			foreach ($this->view->favDecades[$user->getID()]->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $this->view->favDecades[$user->getID()]->getGroupEntries($key);
				$this->view->decadeScores[$user->getID()][$key] = UserListService::getMeanScore($subEntries);
			}
			ChibiRegistry::getHelper('benchmark')->benchmark('decades');

			$this->view->creatorScores[$user->getID()] = [];
			$this->view->creatorTimeSpent[$user->getID()] = [];
			foreach ($this->view->favCreators[$user->getID()]->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $this->view->favCreators[$user->getID()]->getGroupEntries($key);
				$this->view->creatorScores[$user->getID()][$key->getID()] = UserListService::getMeanScore($subEntries);
				$this->view->creatorTimeSpent[$user->getID()][$key->getID()] = UserListService::getTimeSpent($subEntries);
			}
			ChibiRegistry::getHelper('benchmark')->benchmark('creators');

			$this->view->genreScores[$user->getID()] = [];
			$this->view->genreTimeSpent[$user->getID()] = [];
			foreach ($this->view->favGenres[$user->getID()]->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $this->view->favGenres[$user->getID()]->getGroupEntries($key);
				$this->view->genreScores[$user->getID()][$key->getID()] = UserListService::getMeanScore($subEntries);
				$this->view->genreTimeSpent[$user->getID()][$key->getID()] = UserListService::getTimeSpent($subEntries);
			}
			ChibiRegistry::getHelper('benchmark')->benchmark('genres');

		}
	}



	public function sugAction() {
		foreach ($this->view->users as $u) {
			$proposedEntries = array();

			//self::sortEntries($u[$this->view->am]['entries'], 'score');

			$filter = UserListFilters::getCompleted();
			$entries = $u->getList($this->view->am)->getEntries($filter);
			foreach ($entries as $entry) {
				foreach ($entry->getAMEntry()->getRelations() as $relation) {
					//proposed entry is already on user's list
					if ($u->getList($this->view->am)->getEntryByID($relation->getID()) != null) {
						continue;
					}
					if ($this->view->am != $relation->getType()) {
						continue;
					}

					if (!isset($proposedEntries[$entry->getID()])) {
						$proposedEntries[$entry->getID()] = [
							'entry' => $entry,
							'entries' => [],
						];
					}
					if ($this->view->am == AMModel::TYPE_ANIME) {
						$model = new AnimeModel();
					} else {
						$model = new MangaModel();
					}
					$entry2 = $model->get($relation->getID());
					$proposedEntries[$entry->getID()]['entries'] []= $entry2;
				}
			}

			uasort($proposedEntries, function($a, $b) { return $b['entry']->getScore() - $a['entry']->getScore(); });

			$this->view->proposedEntries[$u->getID()] = $proposedEntries;
		}
	}
}
