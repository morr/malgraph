<?php
require_once 'abstract.php';
require_once 'am.php';
class UserModel extends JSONDB {
	const USER_GENDER_FEMALE = 'F';
	const USER_GENDER_MALE = 'M';
	const USER_GENDER_UNKNOWN = '?';

	const USER_LIST_STATUS_DROPPED = 'dropped';
	const USER_LIST_STATUS_ONHOLD = 'onhold';
	const USER_LIST_STATUS_COMPLETING = 'completing';
	const USER_LIST_STATUS_WATCHING = self::USER_LIST_STATUS_COMPLETING;
	const USER_LIST_STATUS_READING = self::USER_LIST_STATUS_COMPLETING;
	const USER_LIST_STATUS_COMPLETED = 'completed';
	const USER_LIST_STATUS_FINISHED = self::USER_LIST_STATUS_COMPLETED;
	const USER_LIST_STATUS_PLANNED = 'planned';
	const USER_LIST_STATUS_UNKNOWN = '???';

	const USER_LIST_STATUS_MAL_DROPPED = 4;
	const USER_LIST_STATUS_MAL_ONHOLD = 3;
	const USER_LIST_STATUS_MAL_COMPLETING = 1;
	const USER_LIST_STATUS_MAL_COMPLETED = 2;
	const USER_LIST_STATUS_MAL_PLANNED = 6;

	const USER_URL_HISTORY = 'http://myanimelist.net/history/{user}';
	const USER_URL_ANIME1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all';
	const USER_URL_MANGA1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all&type=manga';
	const USER_URL_ANIME2 = 'http://myanimelist.net/animelist/{user}&sclick=1';
	const USER_URL_MANGA2 = 'http://myanimelist.net/mangalist/{user}&sclick=1';
	const USER_URL_PROFILE = 'http://myanimelist.net/profile/{user}';
	const USER_URL_CLUBS = 'http://myanimelist.net/showclubs.php?id={user-id}';
	const USER_URL_FRIENDS = 'http://myanimelist.net/friends.php?id={user-id}&show={shift}';

	private static $types = [
		AMModel::ENTRY_TYPE_ANIME,
		AMModel::ENTRY_TYPE_MANGA
	];

	private $anonsFile;
	private $anons;

	private $freezeUpdating;

	public function isFresh($data) {
		if ($this->freezeUpdating) {
			return true;
		}
		return isset($data['expires']) and time() <= $data['expires'];
	}

	public function __construct($freeze = false) {
		$this->freezeUpdating = $freeze;
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->userCacheDir;
		$this->anonsFile = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->anonLookupFile;
		if (file_exists($this->anonsFile)) {
			$this->anons = json_decode(file_get_contents($this->anonsFile), true);
		} else {
			$this->anons = [];
		}
	}

	protected function fixStatus($malStatus) {
		switch ($malStatus) {
			case self::USER_LIST_STATUS_MAL_DROPPED: return self::USER_LIST_STATUS_DROPPED; break;
			case self::USER_LIST_STATUS_MAL_ONHOLD: return self::USER_LIST_STATUS_ONHOLD; break;
			case self::USER_LIST_STATUS_MAL_COMPLETING: return self::USER_LIST_STATUS_COMPLETING; break;
			case self::USER_LIST_STATUS_MAL_COMPLETED: return self::USER_LIST_STATUS_COMPLETED; break;
			case self::USER_LIST_STATUS_MAL_PLANNED: return self::USER_LIST_STATUS_PLANNED; break;
		}
		return self::USER_LIST_STATUS_UNKNOWN;
	}

