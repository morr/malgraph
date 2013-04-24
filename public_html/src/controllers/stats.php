<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/user/listservice.php';

class StatsController extends ChibiController {
	public function beforeWork() {
		ChibiRegistry::getHelper('session')->close();

		//no user specified
		$userNames = ChibiRegistry::getHelper('input')->getStringSafe('u');
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
		$userNames = array_unique(array_map('strtolower', $userNames));
		ChibiRegistry::getView()->userNames = $userNames;

		//load anime-manga switch
		$am = ChibiRegistry::getHelper('input')->get('am');
		if ($am != AMModel::TYPE_MANGA) {
			$am = AMModel::TYPE_ANIME;
		}
		ChibiRegistry::getView()->am = $am;

		if (count(ChibiRegistry::getView()->userNames) > 2) {
			throw new Exception('Sorry. We haven\'t implemented this.');
		}

		//try to load requested users
		$anons = [];
		$modelUsers = new UserModel();
		ChibiRegistry::getView()->users = [];
		foreach (ChibiRegistry::getView()->userNames as $userName) {
			try {
				$userEntry = $modelUsers->get($userName, AbstractModel::CACHE_POLICY_FORCE_CACHE);
			} catch (InvalidEntryException $e) {
				ChibiRegistry::getHelper('session')->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward(ChibiRegistry::getHelper('mg')->constructUrl('index', 'wrong-user'));
				return;
			} catch (DownloadException $e) {
				$this->forward(ChibiRegistry::getHelper('mg')->constructUrl('index', 'net-down'));
				return;
			}
			if ($userEntry->getUserData()->isBlocked()) {
				ChibiRegistry::getHelper('session')->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward(ChibiRegistry::getHelper('mg')->constructUrl('index', 'blocked-user'));
			}
			ChibiRegistry::getView()->users []= $userEntry;
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
			'nick' => ChibiRegistry::getView()->users[0]->getPublicName(),
			'nicks_amp' => implode(' & ', array_map(function($user) { return $user->getPublicName(); }, ChibiRegistry::getView()->users)),
			'nicks_and' => implode(' and ', array_map(function($user) { return $user->getPublicName(); }, ChibiRegistry::getView()->users)),
			'am' => ChibiRegistry::getHelper('mg')->textAM()
		];

		if (isset($titles[ChibiRegistry::getView()->actionName])) {
			$title = 'MALgraph - ';
			$description = '';
			if (count(ChibiRegistry::getView()->users) > 1) {
				$title .= $titles[ChibiRegistry::getView()->actionName][1];
				$description .= $descriptions[ChibiRegistry::getView()->actionName][1];
			} else {
				$title .= $titles[ChibiRegistry::getView()->actionName][0];
				$description .= $descriptions[ChibiRegistry::getView()->actionName][0];
			}
			$title = ChibiRegistry::getHelper('mg')->replaceTokens($title, $tokens);
			$description = ChibiRegistry::getHelper('mg')->replaceTokens($description, $tokens);

			ChibiRegistry::getHelper('head')->setTitle($title);
			ChibiRegistry::getHelper('head')->setDescription($description);
			ChibiRegistry::getHelper('head')->addKeywords(['profile', 'list', 'achievements', 'ratings', 'activity', 'favorites', 'suggestions', 'recommendations']);
			foreach (ChibiRegistry::getView()->userNames as $userName) {
				if ($userName{0} != '=') {
					ChibiRegistry::getHelper('head')->addKeywords([$userName]);
				}
			}
		}
	}


