<?php
require_once 'db.php';
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

	const USER_URL_ANIME = 'http://myanimelist.net/malappinfo.php?u={user}&status=all';
	const USER_URL_MANGA = 'http://myanimelist.net/malappinfo.php?u={user}&status=all&type=manga';
	const USER_URL_PROFILE = 'http://myanimelist.net/profile/{user}';
	const USER_URL_CLUBS = 'http://myanimelist.net/showclubs.php?id={user-id}';
	const USER_URL_FRIENDS = 'http://myanimelist.net/friends.php?id={user-id}&show={shift}';

	public function __construct() {
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'users';
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
		$urls[self::USER_LIST_TYPE_ANIME] = $this->mgHelper->replaceTokens(self::USER_URL_ANIME, ['user' => $user['user-name']]);
		$urls[self::USER_LIST_TYPE_MANGA] = $this->mgHelper->replaceTokens(self::USER_URL_MANGA, ['user' => $user['user-name']]);
		foreach ([self::USER_LIST_TYPE_ANIME, self::USER_LIST_TYPE_MANGA] as $type) {
			$user[$type] = [];
			$list = &$user[$type];

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$this->mgHelper->suppressErrors();
			$contents = $this->mgHelper->download($urls[$type]);
			$doc->loadHTML($contents);
			$this->mgHelper->restoreErrors();
			$xpath = new DOMXPath($doc);

			if ($xpath->query('//myinfo')->length == 0) {
				//user not found?
				throw new Exception('User doesn\'t exist');
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
					var_dump($entry);die;
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
		$contents = $this->mgHelper->download($this->mgHelper->replaceTokens(self::USER_URL_PROFILE, ['user' => $user['user-name']]));
		if (!$contents) {
			$this->mgHelper->restoreErrors();
			throw new Exception('User doesn\'t exist');
		}
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$doc->loadHTML($contents);
		$xpath = new DOMXPath($doc);

		$user[self::USER_LIST_TYPE_ANIME]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Anime List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user[self::USER_LIST_TYPE_MANGA]['list-views'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Manga List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user['user-name'] = $this->mgHelper->fixText($xpath->query('//title')->item(0)->nodeValue);
		$user['user-name'] = substr($user['user-name'], 0, strpos($user['user-name'], '\'s Profile'));

		$user['birthday'] = $this->mgHelper->fixDate($xpath->query('//td[text() = \'Birthday\']/following-sibling::td')->item(0)->nodeValue);
		$user['location'] = $this->mgHelper->fixText($xpath->query('//td[text() = \'Location\']/following-sibling::td')->item(0)->nodeValue);
		$user['website'] = $this->mgHelper->fixText($xpath->query('//td[text() = \'Website\']/following-sibling::td')->item(0)->nodeValue);
		$user['comment-count'] = intval($xpath->query('//td[text() = \'Comments\']/following-sibling::td')->item(0)->nodeValue);
		$user['post-count'] = intval($xpath->query('//td[text() = \'Forum Posts\']/following-sibling::td')->item(0)->nodeValue);
		$user['profile-picture-url'] = $xpath->query('//td[@class = \'profile_leftcell\']//img')->item(0)->getAttribute('src');
		$user['join-date'] = $this->mgHelper->fixDate($xpath->query('//td[text() = \'Join Date\']/following-sibling::td')->item(0)->nodeValue);
		$url = $this->mgHelper->parseURL($xpath->query('//a[text() = \'All Comments\']')->item(0)->getAttribute('href'));
		$user['user-id'] = intval($url['query']['id']);
		$this->mgHelper->restoreErrors();

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
		$contents = $this->mgHelper->download($this->mgHelper->replaceTokens(self::USER_URL_CLUBS, ['user-id' => $user['user-id']]));
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
			$contents = $this->mgHelper->download($this->mgHelper->replaceTokens(self::USER_URL_FRIENDS, ['user-id' => $user['user-id'], 'shift' => $shift]));
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

		try {
			$this->loadLists($user);
			$this->loadProfile($user);
		} catch (Exception $e) {
			return null;
		}

		$this->postGet($user);

		return $user;
	}

	public function getCached($userName) {
		$user = parent::getCached($userName);
		$this->postGet($user);
		return $user;
	}

	public function postGet(&$user) {
		if (!empty($user)) {
			$anonName = crypt($user['user-name'], '$2a$' . $this->config->misc->anonStatsSalt);
			$anonName = unpack('H*', $anonName);
			$anonName = array_shift($anonName);
			$user['anonymous-name'] = $anonName;
			$user['anonymous'] = $user['anonymous-name'] == $user['user-name'];
		}
		return $user;
	}

	public function put($userName, $data) {
		if (!empty($data) and !empty($data['anonymous'])) {
			$userName = $data['user-name'];
		}
		$ret = parent::put($userName, $data);
		$path1 = $this->keyToPath($userName);
		$path2 = dirname($path1);
		$path2 .= '/' . $data['anonymous-name'] . $this->suffix;
		symlink($path1, $path2);
	}


}
