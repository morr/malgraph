<?php
require_once 'src/controllers/abstract.php';
class StatsController extends AbstractController {
	public function init() {
		parent::init();

		//discard session information to speed up things
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
		if ($am != AMModel::ENTRY_TYPE_MANGA) {
			$am = AMModel::ENTRY_TYPE_ANIME;
		}
		$this->view->am = $am;
	}

	/*
	 * Load all users information
	 */
	private function loadUsers() {
		//make sure only two users are compared
		if (count($this->view->userNames) > 2) {
			throw new Exception('Sorry. We haven\'t implemented this.');
		}

		$anons = [];
		$modelUsers = new UserModel(true);
		$this->view->users = [];
		foreach ($this->view->userNames as $userName) {
			try {
				$user = $modelUsers->get($userName);
			} catch (InvalidEntryException $e) {
				$this->sessionHelper->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward($this->mgHelper->constructUrl('index', 'wrong-user'));
				return;
			} catch (DownloadException $e) {
				$this->forward($this->mgHelper->constructUrl('index', 'net-down'));
				return;
			}
			if ($user['blocked']) {
				$this->sessionHelper->restore();
				$_SESSION['wrong-user'] = $userName;
				$this->forward($this->mgHelper->constructUrl('index', 'blocked-user'));
			}
			$this->view->users []= $user;
		}

		foreach ($this->view->users as &$user) {
			if (!empty($user['user-name'])) {
				$user['link-name'] = $user['user-name'];
				$user['visible-name'] = $user['user-name'];
				if ($user['anonymous']) {
					$anons []= &$user;
				}
			}
		}

		//prepare display names of anonymous users
		$anonCurrent = 0;
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		foreach ($anons as $i => &$user) {
			$user['link-name'] = $user['anon-name'];
			$user['visible-name'] = 'Anonymous';
			if (count($anons) > 1) {
				$user['visible-name'] .= ' ' . $alphabet{$i};
			}
		}
	}

	private function loadEntries() {
		$models = [];
		$models[AMModel::ENTRY_TYPE_ANIME] = new AnimeModel();
		$models[AMModel::ENTRY_TYPE_MANGA] = new MangaModel();
		foreach ($this->view->users as $i => $u) {
			foreach ($models as $am => $model) {
				$entries = $u[$am]['entries'];
				$nentries = [];
				foreach ($entries as $k => $e) {
					$key = $e['id'];
					$e2 = $model->get($key);
					if (!empty($e2)) {

						//add additional info
						if ($am == AMModel::ENTRY_TYPE_MANGA) {
							$duration = 10;
							$length = $e['chapters-completed'];
						} else {
							$duration = $e2['duration'];
							$length = $e['episodes-completed'];
						}
						$e['total-duration'] = $length * $duration;

						$nentries[$key] = ['user' => $e, 'full' => $e2];
					}
				}
				$this->view->users[$i][$am]['entries'] = $nentries;
			}
		}
	}

	private function loadUniqueness() {
		foreach ($this->view->users as $i => &$u) {
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				$e['user']['unique'] = true;
				$e['others'] = [];
			}
		}