	public function profileAction() {
		MediaHelper::addMedia([MediaHelper::TABLESORTER]);
		ChibiRegistry::getView()->profileInfo = [];

		foreach (ChibiRegistry::getView()->users as $u) {
			$info = [];
			foreach (AMModel::getTypes() as $type) {
				$info[$type] = new StdClass;
				$info[$type]->completed = 0;
				$info[$type]->eps = 0;
				$info[$type]->epsMismatched = [];

				$entriesNonPlanned = $u->getList($type)->getEntries(UserListFilters::getNonPlanned());
				$entriesCompleted = $u->getList($type)->getEntries(UserListFilters::getCompleted());
				$scoreDistribution = new ScoreDistribution();
				$subTypeDistribution = new SubTypeDistribution();
				foreach ($entriesCompleted as $entry) {
					$info[$type]->completed += 1;
				}
				foreach ($entriesNonPlanned as $entry) {
					$subTypeDistribution->addEntry($entry);
					$scoreDistribution->addEntry($entry);
					if ($type == AMModel::TYPE_ANIME) {
						$a = $entry->getCompletedEpisodes();
						$b = $entry->getAMEntry()->getEpisodeCount();
					} else {
						$a = $entry->getCompletedChapters();
						$b = $entry->getAMEntry()->getChapterCount();
					}
					$info[$type]->eps += $a;
				}
				$subTypeDistribution->finalize();
				$info[$type]->scoreDistribution = $scoreDistribution;
				$info[$type]->subTypeDistribution = $subTypeDistribution;
				$info[$type]->epsMismatched = UserListService::getMismatchedEntries($entriesNonPlanned);
				$info[$type]->franchises = UserListService::getFranchises($entriesNonPlanned);
			}
			ChibiRegistry::getView()->profileInfo[$u->getRuntimeID()] = $info;

		}
		require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/globals.php';
		ChibiRegistry::getView()->globalInfo = GlobalsModel::getData();

	}



	public function listAction() {
		MediaHelper::addMedia([MediaHelper::TABLESORTER]);
	}



