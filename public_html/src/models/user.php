<?php
require_once 'anon.php';
require_once 'abstract.php';
require_once 'user/entry.php';
require_once 'user/clubentry.php';
require_once 'user/friendentry.php';
require_once 'user/list.php';
require_once 'user/listentry.php';
require_once 'user/history.php';
require_once 'user/historyentry.php';

class UserModel extends AbstractModel {
	const URL_ANIME1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all';
	const URL_MANGA1 = 'http://myanimelist.net/malappinfo.php?u={user}&status=all&type=manga';
	const URL_ANIME2 = 'http://myanimelist.net/animelist/{user}&sclick=1';
	const URL_MANGA2 = 'http://myanimelist.net/mangalist/{user}&sclick=1';
	const URL_PROFILE = 'http://myanimelist.net/profile/{user}';
	const URL_HISTORY = 'http://myanimelist.net/history/{user}';
	const URL_CLUBS = 'http://myanimelist.net/showclubs.php?id={user-id}';
	const URL_FRIENDS = 'http://myanimelist.net/friends.php?id={user-id}&show={shift}';

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->userCacheDir;
	}

	protected function loadLists(UserEntry &$userEntry, array &$documents) {
		$userEntry->setList(AMModel::TYPE_ANIME, new UserAnimeList());
		$userEntry->setList(AMModel::TYPE_MANGA, new UserMangaList());

		foreach (AMModel::getTypes() as $type) {
			$list = $userEntry->getList($type);
			if ($type == AMModel::TYPE_ANIME) {
				$contents = $documents[self::URL_ANIME2];
			} else {
				$contents = $documents[self::URL_MANGA2];
			}

			if (strpos($contents, 'This list has been made private by the owner') !== false) {
				$list->setPrivate(true);
			} else {
				$list->setPrivate(false);
			}
		}

		foreach (AMModel::getTypes() as $type) {
			$list = $userEntry->getList($type);
			if ($type == AMModel::TYPE_ANIME) {
				$contents = $documents[self::URL_ANIME1];
			} else {
				$contents = $documents[self::URL_MANGA1];
			}

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
			$doc->loadHTML($contents);
			ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
			$xpath = new DOMXPath($doc);

			if ($xpath->query('//myinfo')->length == 0) {
				//user not found?
				throw new InvalidEntryException($userEntry->getUserName());
			}

			$nodes = $xpath->query('//anime | //manga');
			foreach ($nodes as $root) {
				$id = intval($xpath->query('series_animedb_id | series_mangadb_id', $root)->item(0)->nodeValue);
				$entry = UserListEntry::factory($list, $id);

				$node = $xpath->query('my_score', $root)->item(0);
				if (!empty($node)) {
					$entry->setScore(intval($node->nodeValue));
				}

				$malStatus = $xpath->query('my_status', $root)->item(0)->nodeValue;
				$status = UserListEntry::STATUS_UNKNOWN;
				switch ($malStatus) {
					case UserListEntry::STATUS_MAL_DROPPED: $status = UserListEntry::STATUS_DROPPED; break;
					case UserListEntry::STATUS_MAL_ONHOLD: $status = UserListEntry::STATUS_ONHOLD; break;
					case UserListEntry::STATUS_MAL_COMPLETING: $status = UserListEntry::STATUS_COMPLETING; break;
					case UserListEntry::STATUS_MAL_COMPLETED: $status = UserListEntry::STATUS_COMPLETED; break;
					case UserListEntry::STATUS_MAL_PLANNED: $status = UserListEntry::STATUS_PLANNED; break;
				}
				$entry->setStatus($status);
				$entry->setStartDate(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($xpath->query('my_start_date', $root)->item(0)->nodeValue));
				$entry->setFinishDate(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($xpath->query('my_finish_date', $root)->item(0)->nodeValue));

				if ($type == AMModel::TYPE_ANIME) {
					$entry->setCompletedEpisodes(intval($xpath->query('my_watched_episodes', $root)->item(0)->nodeValue));
				}
				else {
					$entry->setCompletedChapters(intval($xpath->query('my_read_chapters', $root)->item(0)->nodeValue));
					$entry->setCompletedVolumes(intval($xpath->query('my_read_volumes', $root)->item(0)->nodeValue));
				}

				$list->addEntry($entry);
			}

			$list->setTimeSpent(floatval($xpath->query('//user_days_spent_watching')->item(0)->nodeValue));
		}
	}

	protected function loadProfile(UserEntry &$userEntry, array &$documents) {
		$contents = $documents[self::URL_PROFILE];

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
		$doc->loadHTML($contents);
		ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
		$xpath = new DOMXPath($doc);

		$userData = $userEntry->getUserData();

		//basic information
		$userEntry->getAnimeList()->setViewCount(intval(str_replace(',', '', $xpath->query('//td[text() = \'Anime List Views\']/following-sibling::td')->item(0)->nodeValue)));
		$userEntry->getMangaList()->setViewCount(intval(str_replace(',', '', $xpath->query('//td[text() = \'Manga List Views\']/following-sibling::td')->item(0)->nodeValue)));
		$tmp = ChibiRegistry::getInstance()->getHelper('mg')->fixText($xpath->query('//title')->item(0)->nodeValue);
		$userEntry->setUserName(substr($tmp, 0, strpos($tmp, '\'s Profile')));

		//static information
		$userData->setProfilePictureURL($xpath->query('//td[@class = \'profile_leftcell\']//img')->item(0)->getAttribute('src'));
		$userData->setJoinDate(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($xpath->query('//td[text() = \'Join Date\']/following-sibling::td')->item(0)->nodeValue));
		$url = ChibiRegistry::getInstance()->getHelper('mg')->parseURL($xpath->query('//a[text() = \'All Comments\']')->item(0)->getAttribute('href'));
		$userEntry->setID(intval($url['query']['id']));

		//comments
		$node = $xpath->query('//td[text() = \'Comments\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$userData->setCommentCount(intval($node->nodeValue));
		}

		//posts
		$node = $xpath->query('//td[text() = \'Forum Posts\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$userData->setPostCount(intval($node->nodeValue));
		}

		//dynamic information
		$node = $xpath->query('//td[text() = \'Birthday\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$userData->setBirthday(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($node->nodeValue));
		}

		$node = $xpath->query('//td[text() = \'Location\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$userData->setLocation(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
		}

		$node = $xpath->query('//td[text() = \'Website\']/following-sibling::td')->item(0);
		if (!empty($node)) {
			$userData->setWebsite(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
		}


		$gender = $xpath->query('//td[text() = \'Gender\']/following-sibling::td')->item(0)->nodeValue;
		switch($gender) {
			case 'Female': $userData->setGender(UserData::GENDER_FEMALE); break;
			case 'Male': $userData->setGender(UserData::GENDER_MALE); break;
		}

		$userEntry->resetClubs();
		$node = $xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Clubs\')]')->item(0);
		if (!empty($node)) {
			$clubCount = intval(substr($node->nodeValue, 13));
			if ($clubCount <= 15) {
				$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'/club\')]');
				foreach ($q as $node) {
					$url = ChibiRegistry::getInstance()->getHelper('mg')->parseURL($node->getAttribute('href'));
					$club = new UserClubEntry();
					$club->setID(intval($url['query']['cid']));
					$club->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
					$userEntry->addClub($club);
				}
			} else {
				$this->loadClubs($userEntry);
			}
		}

		$userEntry->resetFriends();
		$node = $xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Friends\')]')->item(0);
		if (!empty($node)) {
			$friendCount = intval(substr($node->nodeValue, 15));
			if ($friendCount <= 30) {
				$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'profile\')]');
				foreach ($q as $node) {
					$friend = new UserFriendEntry();
					$friend->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
					$userEntry->addFriend($friend);
				}
			} else {
				$this->loadFriends($userEntry);
			}
		}
	}


	protected function loadClubs(UserEntry &$userEntry) {
		$userEntry->resetClubs();
		$url = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_CLUBS, ['user-id' => $userEntry->getID()]);
		$contents = ChibiRegistry::getInstance()->getHelper('mg')->download($url);
		if (empty($contents)) {
			throw new DownloadException($url);
		}

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
		$doc->loadHTML($contents);
		ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
		$xpath = new DOMXPath($doc);

		$nodes = $xpath->query('//ol//li//a');
		foreach ($nodes as $node) {
			$url = ChibiRegistry::getInstance()->getHelper('mg')->parseURL($node->getAttribute('href'));
			$club = new UserClubEntry();
			$club->setID(intval($url['query']['cid']));
			$club->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
			$userEntry->addClub($club);
		}
	}

	protected function loadFriends(UserEntry &$userEntry) {
		$userEntry->resetFriends();
		$max = 0;
		$shift = 0;
		$page = 6 * 7;
		do {
			$url = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_FRIENDS, ['user-id' => $userEntry->getID(), 'shift' => $shift]);
			$contents = ChibiRegistry::getInstance()->getHelper('mg')->download($url);
			if (empty($contents)) {
				throw new DownloadException($url);
			}

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
			$doc->loadHTML($contents);
			ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
			$xpath = new DOMXPath($doc);

			preg_match('/ has (\d+) friends/', $contents, $results);
			$max = intval($results[1]);

			$nodes = $xpath->query('//table//div[contains(@style, \'margin\')]/a[contains(@href, \'profile\')]');
			foreach ($nodes as $node) {
				$friend = new UserFriendEntry();
				$friend->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
				$userEntry->addFriend($friend);
			}

			$shift += $page;
		} while ($shift < $max);
	}

	protected function loadHistory(UserEntry &$userEntry, array &$documents) {
		$contents = $documents[self::URL_HISTORY];

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
		$doc->loadHTML($contents);
		ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
		$xpath = new DOMXPath($doc);

		$nodes = $xpath->query('//table//td[@class = \'borderClass\']/..');
		$userEntry->setHistory(AMModel::TYPE_ANIME, new UserAnimeHistory());
		$userEntry->setHistory(AMModel::TYPE_MANGA, new UserMangaHistory());

		foreach ($nodes as $node) {
			//basic info
			$link = $node->childNodes->item(0)->childNodes->item(0)->getAttribute('href');
			$sub = intval($node->childNodes->item(0)->childNodes->item(2)->nodeValue);
			preg_match('/(\d+)\/?$/', $link, $matches);
			$id = intval($matches[0]);
			if (strpos($link, 'manga') !== false) {
				$entry = UserHistoryEntry::factory($userEntry->getMangaHistory(), $id);
				$entry->setChapter($sub);
			} elseif (strpos($link, 'anime') !== false) { //risky
				$entry = UserHistoryEntry::factory($userEntry->getAnimeHistory(), $id);
				$entry->setEpisode($sub);
			} else {
				throw new Exception('Unknown history entry type');
			}

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
			$entry->setTimestamp($time);
			$userEntry->getHistory($entry->getType())->addEntry($entry);
		}
	}


	public function getReal($userName) {
		$userEntry = $this->getCached($userName);
		if (empty($userEntry)) {
			$userEntry = new UserEntry($userName);
		}

		$userEntry->setGenerationTime(time());
		if ($userEntry->getUserData()->isVIP()) {
			$userEntry->setExpirationTime(time() + 3600 * 3);
		} else {
			$userEntry->setExpirationTime(time() + 3600 * 24);
		}

		if ($userEntry->getUserData()->isBlocked()) {
			return $userEntry;
		}

		$urls = [];
		$urls[self::URL_ANIME1] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_ANIME1, ['user' => $userEntry->getUserName()]);
		$urls[self::URL_MANGA1] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_MANGA1, ['user' => $userEntry->getUserName()]);
		$urls[self::URL_ANIME2] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_ANIME2, ['user' => $userEntry->getUserName()]);
		$urls[self::URL_MANGA2] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_MANGA2, ['user' => $userEntry->getUserName()]);
		$urls[self::URL_PROFILE] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_PROFILE, ['user' => $userEntry->getUserName()]);
		$urls[self::URL_HISTORY] = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL_HISTORY, ['user' => $userEntry->getUserName()]);

		$documents = ChibiRegistry::getInstance()->getHelper('mg')->downloadMulti($urls);
		foreach ($documents as $type => $contents) {
			if (empty($contents)) {
				throw new DownloadException($urls[$type]);
			}
		}

		$this->loadLists($userEntry, $documents);
		$this->loadProfile($userEntry, $documents);
		$this->loadHistory($userEntry, $documents);
		return $userEntry;
	}

	public function get($key, $cachePolicy = self::CACHE_POLICY_DEFAULT) {
		if (AnonService::getByAnonName($key)) {
			$userEntry = parent::get(AnonService::getByAnonName($key), $cachePolicy);
			if (empty($userEntry)) {
				return null;
			}
			$userEntry->setAnonymous(true);
		} else {
			$userEntry = parent::get($key, $cachePolicy);
			if (empty($userEntry)) {
				return null;
			}
			$userEntry->setAnonymous(false);
		}

		return $userEntry;
	}

}