	protected function loadLists(array &$user, array &$documents) {
		$urls = [];

		foreach (self::$types as $type) {
			$user[$type] = [];
			if ($type == AMModel::ENTRY_TYPE_ANIME) {
				$contents = $documents[self::USER_URL_ANIME2];
			} else {
				$contents = $documents[self::USER_URL_MANGA2];
			}

			if (strpos($contents, 'This list has been made private by the owner') !== false) {
				$user[$type]['private'] = true;
			} else {
				$user[$type]['private'] = false;
			}
		}

		foreach (self::$types as $type) {
			$list = &$user[$type];
			if ($type == AMModel::ENTRY_TYPE_ANIME) {
				$contents = $documents[self::USER_URL_ANIME1];
			} else {
				$contents = $documents[self::USER_URL_MANGA1];
			}

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$this->mgHelper->suppressErrors();
			$doc->loadHTML($contents);
			$this->mgHelper->restoreErrors();
			$xpath = new DOMXPath($doc);

			if ($xpath->query('//myinfo')->length == 0) {
				//user not found?
				throw new InvalidEntryException($user['user-name']);
			}

			$list['entries'] = [];
			$nodes = $xpath->query('//anime | //manga');
			foreach ($nodes as $root) {
				$entry = [];
				$entry['id'] = intval($xpath->query('series_animedb_id | series_mangadb_id', $root)->item(0)->nodeValue);

				$node = $xpath->query('my_score', $root)->item(0);
				if (!empty($node)) {
					$entry['score'] = intval($node->nodeValue);
				} else {
					$entry['score'] = 0;
				}

				$entry['status'] = $this->fixStatus($xpath->query('my_status', $root)->item(0)->nodeValue);

				$entry['start-date'] = $this->mgHelper->fixDate($xpath->query('my_start_date', $root)->item(0)->nodeValue);

				$entry['finish-date'] = $this->mgHelper->fixDate($xpath->query('my_finish_date', $root)->item(0)->nodeValue);

				if ($type == AMModel::ENTRY_TYPE_ANIME) {
					$entry['episodes-completed'] = intval($xpath->query('my_watched_episodes', $root)->item(0)->nodeValue);
				}
				else {
					$entry['chapters-completed'] = intval($xpath->query('my_read_chapters', $root)->item(0)->nodeValue);
					$entry['volumes-completed'] = intval($xpath->query('my_read_volumes', $root)->item(0)->nodeValue);
				}

				$list['entries'] []= $entry;
			}

			$list['time-spent'] = floatval($xpath->query('//user_days_spent_watching')->item(0)->nodeValue);
		}
	}