	public function achiAction() {
		$achList = ChibiRegistry::getHelper('mg')->loadJSON(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->achDefFile);

		$imgFiles = scandir(ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/media/img/ach');
		$getThreshold = function($ach) {
			if (preg_match('/^([0-9.]+)\+$/', $ach['threshold'], $matches)) {
				return [floatval($matches[1]), null];
			} elseif (preg_match('/^([0-9.]+)(\.\.|-)([0-9.]+)$/', $ach['threshold'], $matches)) {
				return [floatval($matches[1]), floatval($matches[3])];
			}
			throw new Exception('Invalid threshold: ' . $ach['threshold']);
		};

		foreach (ChibiRegistry::getView()->users as $i => $u) {
			$entriesCompleted = $u->getList(ChibiRegistry::getView()->am)->getEntries(UserListFilters::getCompleted());
			$entriesNonPlanned = $u->getList(ChibiRegistry::getView()->am)->getEntries(UserListFilters::getNonPlanned());
			$achievements = [];

			foreach ($achList[ChibiRegistry::getView()->am] as $group => $groupData) {
				//get subject and entries basing on requirement type
				$subject = null;
				$entriesOwned = null;
				switch ($groupData['requirement']['type']) {
					case 'given-titles':
						$entriesOwned = array();
						foreach ($entriesCompleted as $e) {
							if (in_array($e->getID(), $groupData['requirement']['titles'])) {
								$entriesOwned []= $e;
							}
						}
						$subject = count($entriesOwned);
						break;
					case 'genre-titles':
						$filterBase = UserListFilters::getGenre($groupData['requirement']['genre']);
						if (!empty($groupData['requirement']['titles'])) {
							$filter = function($e) use ($groupData, $filterBase) { return $filterBase($e) or in_array($e->getID(), $groupData['requirement']['titles']); };
						} else {
							$filter = $filterBase;
						}
						$entriesOwned = array();
						foreach ($entriesCompleted as $e) {
							if ($filter($e)) {
								$entriesOwned []= $e;
							}
						}
						$subject = count($entriesOwned);
						break;
					case 'completed-titles':
						$subject = count($entriesCompleted);
						break;
					case 'mean-score':
						$distribution = new ScoreDistribution($entriesNonPlanned);
						if ($distribution->getRatedCount() > 0) {
							$subject = $distribution->getMeanScore();
						}
						break;
					default:
						throw new Exception('Invalid requirement: ' . $groupData['requirement']['type']);
				}

				if ($subject === null) {
					continue;
				}

				//give first achievement for which the subject fits into its threshold
				$nextAch = null;
				foreach (array_reverse($groupData['achievements']) as $ach) {
					list($a, $b) = $getThreshold($ach);

					if ((($subject >= $a) or ($a === null)) and (($subject <= $b) or ($b === null))) {
						//put additional info
						$ach['a'] = $a;
						$ach['b'] = $b;
						if (!empty($entriesOwned)) {
							uasort($entriesOwned, UserListSorters::getByTitle());
							$ach['entries'] = $entriesOwned;
						}
						foreach ($imgFiles as $f) {
							if (preg_match('/' . $ach['id'] . '[^0-9a-zA-Z_-]/', $f)) {
								$ach['path'] = $f;
							}
						}
						$ach['progress'] = 100;
						$ach['progress-subject'] = round($subject, 2);
						if ($nextAch !== null) {
							list ($nextA, $nextB) = $getThreshold($nextAch);
							$ach['progress'] = ($subject - $a) * 100.0 / ($nextA - $a);
							$ach['progress-next-a'] = $nextA;
							$ach['progress-next-b'] = $nextB;
						}
						$achievements []= $ach;
						break;
					}
					$nextAch = $ach;
				}
			}

			ChibiRegistry::getView()->achievements[$u->getID()] = $achievements;
		}
	}



	public function ratiAction() {
		MediaHelper::addMedia([MediaHelper::HIGHCHARTS,
			MediaHelper::POPUPS,
			MediaHelper::INFOBOX,
			MediaHelper::FARBTASTIC
		]);

		ChibiRegistry::getView()->scoreDistribution = [];
		foreach (ChibiRegistry::getView()->users as $userEntry) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $userEntry->getList(ChibiRegistry::getView()->am)->getEntries($filter);

			$scoreDistribution = new ScoreDistribution();
			$scoreDurationDistribution = new ScoreDurationDistribution();
			$lengthDistribution = new LengthDistribution();

			foreach ($entries as $entry) {
				$scoreDistribution->addEntry($entry);
				$scoreDurationDistribution->addEntry($entry);
				if ($entry->getAMEntry()->getSubType() != AnimeEntry::SUBTYPE_MOVIE) {
					$lengthDistribution->addEntry($entry);
				}
			}
			$scoreDistribution->finalize();
			$scoreDurationDistribution->finalize();
			$lengthDistribution->finalize();

			ChibiRegistry::getView()->scoreDistribution[$userEntry->getID()] = $scoreDistribution;
			ChibiRegistry::getView()->scoreDurationDistribution[$userEntry->getID()] = $scoreDurationDistribution;
			ChibiRegistry::getView()->lengthDistribution[$userEntry->getID()] = $lengthDistribution;

			//add some random information
			list($year, $month, $day) = explode('-', $userEntry->getUserData()->getJoinDate());
			$earliest = mktime(0, 0, 0, $month, $day, $year);
			$totalTime = 0;
			foreach ($entries as $e) {
				$totalTime += $e->getCompletedDuration();
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
			ChibiRegistry::getView()->earliestTimeKnown[$userEntry->getID()] = $earliest;
			ChibiRegistry::getView()->meanTime[$userEntry->getID()] = $totalTime / max(1, (time() - $earliest) / (24. * 3600.0));
		}
	}



	public function ratiImgAction() {
		//prepare info for view
		if (count(ChibiRegistry::getView()->users) > 1) {
			throw new Exception('Only one user is supported here');
		}
		$user = ChibiRegistry::getView()->users[0];
		foreach (array(AMModel::TYPE_ANIME, AMModel::TYPE_MANGA) as $am) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $user->getList($am)->getEntries($filter);
			ChibiRegistry::getView()->scoreDistribution[$am] = new ScoreDistribution($entries);
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
		ChibiRegistry::getView()->user = reset(ChibiRegistry::getView()->users);
		if (!empty($_GET['type'])) {
			switch ($_GET['type']) {
				case '2': ChibiRegistry::getView()->imageType = IMAGE_TYPE_MANGA; break;
				case '3': ChibiRegistry::getView()->imageType = IMAGE_TYPE_ANIME_MANGA; break;
				default: ChibiRegistry::getView()->imageType = IMAGE_TYPE_ANIME; break;
			}
		}

		ChibiRegistry::getView()->colors = [
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
				if (strlen($value) != 6 and strlen($value) != 8) {
					throw new Exception('Wrong length for ' . $constant . ' (expected 8 or 6 characters)');
				}
				$value = array_map('hexdec', str_split($value, 2));
				if (count($value) == 4) {
					list($a, $r, $g, $b) = $value;
				} else {
					$a = 0;
					list($r, $g, $b) = $value;
				}
				ChibiRegistry::getView()->colors[$key] = array('a' => $a, 'r' => $r, 'g' => $g, 'b' => $b);
			}
		}

		if (empty(ChibiRegistry::getView()->colors[COLOR_BACKGROUND2])) {
			ChibiRegistry::getView()->colors[COLOR_BACKGROUND2] = ChibiRegistry::getView()->colors[COLOR_BACKGROUND];
		}
		if (empty(ChibiRegistry::getView()->colors[COLOR_LOGO])) {
			ChibiRegistry::getView()->colors[COLOR_LOGO] = ChibiRegistry::getView()->colors[COLOR_TITLE];
		}

		//finally render image
		ChibiConfig::getInstance()->chibi->runtime->layoutName = null;
		ChibiConfig::getInstance()->chibi->basic->prettyOutput = false;
		header('Content-type: image/png');
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		echo ChibiRegistry::getView()->renderView();

		//refresh user data AFTER rendering image
		//because this is an image, we don't send ajax refresh requests
		//and since they can stop visiting the site except for this image,
		//we gotta do an update HERE, where we present image to given user
		$modelUsers = new UserModel();
		try {
			$user = $modelUsers->get($user->getUserName(), AbstractModel::CACHE_POLICY_DEFAULT);
		} catch (Exception $e) {
		}
	}



