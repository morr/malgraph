<?php
class MGHelper extends ChibiHelper {
	public static $descSuffix = ' on MALgraph, an online tool that extends your MyAnimeList profile.'; //suffix for <meta> description tag

	public function log($message) {
		$ip = null;
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if ((!$ip) or (!filter_var($ip, FILTER_VALIDATE_IP))) {
			$ip = 'unknown';
		}
		$args = array (
			"%s | %-15s | %-50s | %s\n",
			date('Y-m-d H:i:s'),
			$ip,
			gethostbyaddr($ip),
			$message
		);
		$message = call_user_func_array('sprintf', $args);
		error_log($message, 3, ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/' . ChibiConfig::getInstance()->misc->logFile);
	}

	public function currentUrl() {
		return 'http://' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . str_replace('&', '&amp;', $_SERVER['REQUEST_URI']));
	}

	public function subTypeText($subType) {
		switch ($subType) {
			case AnimeEntry::SUBTYPE_OVA: return 'OVA';
			case AnimeEntry::SUBTYPE_ONA: return 'ONA';
			case AnimeEntry::SUBTYPE_TV: return 'TV';
			case AnimeEntry::SUBTYPE_SPECIAL: return 'Special';
			case AnimeEntry::SUBTYPE_MUSIC: return 'Music';
			case AnimeEntry::SUBTYPE_MOVIE: return 'Movie';
			case MangaEntry::SUBTYPE_MANGA: return 'Manga';
			case MangaEntry::SUBTYPE_MANHWA: return 'Manhwa';
			case MangaEntry::SUBTYPE_MANHUA: return 'Manhua';
			case MangaEntry::SUBTYPE_DOUJIN: return 'Doujin';
			case MangaEntry::SUBTYPE_NOVEL: return 'Novel';
			case MangaEntry::SUBTYPE_ONESHOT: return 'One shot';
			case '': return 'Unknown';
			default: throw new Exception('Unknown type: ' . $subType);
		}
	}

	public function statusText($status, $type = null) {
		if ($type === null) {
			$type = ChibiRegistry::getView()->am;
		}
		switch ($status) {
			case UserListEntry::STATUS_PLANNED: return 'Planned';
			case UserListEntry::STATUS_DROPPED: return 'Dropped';
			case UserListEntry::STATUS_COMPLETING: return $type == AMModel::TYPE_ANIME ? 'Watching' : 'Reading';
			case UserListEntry::STATUS_ONHOLD: return 'On-hold';
			case UserListEntry::STATUS_COMPLETED: return 'Completed';
			default: throw new Exception('Unknown status: ' . $status);
		}
	}

	public function amText($type = null) {
		if ($type === null) {
			$type = ChibiRegistry::getView()->am;
		}
		switch ($type) {
			case AMModel::TYPE_ANIME: return 'anime';
			case AMModel::TYPE_MANGA: return 'manga';
		}
		return '?';
	}

	public function removeSpaces($subject) {
		$subject = trim($subject);
		$subject = rtrim($subject, ':');
		while(false !== ($x = strpos($subject, '  '))) {
			$subject = str_replace('  ', ' ', $subject);
		}
		return $subject;
	}

	public function fixText($str) {
		return $this->removeSpaces($str);
	}