		if (count($this->view->users) > 1) {
			$sortFuncs['unique'] = function($a, $b) { return $a['user']['unique'] - $b['user']['unique']; };
			foreach ($this->view->users as $i => &$u) {
				foreach ($this->view->users as $j => &$u2) {
					if ($i == $j) {
						continue;
					}
					foreach ($u[$this->view->am]['entries'] as $k => &$e) {
						$key = $e['full']['id'];
						$l2 = &$u2[$this->view->am]['entries'];
						if (!empty($l2[$key])) {
							$e['others'] []= &$l2[$key];
						}
					}
				}
				foreach ($u[$this->view->am]['entries'] as $k => &$e) {
					$e['user']['unique'] = empty($e['others']);
				}
			}
		}
	}

	private function sort(array &$subject, $defaultSortColumn = null, array $customColumns = null) {
		//get sort column
		$sortColumn = null;
		if (isset($_GET['sort-column'])) {
			$sortColumn = $_GET['sort-column'];
		} elseif ($defaultSortColumn != null) {
			$sortColumn = $defaultSortColumn;
		}
		$this->view->sortColumn = $sortColumn;

		//get sort direction
		$sortDir = 0;
		if (isset($_GET['sort-dir'])) {
			$sortDir = intval($_GET['sort-dir']);
		}
		$this->view->sortDir = $sortDir;

		if (empty($subject)) {
			return;
		}

		//some common sorting flavours
		$defs = [];
		$defs['score'] = [0, function($e) { return $e['user']['score']; }];
		$defs['status'] = [1, function($e) {
			//sort statuses like MAL order
			$statuses = array_flip([
				UserModel::USER_LIST_STATUS_WATCHING,
				UserModel::USER_LIST_STATUS_COMPLETED,
				UserModel::USER_LIST_STATUS_ONHOLD,
				UserModel::USER_LIST_STATUS_DROPPED,
				UserModel::USER_LIST_STATUS_PLANNED,
				UserModel::USER_LIST_STATUS_UNKNOWN,
			]);
			return $statuses[$e['user']['status']];
		}];
		$defs['length'] = [0, function($e) { return $e['full']['type'] == AMModel::ENTRY_TYPE_MANGA ? $e['full']['volumes'] : $e['full']['episodes']; }];
		$defs['title'] = [1, function($e) { return strtolower($e['full']['title']); }];
		$defs['unique'] = [1, function($e) { return empty($e['user']['unique']) ? 0 : $e['user']['unique']; }];
		$defs['start-date'] = [1, function($e) { return $e['user']['start-date']; }];
		$defs['finish-date'] = [1, function($e) { return $e['user']['finish-date']; }];

		//load custom sorting flavours
		if (!empty($customColumns)) {
			foreach ($customColumns as $k => $def) {
				$defs[$k] = $def;
			}
		}

		//do sort
		$sort = array_fill_keys(array_keys($defs), []);
		$sortDirs = [];
		foreach ($defs as $key => $def) {
			list($defDefaultDir, $defFunc) = $def;
			$sortDirs[$key] = $defDefaultDir;
		}

		foreach ($subject as $k => &$e) {
			foreach ($defs as $defK => $def) {
				list($defDefaultDir, $defFunc) = $def;
				$sort[$defK][$k] = $defFunc($e);
			}
		}
		if (empty($defs[$sortColumn])) {
			$sortColumn = array_keys($defs)[0];
		}

		array_multisort($sort[$sortColumn], $sortDirs[$sortColumn] ^ $sortDir ? SORT_ASC : SORT_DESC, $sort['title'], SORT_ASC, $subject);
	}



	public function profileAction() {
		$this->loadUsers();
	}



	public function listAction() {
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/table.css'));

		$this->loadUsers();
		$this->loadEntries();
		$this->loadUniqueness();

		foreach ($this->view->users as $i => &$u) {
			$this->sort($u[$this->view->am]['entries'], 'score');
		}
	}



	public function achAction() {
		$this->loadUsers();
		$this->loadEntries();

		foreach ($this->view->users as $i => &$u) {
			$groups = [];
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				$groups [$e['user']['status']] []= &$e;
			}

			$achievements = [];

			// MAL's database has 7310 anime and 21260 manga as of 06 sep 2012
			/*$thresholds = [[
				'count' => 6000,
				'desc' => 'Are you having fun, breaking all the rules?',
				'level' => ucfirst($this->mgHelper->amText()) . ' obsession'
			], [
				'count' => 3000,
				'desc' => 'This deserves no further comment.',
				'level' => ucfirst($this->mgHelper->amText()) . ' maniac'
			], [
				'count' => 1500,
				'desc' => 'Are you even human? This is not possible for mere mortal beings.',
				'level' => 'The Collector',
			], [
				'count' => 700,
				'desc' => 'You should lean back and think about your life for a while&hellip;',
				'level' => 'Basement dweller',
			], [
				'count' => 400,
				'desc' => 'That\'s&hellip; a lot. You can be proud of yourself, that\'s for sure.',
				'level' => 'A lot of free time',
			], [
				'count' => 100,
				'desc' => 'You bought all of them, right?',
				'level' => 'Casual ' . ($this->view->am == AMModel::ENTRY_TYPE_MANGA ? 'reader' : 'watcher'),
			]];
			$count = count($groups[UserModel::USER_LIST_STATUS_COMPLETED]);
			foreach ($thresholds as $threshold) {
				if ($count > $threshold['count']) {
					$achievements []= [
						'id' => 'numbers-' . $this->mgHelper->amText() . $threshold['count'],
						'title' => 'Completed over ' . $threshold['count'] . ' titles',
						'level' => $threshold['level'],
						'desc' => "You've completed over " . $threshold['count'] . " " . $this->mgHelper->amText() . ". " . $threshold['desc'],
					];
				}
			}*/

			$achList = json_decode(file_get_contents($this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->achDefFile), true);
			if ($this->view->am == AMModel::ENTRY_TYPE_ANIME) {
				$model = new AnimeModel();
				$AM = 'anime';
			} else {
				$model = new MangaModel();
				$AM = 'manga';
			}

			//achievments from json
			foreach ($achList[$AM] as $group => $groupData) {
				var_dump($group);
				$achIds = array();
				foreach ($groupData['titles'] as $title) {
					$achIds []= $title['id'];
				}
				$entriesOwned = array();
				foreach ($groups[UserModel::USER_LIST_STATUS_COMPLETED] as $k => $e) {
					if (in_array($e['user']['id'], $achIds)) {
						$entriesOwned []= &$groups[UserModel::USER_LIST_STATUS_COMPLETED][$k];
					}
				}
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
			if ($this->view->am == AMModel::ENTRY_TYPE_ANIME) {
				$count = 0;
				$sum = 0;
				foreach ($u[$this->view->am]['entries'] as $e) {
					if ($e['user']['score'] > 0 and $e['user']['status'] != UserModel::USER_LIST_STATUS_PLANNED) {
						$sum += $e['user']['score'];
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

			$files = scandir(implode(DIRECTORY_SEPARATOR, [$this->config->chibi->runtime->rootFolder, 'media', 'img', 'ach']));

			foreach ($achievements as &$ach) {
				$ach['path'] = null;
				foreach ($files as $f) {
					if (preg_match('/' . $ach['id'] . '[^0-9a-zA-Z_-]/', $f)) {
						$ach['path'] = $f;
					}
				}
			}
			unset($ach);

			$u['achievements'] = $achievements;
		}
	}



	public function scoreAction() {
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/table.css'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));

		$this->loadUsers();
		$this->loadEntries();
		$this->loadUniqueness();

		//prepare info for view
		foreach ($this->view->users as $i => &$u) {
			$scoreDist = array_fill_keys(range(10, 0), 0);
			$scoreTimeDist = array_fill_keys(range(10, 0), 0);
			$scoreInfo = [];
			$scoreInfo['total'] = 0;
			$scoreInfo['planned'] = 0;
			$scoreInfo['unrated-titles'] = [];
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				if ($e['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED) {
					$scoreInfo['planned'] ++;
					continue;
				}
				$scoreInfo['total'] ++;
				$scoreDist[$e['user']['score']] ++;
				$scoreTimeDist[$e['user']['score']] += $e['user']['total-duration'];
				if ($e['user']['score'] == 0) {
					$scoreInfo['unrated-titles'] []= &$e;
				}
			}

			//conert minutes to hours
			foreach ($scoreTimeDist as $k => &$v) {
				$v /= 60.0;
			}

			//calculate rated and unrated count
			$scoreInfo['unrated'] = $scoreDist[0];
			$scoreInfo['rated'] = array_sum($scoreDist) - $scoreDist[0];
			$scoreInfo['unrated-total-time'] = $scoreTimeDist[0];
			$scoreInfo['rated-total-time'] = array_sum($scoreTimeDist) - $scoreTimeDist[0];

			//calculate mean
			$scoreInfo['mean'] = 0;
			foreach ($scoreDist as $score => $count) {
				$scoreInfo['mean'] += $score * $count;
			}
			$scoreInfo['mean'] /= max(1, $scoreInfo['rated']);

			//calculate standard deviation
			$scoreInfo['std-dev'] = 0;
			foreach ($u[$this->view->am]['entries'] as &$e) {
				if ($e['user']['score'] > 0) {
					$scoreInfo['std-dev'] += pow($e['user']['score'] - $scoreInfo['mean'], 2);
				}
			}
			$scoreInfo['std-dev'] /= max(1, $scoreInfo['rated'] - 1);
			$scoreInfo['std-dev'] = sqrt($scoreInfo['std-dev']);

			$u[$this->view->am]['score-info'] = $scoreInfo;
			$u[$this->view->am]['score-dist'] = $scoreDist;
			$u[$this->view->am]['score-time-dist'] = $scoreTimeDist;
			$this->sort($u[$this->view->am]['score-info']['unrated-titles'], 'length');
		}
	}




	public function actiAction() {
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/table.css'));

		$this->loadUsers();
		$this->loadEntries();

		foreach ($this->view->users as $i => &$u) {
			//count completed within month periods
			$monthPeriods = [];
			$omitted = [];
			$monthPeriod = false;
			$monthPeriodMin = false;
			$monthPeriodMax = false;
			foreach ($u[$this->view->am]['entries'] as &$e) {
				if ($e['user']['status'] != UserModel::USER_LIST_STATUS_COMPLETED) {
					continue;
				}
				$finishedA = explode('-', $e['user']['start-date']);
				$finishedB = explode('-', $e['user']['finish-date']);
				$yearA = intval($finishedA[0]);
				$yearB = intval($finishedB[0]);
				$monthA = isset($finishedA[1]) ? intval($finishedA[1]) : false;
				$monthB = isset($finishedB[1]) ? intval($finishedB[1]) : false;
				if ((!$yearA or !$monthA) and (!$yearB or !$monthB)) {
					$omitted []= $e;
					continue;
				}
				if (!$yearB or !$monthB) {
					$monthPeriod = sprintf('%04d-%02d', $yearA, $monthA);
				} elseif (!$yearA or !$monthA) {
					$monthPeriod = sprintf('%04d-%02d', $yearB, $monthB);
				} else {
					$monthPeriod = sprintf('%04d-%02d', $yearB, $monthB);
				}
				if (!isset($monthPeriods[$monthPeriod])) {
					$monthPeriods[$monthPeriod] = [
						'duration' => 0,
						'titles' => []
					];
				}
				$monthPeriods[$monthPeriod]['titles'] []= $e;
				if ($monthPeriodMin === false or strcmp($monthPeriod, $monthPeriod) < 0) {
					$monthPeriodMin = $monthPeriod;
				}
				if ($monthPeriodMax === false or strcmp($monthPeriod, $monthPeriodMax) > 0) {
					$monthPeriodMax = $monthPeriod;
				}
				$monthPeriods[$monthPeriod]['duration'] += $e['user']['total-duration'];
			}

			//add empty monthPeriods so graph has no gaps
			list($yearMin, $monthMin) = explode('-', $monthPeriodMin);
			list($yearMax, $monthMax) = explode('-', $monthPeriodMax);
			//if now is later than given date, set it to now
			//(these check are prolly unneeded, but i put them here to be on safe note with timezones)
			if (date('Y') > $yearMax) {
				$yearMax = date('Y');
				$monthMax = date('m');
			} elseif (date('Y') == $yearMax) {
				if (date('m') > $monthMax) {
					$monthMax = date('m');
				}
			}
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
						'titles' => []
					];
				}
			}
			krsort($monthPeriods);

			$dayPeriods = [];
			$models = [];
			$models[AMModel::ENTRY_TYPE_ANIME] = new AnimeModel();
			$models[AMModel::ENTRY_TYPE_MANGA] = new MangaModel();
			for ($daysBack = 0; $daysBack <= 21; $daysBack ++) {
				$day = date('Y-m-d', mktime(-24 * $daysBack));
				$dayPeriod = [];
				$dayPeriod['titles'] = [];
				foreach ($u[$this->view->am]['history'] as &$e) {
					if ($e['date'] == $day) {
						$dayPeriod['titles'] []= ['user' => $e, 'full' => $models[$e['type']]->get($e['id'])];
					}
				}
				$dayPeriods[$daysBack] = $dayPeriod;
			}

			$u[$this->view->am]['acti-info'] = [
				'month-periods' => $monthPeriods,
				'day-periods' => $dayPeriods,
				'omitted-titles' => $omitted
			];

			$this->sort($u[$this->view->am]['acti-info']['omitted-titles'], 'title');

		}
	}




	public function jsonAction() {
		$this->loadUsers();
		$this->loadEntries();
	}
}
