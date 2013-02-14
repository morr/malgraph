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


	public function textAM($type = null) {
		if ($type === null) {
			$type = ChibiRegistry::getView()->am;
		}
		switch ($type) {
			case AMModel::TYPE_ANIME: return 'anime';
			case AMModel::TYPE_MANGA: return 'manga';
		}
		throw new InvalidAMTypeException($type);
	}

	public function textSubType($subType, $number = 1) {
		$plural = $number > 1;
		switch ($subType) {
			case AnimeEntry::SUBTYPE_OVA: return $plural ? 'OVAs' : 'OVA';
			case AnimeEntry::SUBTYPE_ONA: return $plural ? 'ONAs' : 'ONA';
			case AnimeEntry::SUBTYPE_TV: return 'TV';
			case AnimeEntry::SUBTYPE_SPECIAL: return $plural ? 'specials' : 'special';
			case AnimeEntry::SUBTYPE_MUSIC: return 'music';
			case AnimeEntry::SUBTYPE_MOVIE: return $plural ? 'movies' : 'movie';
			case MangaEntry::SUBTYPE_MANGA: return 'manga';
			case MangaEntry::SUBTYPE_MANHWA: return 'manhwa';
			case MangaEntry::SUBTYPE_MANHUA: return 'manhua';
			case MangaEntry::SUBTYPE_DOUJIN: return $plural ? 'doujinshi' : 'doujin';
			case MangaEntry::SUBTYPE_NOVEL: return $plural ? 'novels' : 'novel';
			case MangaEntry::SUBTYPE_ONESHOT: return $plural ? 'one shots' : 'one shot';
			case '': return 'Unknown';
			default: throw new Exception('Unknown type: ' . $subType);
		}
	}

	public function textVolumes($number, $short = false, $fmt = '%s %s') {
		$txt = $short ? 'vol' : 'volume';
		if ($number == 0) {
			$number = '?';
			$txt .= 's';
		} elseif ($number > 1) {
			$txt .= 's';
		}
		return sprintf($fmt, $number, $txt);
	}

	public function textChapters($number, $short = false, $fmt = '%s %s') {
		$txt = $short ? 'chap' : 'chapter';
		if ($number == 0) {
			$number = '?';
			$txt .= 's';
		} elseif ($number > 1) {
			$txt .= 's';
		}
		return sprintf($fmt, $number, $txt);
	}

	public function textEpisodes($number, $short = false, $fmt = '%s %s') {
		$txt = $short ? 'ep' : 'episode';
		if ($number == 0) {
			$number = '?';
			$txt .= 's';
		} elseif ($number > 1) {
			$txt .= 's';
		}
		return sprintf($fmt, $number, $txt);
	}

	public function textStatus($status, $type = null) {
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


	public function headerLink($user, $text, $forcePlural = false) {
		$view = ChibiRegistry::getView();
		if (count($view->users) > 1 or $forcePlural) {
			$html = '<a href="' . $this->constructUrl(null, null, [], $user->getLinkableName()) . '">';
			$html .= $user->getPublicName();
			$html .= '</a>&rsquo;s ' . lcfirst($text);
		} else {
			$html = ucfirst($text);
		}
		return $html;
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
		$str = trim(str_replace('  ', ' ', $str));
		$monthNames = array_merge(
			array_flip([1 => 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']),
			array_flip([1 => 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'])
		);
		$day = null;
		$month = null;
		$year = null;
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $str, $matches)) {
			list(, $year, $month, $day) = $matches;
		} elseif (preg_match('/^(\w{3,}) (\d{1,2})(\w*,?) (\d{4})$/', $str, $matches)) {
			list (, $month, $day, , $year) = $matches;
		} elseif (preg_match('/^(\d{1,2})(\w*) (\w{3,}),? (\d{4})$/', $str, $matches)) {
			list (, $day, , $month, $year) = $matches;
		} elseif (preg_match('/^(\d{4}),? (\w{3,}),? (\d{1,2})(\w*)$/', $str, $matches)) {
			list (, $year, $month, $day) = $matches;
		} elseif (preg_match('/^(\w{3,}),? (\d{4})$/', $str, $matches)) {
			$month = $matches[1];
			$year = $matches[2];
		} elseif (preg_match('/^(\d{4})$/', $str, $matches)) {
			$year = $matches[1];
		}

		if (!($month >= 1 and $month <= 12)) {
			if (isset($monthNames[strtolower($month)])) {
				$month = $monthNames[strtolower($month)];
			} else {
				$month = null;
			}
		}
		$year = intval($year);
		$day = intval($day);

		if (!$year) {
			return '?';
		}
		if (!$month) {
			return sprintf('%04d-?-?', $year);
		}
		if (!$day) {
			return sprintf('%04d-%02d-?', $year, $month);
		}
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
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

	private function parseDownloadResult($result) {
		$pos = strpos($result, "\r\n\r\n");
		$headerLines = substr($result, 0, $pos);
		$contents = substr($result, $pos + 4);
		$headers = [];
		$headerLines = explode("\r\n", $headerLines);
		array_shift($headerLines);
		foreach ($headerLines as $line) {
			list($key, $value) = explode(': ', $line);
			if (!isset($headers[$key])) {
				$headers[$key] = $value;
			} elseif (is_array($headers[$key])) {
				$headers[$key] []= $value;
			} else {
				$headers[$key] = [$headers[$key]];
				$headers[$key] []= $value;
			}
		}

		$contents = '<?xml encoding="utf-8" ?'.'>' . $contents;
		//別ハックは、	Another hack
		//私は静かに	makes me
		//泣きます		quietly weep
		return [$headers, $contents];
	}

	public function downloadMulti(array $urls) {
		$documents = [];

		if (ChibiConfig::getInstance()->misc->mirrorEnabled) {
			$mirrors = [];
			$nurls = $urls;
			foreach ($urls as $key => $url) {
				$mirror = rawurlencode($url);
				$mirror = implode(DIRECTORY_SEPARATOR, [ChibiConfig::getInstance()->chibi->runtime->rootFolder, ChibiConfig::getInstance()->misc->mirrorDir, $mirror . '.dat']);
				$mirrors[$key] = $mirror;
				if (file_exists($mirror)) {
					$result = file_get_contents($mirror);
					$documents[$key] = self::parseDownloadResult($result);
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
			curl_setopt($ch, CURLOPT_HEADER, 1);
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
			$result = curl_multi_getcontent($ch);
			$documents[$key] = self::parseDownloadResult($result);
			if (ChibiConfig::getInstance()->misc->mirrorEnabled) {
				file_put_contents($mirrors[$key], $result);
			}
		}
		curl_multi_close($multicurl);

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
			if ($actionName != 'profile') {
				$url .= '/';
				$url .= $actionName;
				if ($am != AMModel::TYPE_ANIME) {
					$url .= ',';
					$url .= $am;
				}
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



	public static function loadJSON($path) {
		$contents = file_get_contents($path);
		$contents = preg_replace('/#(.*)$/m', '', $contents);
		return json_decode($contents, true);
	}



	public static function getMemoryUsage() {
		$unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
		$size = memory_get_usage(true);
		$i = floor(log($size, 1024));
		return sprintf('%.02f' . $unit[$i], $size / pow(1024, $i), 2);
	}
}