	public function actiAction() {
		MediaHelper::addMedia([MediaHelper::HIGHCHARTS, MediaHelper::INFOBOX]);

		foreach (ChibiRegistry::getView()->users as $i => $u) {
			//count completed within month periods
			$monthPeriod = false;
			$monthPeriodMin = false;
			$monthPeriodMax = false;
			$monthPeriods = [];

			$filter = UserListFilters::getCompleted();
			$entries = $u->getList(ChibiRegistry::getView()->am)->getEntries($filter);
			foreach ($entries as $e) {
				$monthPeriod = UserListService::getMonthPeriod($e);
				if (!isset($monthPeriods[$monthPeriod])) {
					$monthPeriods[$monthPeriod] = [
						'duration' => 0,
						'entries' => []
					];
				}
				$monthPeriods[$monthPeriod]['entries'] []= $e;
				if ($monthPeriod != '?') {
					if ($monthPeriodMin === false or strcmp($monthPeriod, $monthPeriodMin) < 0) {
						$monthPeriodMin = $monthPeriod;
					}
					if ($monthPeriodMax === false or strcmp($monthPeriod, $monthPeriodMax) > 0) {
						$monthPeriodMax = $monthPeriod;
					}
				}
				$monthPeriods[$monthPeriod]['duration'] += $e->getCompletedDuration();
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
					if (!isset($monthPeriods[$key])) {
						$monthPeriods[$key] = [
							'duration' => 0,
							'entries' => []
						];
					}
				}
				uksort($monthPeriods, function($a, $b) {
					if ($a == '?') {
						return -1;
					} elseif ($b == '?') {
						return 1;
					}
					return strcmp($a, $b);
				});
			}

			//day periods
			$dayPeriods = [];
			$dayPeriodTitles = [];
			for ($daysBack = 21; $daysBack >= 0; $daysBack --) {
				$dayPeriod = [];
				foreach ($u->getHistory(ChibiRegistry::getView()->am)->getEntriesByDaysAgo($daysBack) as $entry) {
					$dayPeriod []= $entry;
					$dayPeriodTitles[$entry->getID()] = $entry;
				}
				$dayPeriods[$daysBack] = $dayPeriod;
			}

			ChibiRegistry::getView()->monthPeriods[$u->getID()] = $monthPeriods;
			ChibiRegistry::getView()->dayPeriods[$u->getID()] = $dayPeriods;
			ChibiRegistry::getView()->dayPeriodTitles[$u->getID()] = $dayPeriodTitles;
		}
	}



	public function favsAction() {
		MediaHelper::addMedia([
			MediaHelper::HIGHCHARTS,
			MediaHelper::TABLESORTER,
			MediaHelper::INFOBOX,
		]);

		foreach (ChibiRegistry::getView()->users as $user) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $user->getList(ChibiRegistry::getView()->am)->getEntries($filter);

			$favCreators = new CreatorDistribution();
			$favGenres = new GenreDistribution();
			$favYears = new YearDistribution();
			$favDecades = new DecadeDistribution();
			$favLengths = new LengthDistribution();
			foreach ($entries as $entry) {
				$favCreators->addEntry($entry);
				$favGenres->addEntry($entry);
				$favYears->addEntry($entry);
				$favDecades->addEntry($entry);
				if ($entry->getAMEntry()->getSubType() != AnimeEntry::SUBTYPE_MOVIE) {
					$favLengths->addEntry($entry);
				}
			}
			$favDecades->addEmptyDecades();
			$favCreators->finalize();
			$favGenres->finalize();
			$favYears->finalize();
			$favDecades->finalize();
			$favLengths->finalize();
			ChibiRegistry::getView()->favCreators[$user->getID()] = $favCreators;
			ChibiRegistry::getView()->favGenres[$user->getID()] = $favGenres;
			ChibiRegistry::getView()->favYears[$user->getID()] = $favYears;
			ChibiRegistry::getView()->favDecades[$user->getID()] = $favDecades;
			ChibiRegistry::getView()->favLengths[$user->getID()] = $favLengths;

			ChibiRegistry::getView()->lengthScores[$user->getID()] = [];
			foreach ($favLengths->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $favLengths->getGroupEntries($key);
				ChibiRegistry::getView()->lengthScores[$user->getID()][$key] = UserListService::getMeanScore($subEntries);
			}

			ChibiRegistry::getView()->yearScores[$user->getID()] = [];
			foreach ($favYears->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $favYears->getGroupEntries($key);
				ChibiRegistry::getView()->yearScores[$user->getID()][$key] = UserListService::getMeanScore($subEntries);
			}

			ChibiRegistry::getView()->decadeScores[$user->getID()] = [];
			foreach ($favDecades->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $favDecades->getGroupEntries($key);
				ChibiRegistry::getView()->decadeScores[$user->getID()][$key] = UserListService::getMeanScore($subEntries);
			}

			ChibiRegistry::getView()->creatorScores[$user->getID()] = [];
			ChibiRegistry::getView()->creatorValues[$user->getID()] = [];
			ChibiRegistry::getView()->creatorTimeSpent[$user->getID()] = [];
			foreach ($favCreators->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $favCreators->getGroupEntries($key);
				ChibiRegistry::getView()->creatorScores[$user->getID()][$key->getID()] = UserListService::getMeanScore($subEntries);
				ChibiRegistry::getView()->creatorTimeSpent[$user->getID()][$key->getID()] = UserListService::getTimeSpent($subEntries);
			}
			ChibiRegistry::getView()->creatorValues[$user->getID()] = UserListService::evaluateDistribution($favCreators);

			ChibiRegistry::getView()->genreScores[$user->getID()] = [];
			ChibiRegistry::getView()->genreValues[$user->getID()] = [];
			ChibiRegistry::getView()->genreTimeSpent[$user->getID()] = [];
			foreach ($favGenres->getGroupsKeys(Distribution::IGNORE_NULL_KEY) as $key) {
				$subEntries = $favGenres->getGroupEntries($key);
				ChibiRegistry::getView()->genreScores[$user->getID()][$key->getID()] = UserListService::getMeanScore($subEntries);
				ChibiRegistry::getView()->genreTimeSpent[$user->getID()][$key->getID()] = UserListService::getTimeSpent($subEntries);
			}
			ChibiRegistry::getView()->genreValues[$user->getID()] = UserListService::evaluateDistribution($favGenres);
		}
	}



	public function sugAction() {
		ChibiRegistry::getView()->recsStatic = [];
		ChibiRegistry::getView()->recs = [];

		#ChibiRegistry::getHelper('benchmark')->benchmark('init');
		//get list of source users
		$goal = 50;
		$coolUsers = GlobalsModel::getData()->getCoolUsersForCF(ChibiRegistry::getView()->am);
		shuffle($coolUsers);
		$coolUsers = array_slice($coolUsers, 0, $goal);
		$modelUsers = new UserModel();
		$selUsers = [];
		foreach ($coolUsers as $id) {
			$u2 = $modelUsers->get($id, AbstractModel::CACHE_POLICY_FORCE_CACHE);
			$list2 = $u2->getList(ChibiRegistry::getView()->am);
			$selUser = new StdClass;
			$selUser->user = $u2;
			$selUser->list = $list2;
			$selUser->meanScore = $list2->getScoreDistributionForCF()->getMeanScore();
			$selUsers[$id] = $selUser;
		}
		#ChibiRegistry::getHelper('benchmark')->benchmark('got users');


		foreach (ChibiRegistry::getView()->users as $u) {
			$filter = UserListFilters::getCompleted();
			$entries = $u->getList(ChibiRegistry::getView()->am)->getEntries($filter);

			$recsStatic = count($entries) <= 20;
			$modelAM = AMModel::factory(ChibiRegistry::getView()->am);
			$finalAM = [];

			#ChibiRegistry::getHelper('benchmark')->benchmark('init ' . $u->getUserName());
			if ($recsStatic) {
				ChibiRegistry::getView()->recsStatic[$u->getID()] = true;

			//dynamic recommendations...
			} else {
				ChibiRegistry::getView()->recsStatic[$u->getID()] = false;

				$list = $u->getList(ChibiRegistry::getView()->am);
				$meanScore = UserListService::getMeanScore($entries);

				//get titles that are worth checking out
				#$simNormalize = 0;
				$selAM = [];
				foreach ($selUsers as $id => &$selUser) {
					//compute similarity indexes between "me" and selected user
					$sum1 = $sum2a = $sum2b = 0;
					foreach ($entries as $e1) {
						$e2 = $selUser->list->getEntryByID($e1->getID());
						if ($e2 !== null) {
							$score1 = $e1->getScore();
							$score2 = $e2->getScore();
							$tmp1 = ($score1 - $meanScore);
							$tmp2 = ($score2 - $selUser->meanScore);
							$sum1 += $tmp1 * $tmp2;
							$sum2a += $tmp1 * $tmp1;
							$sum2b += $tmp2 * $tmp2;
						}
					}
					$selUser->sim = $sum1 / max(1, sqrt($sum2a * $sum2b));

					//check what titles are on their list
					foreach ($selUser->list->getEntries() as $e2) {
						$e1 = $list->getEntryByID($e2->getID());
						if (!$e1 or $e1->getStatus() == UserListEntry::STATUS_PLANNED) {
							$selAM[$e2->getID()] = true;
						}
					}
					#$simNormalize += abs($selUser->sim);
				}
				$selAM = array_keys($selAM);
				#$simNormalize = 1. / max(1, $simNormalize);
				#ChibiRegistry::getHelper('benchmark')->benchmark('got titles');

				//get title ratings
				$finalAM = [];
				foreach ($selAM as $id) {
					$score = 0;
					foreach ($selUsers as $selUser) {
						$e2 = $selUser->list->getEntryByID($id);
						//filter sources
						if ($e2) {
							$score2 = $e2->getScore();
							if ($score2) {
								$score += $selUser->sim * ($score2 - $selUser->meanScore);
							}
						}
					}
					#$score *= $simNormalize;
					$score += $meanScore;
					$finalAM[$id] = $score;
				}
				arsort($finalAM, SORT_NUMERIC);
				$finalAM = array_keys($finalAM);
				#ChibiRegistry::getHelper('benchmark')->benchmark('got scores');
			}

			//always append at the end shuffled static recommendations
			$staticRecs = ChibiRegistry::getHelper('mg')->loadJSON(ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->staticRecsDefFile);
			shuffle($staticRecs[ChibiRegistry::getView()->am]);
			$finalAM = array_merge($finalAM, $staticRecs[ChibiRegistry::getView()->am]);
			#ChibiRegistry::getHelper('benchmark')->benchmark('added static recs');

			//now, compute final recommendations
			$limit = 15;
			$recs = [];
			$nonPlannedRecs = 0;
			while (count($finalAM) > 0 and $nonPlannedRecs < $limit) {
				//make sure only first unwatched thing in franchise is going to be recommended
				$franchise = $modelAM->get(array_shift($finalAM))->getFranchise();
				uasort($franchise->entries, function($a, $b) { return $a->getID() > $b->getID() ? 1 : -1; });
				$amEntry = null;
				foreach ($franchise->entries as $franchiseEntry) {
					if ($franchiseEntry->getStatus() == AMEntry::STATUS_NOT_YET_PUBLISHED) {
						continue;
					}
					$userEntry = $u->getList(ChibiRegistry::getView()->am)->getEntryByID($franchiseEntry->getID());
					if (!$userEntry) {
						$amEntry = $franchiseEntry;
						$nonPlannedRecs ++;
						break;
					} elseif ($userEntry->getStatus() == UserListEntry::STATUS_PLANNED) {
						$amEntry = $franchiseEntry;
						break;
					}
				}
				if (empty($amEntry)) {
					continue;
				}
				//don't recommend more than one thing in given franchise
				foreach ($franchise->entries as $franchiseEntry) {
					$id2 = $franchiseEntry->getID();
					$finalAM = array_filter($finalAM, function($id) use ($id2) { return $id != $id2; });
				}
				$rec = new StdClass;
				$rec->userEntry = $userEntry;
				$rec->amEntry = $amEntry;
				$recs []= $rec;
			}
			#ChibiRegistry::getHelper('benchmark')->benchmark('computed final recs');

			ChibiRegistry::getHelper('session')->restore();
			ChibiRegistry::getView()->recs[$u->getID()] = $recs;
		}

		ChibiRegistry::getView()->relations = [];
		foreach (ChibiRegistry::getView()->users as $u) {
			$filter = UserListFilters::getNonPlanned();
			$entries = $u->getList(ChibiRegistry::getView()->am)->getEntries($filter);
			$relations = UserListService::getFranchises($entries, null);
			//remove ids user has seen
			foreach ($relations as $franchise) {
				foreach ($franchise->ownEntries as $ownEntry) {
					unset($franchise->entries[$ownEntry->getID()]);
				}
			}
			//filter entries that might be empty after removal
			$relations = array_filter($relations, function($a) {
				return count($a->entries) > 0;
			});
			//sort titles
			foreach ($relations as $franchise) {
				$franchise->meanScore = UserListService::getMeanScore($franchise->ownEntries);
				uasort($franchise->entries, function($a, $b) {
					return $a->getID() - $b->getID();
				});
			}
			uasort($relations, function($a, $b) { return $b->meanScore > $a->meanScore; });

			ChibiRegistry::getView()->relations[$u->getID()] = $relations;
		}
	}
}