	protected function loadProfile(array &$user, array &$documents) {
		$contents = $documents[self::USER_URL_PROFILE];

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$this->mgHelper->suppressErrors();
		$doc->loadHTML($contents);
		$this->mgHelper->restoreErrors();
		$xpath = new DOMXPath($doc);

		//basic information
		$user[AMModel::ENTRY_TYPE_ANIME]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Anime List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user[AMModel::ENTRY_TYPE_MANGA]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Manga List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user['user-name'] = $this->mgHelper->fixText($xpath->query('//title')->item(0)->nodeValue);
		$user['user-name'] = substr($user['user-name'], 0, strpos($user['user-name'], '\'s Profile'));

		//static information
		$user['profile-picture-url'] = $xpath->query('//td[@class = \'profile_leftcell\']//img')->item(0)->getAttribute('src');
		$user['join-date'] = $this->mgHelper->fixDate($xpath->query('//td[text() = \'Join Date\']/following-sibling::td')->item(0)->nodeValue);
		$url = $this->mgHelper->parseURL($xpath->query('//a[text() = \'All Comments\']')->item(0)->getAttribute('href'));
		$user['user-id'] = intval($url['query']['id']);

		//comments
		$node = $xpath->query('//td[text() = \'Comments\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$user['comment-count'] = intval($node->nodeValue);
		}

		//posts
		$node = $xpath->query('//td[text() = \'Forum Posts\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$user['post-count'] = intval($node->nodeValue);
		}

		//dynamic information
		$user['birthday'] = null;
		$node = $xpath->query('//td[text() = \'Birthday\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$user['birthday'] = $this->mgHelper->fixDate($node->nodeValue);
		}

		$user['location'] = null;
		$node = $xpath->query('//td[text() = \'Location\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$user['location'] = $this->mgHelper->fixText($node->nodeValue);
		}

		$user['website'] = null;
		$node = $xpath->query('//td[text() = \'Website\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$user['website'] = $this->mgHelper->fixText($node->nodeValue);
		}


		$gender = $xpath->query('//td[text() = \'Gender\']/following-sibling::td')->item(0)->nodeValue;
		switch($gender) {
			case 'Female': $user['gender'] = self::USER_GENDER_FEMALE; break;
			case 'Male': $user['gender'] = self::USER_GENDER_MALE; break;
			case 'Not specified': $user['gender'] = self::USER_GENDER_UNKNOWN; break;
		}



		$node = $xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Clubs\')]')->item(0);
		$user['clubs'] = [];
		if (!empty($node)) {
			$clubCount = intval(substr($node->nodeValue, 13));
			if ($clubCount <= 15) {
				$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'/club\')]');
				foreach ($q as $node) {
					$url = $this->mgHelper->parseURL($node->getAttribute('href'));
					$club = [];
					$club['id'] = intval($url['query']['cid']);
					$club['name'] = $this->mgHelper->fixText($node->nodeValue);
					$user['clubs'] []= $club;
				}
			} else {
				$this->loadClubs($user);
			}
		}



		$node = $xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Friends\')]')->item(0);
		$user['friends'] = [];
		if (!empty($node)) {
			$friendCount = intval(substr($node->nodeValue, 15));
			if ($friendCount <= 30) {
				$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'profile\')]');
				foreach ($q as $node) {
					$user['friends'] []= $this->mgHelper->fixText($node->nodeValue);
				}
			} else {
				$this->loadFriends($user);
			}
		}

	}


	protected function loadClubs(array &$user) {
		$user['clubs'] = [];

		$url = $this->mgHelper->replaceTokens(self::USER_URL_CLUBS, ['user-id' => $user['user-id']]);
		$contents = $this->mgHelper->download($url);
		if (empty($contents)) {
			throw new DownloadException($url);
		}

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$this->mgHelper->suppressErrors();
		$doc->loadHTML($contents);
		$this->mgHelper->restoreErrors();
		$xpath = new DOMXPath($doc);

		$nodes = $xpath->query('//ol//li//a');
		foreach ($nodes as $node) {
			$url = $this->mgHelper->parseURL($node->getAttribute('href'));
			$club = [];
			$club['id'] = intval($url['query']['cid']);
			$club['name'] = $this->mgHelper->fixText($node->nodeValue);
			$user['clubs'] []= $club;
		}
	}

	protected function loadFriends(array &$user) {
		$user['friends'] = [];

		$max = 0;
		$shift = 0;
		$page = 6 * 7;
		do {
			$url = $this->mgHelper->replaceTokens(self::USER_URL_FRIENDS, ['user-id' => $user['user-id'], 'shift' => $shift]);
			$contents = $this->mgHelper->download($url);
			if (empty($contents)) {
				throw new DownloadException($url);
			}

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$this->mgHelper->suppressErrors();
			$doc->loadHTML($contents);
			$this->mgHelper->restoreErrors();
			$xpath = new DOMXPath($doc);

			preg_match('/ has (\d+) friends/', $contents, $results);
			$max = intval($results[1]);

			$nodes = $xpath->query('//table//div[contains(@style, \'margin\')]/a[contains(@href, \'profile\')]');
			foreach ($nodes as $node) {
				$user['friends'] []= $this->mgHelper->fixText($node->nodeValue);
			}

			$shift += $page;
		} while ($shift < $max);
	}


	protected function loadHistory(array &$user, array &$documents) {
		$contents = $documents[self::USER_URL_HISTORY];

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$this->mgHelper->suppressErrors();
		$doc->loadHTML($contents);
		$this->mgHelper->restoreErrors();
		$xpath = new DOMXPath($doc);

		$nodes = $xpath->query('//table//td[@class = \'borderClass\']/..');

		foreach ($nodes as $node) {
			//basic info
			$link = $node->childNodes->item(0)->childNodes->item(0)->getAttribute('href');
			preg_match('/(\d+)\/?$/', $link, $matches);
			$entry['id'] = intval($matches[0]);
			$sub = intval($node->childNodes->item(0)->childNodes->item(2)->nodeValue);
			if (strpos($link, 'manga') !== false) {
				$type = AMModel::ENTRY_TYPE_MANGA;
				$entry['chap'] = $sub;
			} else {
				$type = AMModel::ENTRY_TYPE_ANIME;
				$entry['ep'] = $sub;
			}
			$entry['type'] = $type;

			//parse time
			//That's what MAL servers output for MG client
			date_default_timezone_set('America/Los_Angeles');
			$hour =   date('H');
			$minute = date('i');
			$second = date('s');
			$day =    date('d');
			$month =  date('m');
			$year =   date('Y');
			$dateString = $node->childNodes->item(2)->nodeValue;
			if (preg_match('/(\d*) seconds? ago/', $dateString, $matches)) {
				$second -= intval($matches[1]);
			} elseif (preg_match('/(\d*) minutes? ago/', $dateString, $matches)) {
				$second += - intval($matches[1]) * 60;
			} elseif (preg_match('/(\d*) hours? ago/', $dateString, $matches)) {
				$minute += - intval($matches[1]) * 60;
			} elseif (preg_match('/Today, (\d*):(\d\d) (AM|PM)/', $dateString, $matches)) {
				$hour = intval($matches[1]);
				$minute = intval($matches[2]);
				$hour += ($matches[3] == 'PM' and $hour != 12) ? 12 : 0;
			} elseif (preg_match('/Yesterday, (\d*):(\d\d) (AM|PM)/', $dateString, $matches)) {
				$hour = intval($matches[1]);
				$minute = intval($matches[2]);
				$hour += ($matches[3] == 'PM' and $hour != 12) ? 12 : 0;
				$hour -= 24;
			} elseif (preg_match('/(\d\d)-(\d\d)-(\d\d), (\d*):(\d\d) (AM|PM)/', $dateString, $matches)) {
				$year = intval($matches[3]) + 2000;
				$month = intval($matches[1]);
				$day = intval($matches[2]);
				$hour = intval($matches[4]);
				$minute = intval($matches[5]);
				$hour += ($matches[6] == 'PM' and $hour != 12) ? 12 : 0;
			}
			$time = mktime($hour, $minute, $second, $month, $day, $year);
			date_default_timezone_set('UTC');
			$entry['date'] = date('Y-m-d', $time);
			$entry['hour'] = date('H:i:s', $time);
			$user[$type]['history'] []= $entry;
		}
	}




	public function getReal($userName) {
		$user = $this->getCached($userName);
		if (empty($user)) {
			$user = [];
			$user['vip'] = false;
			$user['blocked'] = false;
			$user['user-name'] = $userName;
			do {
				$alpha = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				$anonName = '=';
				foreach (range(1, 8) as $k) {
					$anonName .= $alpha{mt_rand() % strlen($alpha)};
				}
			} while (!empty($this->anons[$anonName]) and $this->anons[$anonName] != $user['user-name']);
			$user['anon-name'] = $anonName;
		}

		$user['generated'] = time();
		if ($user['vip']) {
			$user['expires'] = time() + 3600 * 3;
		} else {
			$user['expires'] = time() + 3600 * 24;
		}

		if ($user['blocked']) {
			return $user;
		}

		$urls = [];
		$urls[self::USER_URL_ANIME1] = $this->mgHelper->replaceTokens(self::USER_URL_ANIME1, ['user' => $user['user-name']]);
		$urls[self::USER_URL_MANGA1] = $this->mgHelper->replaceTokens(self::USER_URL_MANGA1, ['user' => $user['user-name']]);
		$urls[self::USER_URL_ANIME2] = $this->mgHelper->replaceTokens(self::USER_URL_ANIME2, ['user' => $user['user-name']]);
		$urls[self::USER_URL_MANGA2] = $this->mgHelper->replaceTokens(self::USER_URL_MANGA2, ['user' => $user['user-name']]);
		$urls[self::USER_URL_PROFILE] = $this->mgHelper->replaceTokens(self::USER_URL_PROFILE, ['user' => $user['user-name']]);
		$urls[self::USER_URL_HISTORY] = $this->mgHelper->replaceTokens(self::USER_URL_HISTORY, ['user' => $user['user-name']]);

		$documents = $this->mgHelper->downloadMulti($urls);
		foreach ($documents as $type => $contents) {
			if (empty($contents)) {
				throw new DownloadException($urls[$type]);
			}
		}

		$this->loadLists($user, $documents);
		$this->loadProfile($user, $documents);
		$this->loadHistory($user, $documents);
		return $user;
	}

	public function get($key, $getReal = false) {
		if (isset($this->anons[$key])) {
			$data = parent::get($this->anons[$key], $getReal);
			if (empty($data)) {
				return null;
			}
			$data['anonymous'] = true;
		} else {
			$data = parent::get($key, $getReal);
			if (empty($data)) {
				return null;
			}
			$data['anonymous'] = false;
		}

		if (!empty($data['user-name']) and !isset($this->anons[$data['anon-name']])) {
			$this->anons[$data['anon-name']] = $data['user-name'];
			file_put_contents($this->anonsFile, json_encode($this->anons), LOCK_EX);
		}

		return $data;
	}

	public function put($key, &$data) {
		$this->anons[$data['anon-name']] = $data['user-name'];
		file_put_contents($this->anonsFile, json_encode($this->anons), LOCK_EX);
		return parent::put($key, $data);
	}

}
