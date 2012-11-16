<?php
require_once 'abstract.php';
class UserModel extends JSONDB {
	const USER_GENDER_FEMALE = 'F';
	const USER_GENDER_MALE = 'M';
	const USER_GENDER_UNKNOWN = '?';

	const USER_LIST_TYPE_ANIME = 'anime';
	const USER_LIST_TYPE_MANGA = 'manga';

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

	const USER_URL_ANIME1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all';
	const USER_URL_MANGA1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all&type=manga';
	const USER_URL_ANIME2 = 'http://myanimelist.net/animelist/{user}&sclick=1';
	const USER_URL_MANGA2 = 'http://myanimelist.net/mangalist/{user}&sclick=1';
	const USER_URL_PROFILE = 'http://myanimelist.net/profile/{user}';
	const USER_URL_CLUBS = 'http://myanimelist.net/showclubs.php?id={user-id}';
	const USER_URL_FRIENDS = 'http://myanimelist.net/friends.php?id={user-id}&show={shift}';

	private $anonsFile;
	private $anons;

	public function __construct() {
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'users';
		$this->anonsFile = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . 'anons.json';
		if (file_exists($this->anonsFile)) {
			$this->anons = json_decode(file_get_contents($this->anonsFile), true);
		} else {
			$this->anons = [];
		}
	}

	private $allowUpdate = false;
	public function allowUpdate($a) {
		$this->allowUpdate = $a;
	}

	public function isFresh($data) {
		if ($this->allowUpdate) {
			return isset($data['expires']) and time() <= $data['expires'];
		}
		return true;
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

	protected function loadLists(array &$user) {
		$urls = [];

		$urls[self::USER_LIST_TYPE_ANIME] = $this->mgHelper->replaceTokens(self::USER_URL_ANIME2, ['user' => $user['user-name']]);
		$urls[self::USER_LIST_TYPE_MANGA] = $this->mgHelper->replaceTokens(self::USER_URL_MANGA2, ['user' => $user['user-name']]);
		foreach ($urls as $type => $url) {
			$user[$type] = [];

			$contents = $this->mgHelper->download($url);
			if (empty($contents)) {
				throw new DownloadException($url);
			}
			if (strpos($contents, 'This list has been made private by the owner') !== false) {
				$user[$type]['private'] = true;
			} else {
				$user[$type]['private'] = false;
			}
		}

		$urls[self::USER_LIST_TYPE_ANIME] = $this->mgHelper->replaceTokens(self::USER_URL_ANIME1, ['user' => $user['user-name']]);
		$urls[self::USER_LIST_TYPE_MANGA] = $this->mgHelper->replaceTokens(self::USER_URL_MANGA1, ['user' => $user['user-name']]);
		foreach ($urls as $type => $url) {
			$list = &$user[$type];

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$contents = $this->mgHelper->download($url);
			if (empty($contents)) {
				throw new DownloadException($url);
			}
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

				if ($type == self::USER_LIST_TYPE_ANIME) {
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


	protected function loadProfile(array &$user) {
		$this->mgHelper->suppressErrors();
		$url = $this->mgHelper->replaceTokens(self::USER_URL_PROFILE, ['user' => $user['user-name']]);
		$contents = $this->mgHelper->download($url);
		if (empty($contents)) {
			throw new DownloadException($url);
		}
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$doc->loadHTML($contents);
		$xpath = new DOMXPath($doc);
		$this->mgHelper->restoreErrors();

		//basic information
		$user[self::USER_LIST_TYPE_ANIME]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Anime List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user[self::USER_LIST_TYPE_MANGA]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Manga List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user['user-name'] = $this->mgHelper->fixText($xpath->query('//title')->item(0)->nodeValue);
		$user['user-name'] = substr($user['user-name'], 0, strpos($user['user-name'], '\'s Profile'));

		//anonymous name
		$anonName = crypt($user['user-name'], '$2a$' . $this->config->misc->anonStatsSalt);
		$user['anon-name'] = $anonName;

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

		$this->mgHelper->suppressErrors();
		$url = $this->mgHelper->replaceTokens(self::USER_URL_CLUBS, ['user-id' => $user['user-id']]);
		$contents = $this->mgHelper->download($url);
		if (empty($contents)) {
			throw new DownloadException($url);
		}
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$doc->loadHTML($contents);
		$xpath = new DOMXPath($doc);
		$this->mgHelper->restoreErrors();

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
			$this->mgHelper->suppressErrors();
			$url = $this->mgHelper->replaceTokens(self::USER_URL_FRIENDS, ['user-id' => $user['user-id'], 'shift' => $shift]);
			$contents = $this->mgHelper->download($url);
			if (empty($contents)) {
				throw new DownloadException($url);
			}
			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$doc->loadHTML($contents);
			$xpath = new DOMXPath($doc);
			$this->mgHelper->restoreErrors();

			preg_match('/ has (\d+) friends/', $contents, $results);
			$max = intval($results[1]);

			$nodes = $xpath->query('//table//div[contains(@style, \'margin\')]/a[contains(@href, \'profile\')]');
			foreach ($nodes as $node) {
				$user['friends'] []= $this->mgHelper->fixText($node->nodeValue);
			}

			$shift += $page;
		} while ($shift < $max);
	}



	public function getReal($userName) {
		$vips = ['fri', 'rr-', 'karhu', 'draconismarch', 'archein', 'kkukaa', 'izdubar', 'bigonanime', 'don_don_kun', 'imperialx', 'el_marco', 'navycherub', 'kfyatek'];
		$user = [];
		$user['user-name'] = $userName;
		$user['vip'] = in_array(strtolower($userName), $vips);
		$user['generated'] = time();
		$user['expires'] = time() + 3600 * 24;

		$this->loadLists($user);
		$this->loadProfile($user);

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
		return $data;
	}

	public function put($key, &$data) {
		$this->anons[$data['anon-name']] = $data['user-name'];
		file_put_contents($this->anonsFile, json_encode($this->anons), LOCK_EX);
		return parent::put($key, $data);
	}

}