	public function fixDate($str) {
		$str = str_replace('  ', ' ', $str);
		if($str == '?' or $str == 'Not available')
			return '?';
		$monthNames = [
			['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
			['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
		];
		$x = explode(' ', $str);
		if (count($x) == 3) {
			$month = trim($x[0], ',');
			$day = $x[1];
			$year = $x[2];
			foreach ($monthNames as $t) {
				$i = array_search($month, $t);
				if ($i !== false) {
					$month = $i + 1;
				}
			}
			return sprintf('%04d-%02d-%02d', $year, $month, $day);
		} elseif(count($x) == 2) {
			$month = trim($x[0], ',');
			$year = $x[1];
			foreach ($monthNames as $t) {
				$i = array_search($month, $t);
				if ($i !== false) {
					$month = $i + 1;
				}
			}
			return sprintf('%04d-%02d-?', $year, $month);
		} elseif (count($x) == 1 and intval($x[0]) > 0) {
			if (strpos($x[0], '-') !== false)
				return $x[0];
			$year = intval($x[0]);
			return sprintf('%04d-?-?', $year);
		}
		return '?';
	}

	public function parseURL($url) {
		$parts = parse_url($url);
		if (isset($parts['query'])) {
			parse_str(urldecode($parts['query']), $parts['query']);
		}
		return $parts;
	}

	public function replaceTokens($input, array $tokens) {
		$output = $input;
		foreach ($tokens as $key => $val) {
			$output = str_replace('{' . $key . '}', $val, $output);
		}
		return $output;
	}

	public function download($url) {
		$urls = [$url];
		$documents = $this->downloadMulti($urls);
		return $documents[0];
	}

	public function downloadMulti(array $urls) {
		$documents = [];

		if (!empty(ChibiConfig::getInstance()->misc->mirrorDir)) {
			$mirrors = [];
			$nurls = $urls;
			foreach ($urls as $key => $url) {
				$mirror = rawurlencode($url);
				$mirror = implode(DIRECTORY_SEPARATOR, [ChibiConfig::getInstance()->chibi->runtime->rootFolder, ChibiConfig::getInstance()->misc->mirrorDir, $mirror . '.dat']);
				$mirrors[$key] = $mirror;
				if (file_exists($mirror)) {
					$documents[$key] = file_get_contents($mirror);
					unset($nurls[$key]);
				}
			}
			$urls = $nurls;
		}

		$headers = [];
		$headers['Connection'] = 'close';
		$headers['User-Agent'] = 'Mozilla/5.0 (MALgraph crawler)';
		if (ChibiConfig::getInstance()->misc->sendReferrer) {
			$headers['Referer'] = 'http://' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI']);
		}

		$curlHeaders = [];
		foreach ($headers as $k => $v) {
			$k = str_replace(' ', '-', ucwords(str_replace('-', ' ', $k)));
			$curlHeaders []= $k . ': ' . $v;
		}

		$chs = [];
		$multicurl = curl_multi_init();
		foreach ($urls as $key => $url) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_AUTOREFERER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_multi_add_handle($multicurl, $ch);
			$chs[$key] = $ch;
		}

		$active = null;
		do {
			$mrc = curl_multi_exec($multicurl, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($multicurl) != -1) {
				do {
					$mrc = curl_multi_exec($multicurl, $active);
				}
				while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		foreach ($urls as $key => $url) {
			$ch = $chs[$key];
			curl_multi_remove_handle($multicurl, $ch);
			$documents[$key] = curl_multi_getcontent($ch);
			if (!empty(ChibiConfig::getInstance()->misc->mirrorDir)) {
				file_put_contents($mirrors[$key], $documents[$key]);
			}
		}
		curl_multi_close($multicurl);

		foreach (array_keys($documents) as $key) {
			$documents[$key] = '<?xml encoding="utf-8" ?>' . $documents[$key];
			//別ハックは、	Another hack
			//私は静かに	makes me
			//泣きます		quietly weep
		}

		return $documents;
	}




	public function suppressErrors() {
		set_error_handler(function($errno, $errstr, $errfile, $errline) {});
	}
	public function restoreErrors() {
		restore_error_handler();
	}


	public function constructUrl($controllerName = null, $actionName = null, array $get = [], $userNames = null, $am = null) {
		if (empty($controllerName)) {
			$controllerName = ChibiRegistry::getView()->controllerName;
			if (empty($actionName)) {
				$actionName = ChibiRegistry::getView()->actionName;
			}
		}
		if ($controllerName == 'stats') {
			if (empty($actionName)) {
				$actionName = 'profile';
			}
			if (empty($userNames)) {
				$userNames = ChibiRegistry::getView()->userNames;
			}
			if (empty($am) and !empty(ChibiRegistry::getView()->am)) {
				$am = ChibiRegistry::getView()->am;
			}
			if ($am != AMModel::TYPE_MANGA) {
				$am = AMModel::TYPE_ANIME;
			}
			if (!is_array($userNames)) {
				$userNames = [$userNames];
			}
			$url = join(',', $userNames);
			if ($actionName != 'profile' or $am == AMModel::TYPE_MANGA) {
				$url .= '/';
				$url .= $actionName;
			}
			if ($am != AMModel::TYPE_ANIME) {
				$url .= ',';
				$url .= $am;
			}
		} elseif ($controllerName == 'index') {
			if (empty($actionName)) {
				$actionName = 'index';
			}
			if ($actionName == 'index') {
				$url = '';
			} else {
				$url = 's/';
				$url .= $actionName;
			}
		} else {
			$url = '';
		}
		return UrlHelper::htmlUrl($url, $get);
	}

}
