<?php
require_once 'src/controllers/abstract.php';
class StatsController extends AbstractController {
	private static $descSuffix = ' on MALgraph, an online tool that extends your MyAnimeList profile.'; //suffix for <meta> description tag

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

		//add fun keywords
		$this->headHelper->addKeywords(['profile', 'list', 'achievements', 'ratings', 'activity', 'favorites', 'suggestions', 'recommendations']);
		foreach ($this->view->userNames as $userName) {
			if ($userName{0} != '=') {
				$this->headHelper->addKeywords([$userName]);
			}
		}
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

		foreach ($this->view->users as $k => &$user) {
			if (!empty($user['user-name'])) {
				$user['link-name'] = $user['user-name'];
				$user['visible-name'] = $user['user-name'];
				$user['user-safe-id'] = $k;
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



	/*
	 * Load all user entries information
	 */
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
						$e['total-time'] = $length * $duration;

						$nentries[$key] = ['user' => $e, 'full' => $e2];
					}
				}
				$this->view->users[$i][$am]['entries'] = $nentries;
			}
		}

		foreach ($this->view->users as $i => &$u) {
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				$e['user']['unique'] = true;
				$e['others'] = [];
			}
		}

		if (count($this->view->users) > 1) {
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



	/*
	 * Sort given array of entries according to params
	 */
	private static function sortEntries(array &$subject, $sortColumn = null, $sortDir = 0) {
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


	/*
	 * Calculate for each group of entries some basic statistics
	 */
	private static function evaluateGroups(&$groups) {
		$global = [];
		$global['mean-score'] = 0;
		$global['unrated'] = 0;
		$global['rated'] = 0;
		$global['rated-max'] = 0; //maximum rating COUNT within group
		foreach ($groups as &$group) {
			$group['rated'] = 0;
			$group['unrated'] = 0;
			$group['mean-score'] = 0;
			$group['total-time'] = 0;
			foreach ($group['entries'] as &$entry) {
				$score = $entry['user']['score'];
				if ($score > 0) {
					$group['rated'] ++;
					$group['mean-score'] += $score;
					$global['rated'] ++;
					$global['mean-score'] += $score;
				} else {
					$group['unrated'] ++;
					$global['unrated'] ++;
				}
				$time = $entry['user']['total-time'];
				$group['total-time'] += $time;
			}
			$group['mean-score'] /= max(1, $group['rated']);
			$global['rated-max'] = max($group['rated'], $global['rated-max']);
		}
		$global['mean-score'] /= max(1, $global['rated']);
	}




	public function profileAction() {
		$this->loadUsers();

		if (count($this->view->userNames) == 1) {
			$this->headHelper->setTitle('MALgraph - ' . $this->view->users[0]['visible-name'] . '’s profile');
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s profile' . self::$descSuffix);
		} else {
			$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)));
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s profiles' . self::$descSuffix);
		}
	}



	public function listAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addScript($this->urlHelper->url('media/js/jquery.tablesorter.min.js'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - ' . $this->mgHelper->amText() . ' list');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' list' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' lists' . self::$descSuffix);
		}

		foreach ($this->view->users as $i => &$u) {
			self::sortEntries($u[$this->view->am]['entries'], 'score');
		}
	}



	public function achiAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/more.css'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - achievements (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' achievements' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' achievements' . self::$descSuffix);
		}

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
				self::sortEntries($entriesOwned, 'title');
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

			//sort by achievement related titles count
			uasort($achievements, function($a, $b) { return count($a['entries']) - count($b['entries']); });

			$u['achievements'] = $achievements;
		}
	}



	public function ratiAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/infobox.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/more.css'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - rating stats (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' rating statistics' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' rating statistics' . self::$descSuffix);
		}

		//prepare info for view
		foreach ($this->view->users as $i => &$u) {
			$allScores = range(10, 0);

			$scoreInfo = [];
			$scoreInfo['dist-score'] = array_fill_keys($allScores, 0);
			$scoreInfo['dist-time'] = array_fill_keys($allScores, 0);
			$scoreInfo['dist-entries'] = array_fill_keys($allScores, []);
			foreach ($u[$this->view->am]['entries'] as $k => &$e) {
				if ($e['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED) {
					continue;
				}
				$scoreInfo['dist-score'][$e['user']['score']] ++;
				$scoreInfo['dist-time'][$e['user']['score']] += $e['user']['total-time'];
				$scoreInfo['dist-entries'][$e['user']['score']] []= &$e;
			}

			//conert minutes to hours
			foreach ($scoreInfo['dist-time'] as $k => &$v) {
				$v /= 60.0;
			}

			//calculate rated and unrated count
			$scoreInfo['unrated'] = $scoreInfo['dist-score'][0];
			$scoreInfo['rated'] = array_sum($scoreInfo['dist-score']) - $scoreInfo['dist-score'][0];
			$scoreInfo['unrated-total-time'] = $scoreInfo['dist-time'][0];
			$scoreInfo['rated-total-time'] = array_sum($scoreInfo['dist-time']) - $scoreInfo['dist-time'][0];

			//calculate mean
			$scoreInfo['mean-score'] = 0;
			$scoreInfo['mean-time'] = 0;
			foreach ($allScores as $score) {
				$scoreInfo['mean-score'] += $score * $scoreInfo['dist-score'][$score];
				$scoreInfo['mean-time'] += $score * $scoreInfo['dist-time'][$score];
			}
			$scoreInfo['mean-score'] /= max(1, $scoreInfo['rated']);
			$scoreInfo['mean-time'] /= max(1, $scoreInfo['rated-total-time']);

			//calculate standard deviation
			$scoreInfo['std-dev'] = 0;
			foreach ($u[$this->view->am]['entries'] as &$e) {
				if ($e['user']['score'] > 0) {
					$scoreInfo['std-dev'] += pow($e['user']['score'] - $scoreInfo['mean-score'], 2);
				}
			}
			$scoreInfo['std-dev'] /= max(1, $scoreInfo['rated'] - 1);
			$scoreInfo['std-dev'] = sqrt($scoreInfo['std-dev']);

			foreach ($scoreInfo['dist-entries'] as &$entries) {
				self::sortEntries($entries, 'title');
			}
			$u[$this->view->am]['score-info'] = $scoreInfo;
		}
	}



	public function ratiImgAction() {
		$this->loadUsers();
		$this->loadEntries();

		//prepare info for view
		foreach (array(AMModel::ENTRY_TYPE_ANIME, AMModel::ENTRY_TYPE_MANGA) as $am) {
			foreach ($this->view->users as $i => &$u) {
				$allScores = range(10, 0);

				$scoreInfo = [];
				$scoreInfo['dist-score'] = array_fill_keys($allScores, 0);
				$scoreInfo['dist-entries'] = array_fill_keys($allScores, []);
				foreach ($u[$am]['entries'] as $k => &$e) {
					if ($e['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED) {
						continue;
					}
					$scoreInfo['dist-score'][$e['user']['score']] ++;
					$scoreInfo['dist-entries'][$e['user']['score']] []= &$e;
				}

				//calculate rated and unrated count
				$scoreInfo['unrated'] = $scoreInfo['dist-score'][0];
				$scoreInfo['rated'] = array_sum($scoreInfo['dist-score']) - $scoreInfo['dist-score'][0];
				$scoreInfo['rated-max'] = max(array_diff($scoreInfo['dist-score'], array(0 => $scoreInfo['dist-score'][0])));

				//calculate mean
				$scoreInfo['mean-score'] = 0;
				foreach ($allScores as $score) {
					$scoreInfo['mean-score'] += $score * $scoreInfo['dist-score'][$score];
				}
				$scoreInfo['mean-score'] /= max(1, $scoreInfo['rated']);

				//calculate standard deviation
				$scoreInfo['std-dev'] = 0;
				foreach ($u[$am]['entries'] as &$e) {
					if ($e['user']['score'] > 0) {
						$scoreInfo['std-dev'] += pow($e['user']['score'] - $scoreInfo['mean-score'], 2);
					}
				}
				$scoreInfo['std-dev'] /= max(1, $scoreInfo['rated'] - 1);
				$scoreInfo['std-dev'] = sqrt($scoreInfo['std-dev']);

				foreach ($scoreInfo['dist-entries'] as &$entries) {
					self::sortEntries($entries, 'title');
				}
				$u[$am]['score-info'] = $scoreInfo;
			}
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
		$this->config->chibi->runtime->layoutName = null;
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
			$user = $modelUsers->get($this->view->user['user-name']);
		} catch (Exception $e) {
		}
	}



	public function actiAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/infobox.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/more.css'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - activity (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' activity' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' activity' . self::$descSuffix);
		}

		foreach ($this->view->users as $i => &$u) {
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
				if ($yearB and $monthB) {
					$monthPeriod = sprintf('%04d-%02d', $yearB, $monthB);
				} elseif ($yearA and $monthA) {
					$monthPeriod = sprintf('%04d-%02d', $yearA, $monthA);
				} else {
					$monthPeriod = '?';
				}
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
				$actiInfo['month-periods'][$monthPeriod]['duration'] += $e['user']['total-time'];
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
						return 1;
					} elseif ($b == '?') {
						return - 1;
					}
					return strcmp($b, $a);
				});
			}

			//add some random information
			$actiInfo['total-time'] = array_sum(array_map(function($mp) { return $mp['duration']; }, $actiInfo['month-periods']));
			list($year, $month, $day) = explode('-', $u['join-date']);
			$joinedDays = (time() - mktime(0, 0, 0, $month, $day, $year)) / 24. / 3600.;
			$actiInfo['mean-time'] = $actiInfo['total-time'] / $joinedDays;

			//day periods
			$models = [];
			$models[AMModel::ENTRY_TYPE_ANIME] = new AnimeModel();
			$models[AMModel::ENTRY_TYPE_MANGA] = new MangaModel();
			for ($daysBack = 0; $daysBack <= 21; $daysBack ++) {
				$day = date('Y-m-d', mktime(-24 * $daysBack));
				$dayPeriod = [];
				$dayPeriod['entries'] = [];
				foreach ($u[$this->view->am]['history'] as &$e) {
					if ($e['date'] == $day) {
						$dayPeriod['entries'] []= ['user' => $e, 'full' => $models[$e['type']]->get($e['id'])];
					}
				}
				$actiInfo['day-periods'][$daysBack] = $dayPeriod;
			}

			$u[$this->view->am]['acti-info'] = $actiInfo;
		}
	}



	public function favsAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addScript($this->urlHelper->url('media/js/jquery.tablesorter.min.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/more.css'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - favorites (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' favorites' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' favorites' . self::$descSuffix);
		}

		$excludedProducers = json_decode(file_get_contents($this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->excludedProducersDefFile), true);
		$excludedProducerIds = array_map(function($e) { return $e['id']; }, $excludedProducers[$this->mgHelper->amText()]);
		$excludedGenres = json_decode(file_get_contents($this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->excludedGenresDefFile), true);
		$excludedGenreIds = array_map(function($e) { return $e['id']; }, $excludedGenres[$this->mgHelper->amText()]);
		foreach ($this->view->users as &$u) {
			$producers = [];
			$genres = [];
			$decades = [];
			$years = [];

			foreach ($u[$this->view->am]['entries'] as &$entry) {
				if ($entry['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED) {
					continue;
				}

				//producers
				if ($entry['full']['type'] == AMModel::ENTRY_TYPE_MANGA) {
					$x = &$entry['full']['authors'];
				} else {
					$x = &$entry['full']['producers'];
				}
				foreach ($x as $data) {
					if (in_array($data['id'], $excludedProducerIds)) {
						continue;
					}
					$producer = $data['name'];
					if (!isset($producers[$producer])) {
						$producers[$producer] = [
							'entries' => array(),
							'id' => $data['id'],
							'name' => $data['name']
						];
					}
					$producers[$producer]['entries'] []= &$entry;
				}
				unset ($x);

				//genres
				foreach ($entry['full']['genres'] as $data) {
					if (in_array($data['id'], $excludedGenreIds)) {
						continue;
					}
					$genre = $data['name'];
					if (!isset($genres[$genre])) {
						$genres[$genre] = [
							'entries' => array(),
							'id' => $data['id'],
							'name' => $data['name']
						];
					}
					$genres[$genre]['entries'] []= &$entry;
				}

				//years
				$yearA = intval(substr($entry['full']['aired-from'], 0, 4));
				$yearB = intval(substr($entry['full']['aired-to'], 0, 4));
				if (!$yearA and !$yearB) {
					continue;
				} elseif (!$yearA) {
					$year = $yearB;
				} elseif (!$yearB) {
					$year = $yearA;
				} else {
					//$year = ($yearA + $yearB) >> 1;
					$year = $yearA;
				}
				if (!isset($years[$year])) {
					$years[$year] = [
						'year' => $year,
						'entries' => []
					];
				}
				$years[$year]['entries'] []= &$entry;


				//decades
				$decade = floor($year / 10) * 10;
				if (!isset($decades[$decade])) {
					$decades[$decade] = [
						'decade' => $decade,
						'entries' => []
					];
				}
				$decades[$decade]['entries'] []= &$entry;
			}

			//sort
			foreach ($producers as &$producer) {
				self::sortEntries($producer['entries'], 'score');
			}
			unset ($producer);
			foreach ($genres as &$genre) {
				self::sortEntries($genre['entries'], 'score');
			}
			unset ($genre);

			self::evaluateGroups($producers);
			self::evaluateGroups($genres);
			self::evaluateGroups($years);
			self::evaluateGroups($decades);
			ksort($years);
			ksort($decades);
			$u[$this->view->am]['producers'] = $producers;
			$u[$this->view->am]['genres'] = $genres;
			$u[$this->view->am]['years'] = $years;
			$u[$this->view->am]['decades'] = $decades;
		}
	}



	public function miscAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/highcharts.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/highcharts/themes/mg.js'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/infobox.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/more.css'));
		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - miscellaneous (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s miscellaneous ' . $this->mgHelper->amText() . ' statistics' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s miscellaneous ' . $this->mgHelper->amText() . ' statistics' . self::$descSuffix);
		}

		foreach ($this->view->users as &$u) {
			$u[$this->view->am]['dist-status'] = [];
			$u[$this->view->am]['dist-subtype'] = [];
			$u[$this->view->am]['dist-length'] = [];
			$u[$this->view->am]['misc'] = [
				'total-chapters' => 0,
				'total-volumes' => 0,
				'total-episodes' => 0
			];
			$keys = $this->view->am == AMModel::ENTRY_TYPE_ANIME ? [1, 6, 13, 26, 52, 100] : [1, 10, 25, 50, 100, 200];
			for ($i = 0; $i < count($keys); $i ++) {
				$a = $i == 0 ? $keys[0] : $keys[$i - 1] + 1;
				$b = $keys[$i];
				$str = $a == $b ? "$a" : "$a-$b";
				$u[$this->view->am]['dist-length'] []= [
					'a' => $a,
					'b' => $b,
					'text' => $str,
					'entries' => []
				];
			}
			$x = end($u[$this->view->am]['dist-length']);
			$u[$this->view->am]['dist-length'] []= [
				'a' => $x['b'] + 1,
				'b' => null,
				'text' => ($x['b'] + 1) . '+',
				'entries' => []
			];
			foreach ($u[$this->view->am]['entries'] as &$entry) {
				if ($this->view->am == AMModel::ENTRY_TYPE_MANGA) {
					$u[$this->view->am]['misc']['total-volumes'] += $entry['user']['volumes-completed'];
					$u[$this->view->am]['misc']['total-chapters'] += $entry['user']['chapters-completed'];
				} else {
					$u[$this->view->am]['misc']['total-episodes'] += $entry['user']['episodes-completed'];
				}

				if ($entry['user']['status'] == UserModel::USER_LIST_STATUS_COMPLETED) {
					$length = $entry['full'][$this->view->am == AMModel::ENTRY_TYPE_MANGA ? 'chapters' : 'episodes'];
					foreach ($u[$this->view->am]['dist-length'] as &$threshold) {
						if (($threshold['a'] === null or $length >= $threshold['a']) and ($threshold['b'] === null or $length <= $threshold['b'])) {
							$threshold['entries'] []= &$entry;
						}
					}
					unset($threshold);
				}

				if (empty($u[$this->view->am]['dist-status'][$entry['user']['status']])) {
					$u[$this->view->am]['dist-status'][$entry['user']['status']]  = [
						'entries' => [],
						'text' => $this->mgHelper->statusText($entry['user']['status'])
					];
				}
				$u[$this->view->am]['dist-status'][$entry['user']['status']]['entries'] []= &$entry;

				if (empty($u[$this->view->am]['dist-subtype'][$entry['full']['sub-type']])) {
					$u[$this->view->am]['dist-subtype'][$entry['full']['sub-type']] = [
						'entries' => [],
						'text' => $this->mgHelper->subTypeText($entry['full']['sub-type'])
					];
				}
				$u[$this->view->am]['dist-subtype'][$entry['full']['sub-type']]['entries'] []= &$entry;
			}
			//filter empty thresholds
			$u[$this->view->am]['dist-length'] = array_filter($u[$this->view->am]['dist-length'], function($x) { return count($x['entries']) > 0; });
		}
	}



	public function suggAction() {
		$this->loadUsers();
		$this->loadEntries();

		$this->headHelper->setTitle('MALgraph - ' . implode(' & ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . ' - suggestions (' . $this->mgHelper->amText() . ')');
		if (count($this->view->userNames) == 1) {
			$this->headHelper->setDescription($this->view->users[0]['visible-name'] . '’s ' . $this->mgHelper->amText() . ' suggestions' . self::$descSuffix);
		} else {
			$this->headHelper->setDescription('Comparison of ' . implode(' and ', array_map(function($x) { return $x['visible-name']; }, $this->view->users)) . '’s ' . $this->mgHelper->amText() . ' suggestions' . self::$descSuffix);
		}

		foreach ($this->view->users as &$u) {
			$proposedEntries = array();

			//self::sortEntries($u[$this->view->am]['entries'], 'score');

			foreach ($u[$this->view->am]['entries'] as &$entry) {
				if ($entry['user']['status'] != UserModel::USER_LIST_STATUS_COMPLETED) {
					continue;
				}
				foreach ($entry['full']['related'] as $data) {
					//proposed entry is already on user's list and it's not on PTW list
					if (isset($u[$this->view->am]['entries'][$data['id']])/* and $u[$this->view->am]['entries'][$data['id']]['user']['status'] != UserModel::USER_LIST_STATUS_PLANNED*/) {
						continue;
					}
					if ($this->mgHelper->amText() != $data['type']) {
						continue;
					}

					if (!isset($proposedEntries[$entry['full']['id']])) {
						$proposedEntries[$entry['full']['id']] = [
							'entry' => &$entry,
							'entries' => [],
						];
					}
					if ($this->view->am == AMModel::ENTRY_TYPE_ANIME) {
						$model = new AnimeModel();
					} else {
						$model = new MangaModel();
					}
					$entry2 = $model->get($data['id']);
					$proposedEntries[$entry['full']['id']]['entries'] []= $entry2;
				}
			}

			uasort($proposedEntries, function($a, $b) { return $b['entry']['user']['score'] - $a['entry']['user']['score']; });

			$u['proposed-entries'] = $proposedEntries;
		}
	}
}
