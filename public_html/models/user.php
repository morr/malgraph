<?php
require 'db.php';
class UserModel extends JSONDB {
	const USER_STATUS_OK = 1;
	const USER_STATUS_NOT_FOUND = 3;

	const USER_GENDER_FEMALE = 'F';
	const USER_GENDER_MALE = 'M';
	const USER_GENDER_UNKNOWN = '?';

	const USER_LIST_STATUS_OK = 1;

	const USER_LIST_TYPE_ANIME = 'anime';
	const USER_LIST_TYPE_MANGA = 'manga';

	const USER_LIST_STATUS_DROPPED = 'dropped';
	const USER_LIST_STATUS_ONHOLD = 'onhold';
	const USER_LIST_STATUS_COMPLETING = 'completing';
	const USER_LIST_STATUS_COMPLETED = 'completed';
	const USER_LIST_STATUS_PLANNED = 'planned';

	const USER_URL_ANIME = 1;
	const USER_URL_MANGA = 2;
	const USER_URL_PROFILE = 3;

	private $freshTime = 600;

	private $errorHandler;
	private function suppressErrors() {
		$this->errorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {});
	}
	private function restoreErrors() {
		set_error_handler($this->errorHandler);
	}


	public function __construct() {
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'users';
	}

	public function isFresh($data) {
		if (time() - $data['generated'] <= $this->freshTime)
			return true;
		return false;
	}

	public function getReal($userName) {
		$urls = array(
			self::USER_URL_ANIME => 'http://myanimelist.net/malappinfo.php?u=' . $userName . '&status=all',
			self::USER_URL_MANGA => 'http://myanimelist.net/malappinfo.php?u=' . $userName . '&status=all&type=manga',
			self::USER_URL_PROFILE => 'http://myanimelist.net/profile/' . $userName
		);
		$curls = array();
		$multicurl = curl_multi_init();
		foreach($urls as $key => $url) {
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_multi_add_handle($multicurl, $curl);
			$curls[$key] = $curl;
		}

		$active = null;
		do {
			$mrc = curl_multi_exec($multicurl, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($multicurl) != -1) {
				do {
					$mrc = curl_multi_exec($multicurl, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}
		foreach($curls as $curl) {
			curl_multi_remove_handle($multicurl, $curl);
		}
		curl_multi_close($multicurl);




		$vips = array('fri', 'chrupky', 'karhu', 'draconismarch', 'archein', 'kkukaa', 'izdubar', 'bigonanime', 'don_don_kun', 'imperialx', 'el_marco', 'navycherub', 'kfyatek');
		$user = array();
		$user['userName'] = $userName; // fill something for starters... if script exits early, we'll have at least this much
		$user['vip'] = in_array(strtolower($userName), $vips);

		$contents[self::USER_LIST_TYPE_ANIME] = curl_multi_getcontent($curls[self::USER_URL_ANIME]);
		$contents[self::USER_LIST_TYPE_MANGA] = curl_multi_getcontent($curls[self::USER_URL_MANGA]);
		foreach (array(self::USER_LIST_TYPE_ANIME, self::USER_LIST_TYPE_MANGA) as $type) {
			$user[$type] = array();
			$list = &$user[$type];

			$doc = new DOMDocument;
			$doc->preserveWhiteSpace = false;
			$this->suppressErrors();
			$doc->loadHTML($contents[$type]);
			$this->restoreErrors();
			$xpath = new DOMXPath($doc);

			if ($xpath->query('//myinfo')->length == 0) {
				$user['status'] = self::USER_STATUS_NOT_FOUND;
				return $user;
			}

			$list[self::USER_LIST_STATUS_COMPLETED] = array();
			$list[self::USER_LIST_STATUS_COMPLETING] = array();
			$list[self::USER_LIST_STATUS_ONHOLD] = array();
			$list[self::USER_LIST_STATUS_PLANNED] = array();
			$list[self::USER_LIST_STATUS_DROPPED] = array();

			$nodes = $xpath->query('//anime | //manga');
			foreach($nodes as $node) {
				$entry = array();
				$entry['id'] = intval($xpath->query('series_animedb_id | series_mangadb_id', $node)->item(0)->nodeValue);
				$entry['score'] = intval($xpath->query('my_score', $node)->item(0)->nodeValue);
				$entry['status'] = $xpath->query('my_status', $node)->item(0)->nodeValue;
				$entry['startDate'] = $this->mgHelper->fixDate($xpath->query('my_start_date', $node)->item(0)->nodeValue);
				$entry['finishDate'] = $this->mgHelper->fixDate($xpath->query('my_finish_date', $node)->item(0)->nodeValue);
				if ($type == self::USER_LIST_TYPE_ANIME) {
					$entry['episodesCompleted'] = intval($xpath->query('my_watched_episodes', $node)->item(0)->nodeValue);
				}
				else {
					$entry['chaptersCompleted'] = intval($xpath->query('my_read_chapters', $node)->item(0)->nodeValue);
					$entry['volumesCompleted'] = intval($xpath->query('my_read_volumes', $node)->item(0)->nodeValue);
				}
				$list[$entry['status']] []= $entry;
			}

			$list['timeSpent'] = floatval($xpath->query('//user_days_spent_watching')->item(0)->nodeValue);
			$list['status'] = self::USER_STATUS_OK;
		}



		$contents = curl_multi_getcontent($curls[self::USER_URL_PROFILE]);
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$this->suppressErrors();
		$doc->loadHTML($contents);
		$xpath = new DOMXPath($doc);

		$user[self::USER_LIST_TYPE_ANIME]['listViews'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Anime List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user[self::USER_LIST_TYPE_MANGA]['listViews'] = intval(str_replace(',', '', $xpath->query('//td[text() = \'Manga List Views\']/following-sibling::td')->item(0)->nodeValue));
		$user['userName'] = $xpath->query('//title')->item(0)->nodeValue;
		$user['userName'] = substr($user['userName'], 0, strpos($user['userName'], '\'s Profile'));

		$user['birthday'] = $this->mgHelper->fixDate($xpath->query('//td[text() = \'Birthday\']/following-sibling::td')->item(0)->nodeValue);
		$user['location'] = $xpath->query('//td[text() = \'Location\']/following-sibling::td')->item(0)->nodeValue;
		$user['website'] = $xpath->query('//td[text() = \'Website\']/following-sibling::td')->item(0)->nodeValue;
		$user['commentCount'] = intval($xpath->query('//td[text() = \'Comments\']/following-sibling::td')->item(0)->nodeValue);
		$user['postCount'] = intval($xpath->query('//td[text() = \'Forum Posts\']/following-sibling::td')->item(0)->nodeValue);
		$user['profilePic'] = $xpath->query('//td[@class = \'profile_leftcell\']//img')->item(0)->getAttribute('src');
		$user['totalFriends'] = intval(substr($xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Friends\')]')->item(0)->nodeValue, 15));
		$user['totalClubs'] = intval(substr($xpath->query('//div[@class = \'spaceit_pad\'][contains(text(), \'Total Clubs\')]')->item(0)->nodeValue, 13));
		$user['joinDate'] = $this->mgHelper->fixDate($xpath->query('//td[text() = \'Join Date\']/following-sibling::td')->item(0)->nodeValue);
		$this->restoreErrors();

		$gender = $xpath->query('//td[text() = \'Gender\']/following-sibling::td')->item(0)->nodeValue;
		switch($gender) {
			case 'Female': $user['gender'] = self::USER_GENDER_FEMALE; break;
			case 'Male': $user['gender'] = self::USER_GENDER_MALE; break;
			case 'Not specified': $user['gender'] = self::USER_GENDER_UNKNOWN; break;
		}

		$user['friends'] = array();
		$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'profile\')]');
		foreach ($q as $item) {
			$user['friends'] []= array (
				'name' => $item->nodeValue,
				'link' => $item->getAttribute('href')
			);
		}

		$user['clubs'] = array();
		$q = $xpath->query('//td[@class = \'profile_leftcell\']//a[contains(@href, \'/club\')]');
		foreach ($q as $item) {
			$user['clubs'] []= array (
				'name' => $item->nodeValue,
				'link' => $item->getAttribute('href')
			);
		}

		$user['status'] = self::USER_STATUS_OK;
		return $user;
	}
}
