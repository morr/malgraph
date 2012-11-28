<?php
class MGHelper extends ChibiHelper {
	public function currentUrl() {
		return 'http://' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . str_replace('&', '&amp;', $_SERVER['REQUEST_URI']));
	}

	public function subTypeText($subType) {
		switch ($subType) {
			case AnimeModel::ENTRY_SUBTYPE_OVA: return 'OVA';
			case AnimeModel::ENTRY_SUBTYPE_ONA: return 'ONA';
			case AnimeModel::ENTRY_SUBTYPE_TV: return 'TV';
			case AnimeModel::ENTRY_SUBTYPE_SPECIAL: return 'Special';
			case AnimeModel::ENTRY_SUBTYPE_MUSIC: return 'Music';
			case AnimeModel::ENTRY_SUBTYPE_MOVIE: return 'Movie';
			case MangaModel::ENTRY_SUBTYPE_MANGA: return 'Manga';
			case MangaModel::ENTRY_SUBTYPE_MANHWA: return 'Manhwa';
			case MangaModel::ENTRY_SUBTYPE_NOVEL: return 'Novel';
			case MangaModel::ENTRY_SUBTYPE_ONESHOT: return 'One shot';
			default: throw new Exception('Unknown type: ' . $subType);
		}
	}

	public function statusText($status, $type = null) {
		if ($type === null) {
			$type = $this->view->am;
		}
		switch ($status) {
			case UserModel::USER_LIST_STATUS_PLANNED: return 'Planned';
			case UserModel::USER_LIST_STATUS_DROPPED: return 'Dropped';
			case UserModel::USER_LIST_STATUS_COMPLETING: return $type == AMModel::ENTRY_TYPE_ANIME ? 'Watching' : 'Reading';
			case UserModel::USER_LIST_STATUS_ONHOLD: return 'On-hold';
			case UserModel::USER_LIST_STATUS_COMPLETED: return 'Completed';
			default: throw new Exception('Unknown status: ' . $status);
		}
	}

	public function amText($type = null) {
		if ($type === null) {
			$type = $this->view->am;
		}
		switch ($type) {
			case AMModel::ENTRY_TYPE_ANIME: return 'anime';
			case AMModel::ENTRY_TYPE_MANGA: return 'manga';
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

		if (!empty($this->config->misc->mirrorDir)) {
			$mirrors = [];
			$nurls = $urls;
			foreach ($urls as $key => $url) {
				$mirror = rawurlencode($url);
				$mirror = implode(DIRECTORY_SEPARATOR, [$this->config->chibi->runtime->rootFolder, $this->config->misc->mirrorDir, $mirror . '.dat']);
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
		if ($this->config->misc->sendReferrer) {
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
			if (!empty($this->config->misc->mirrorDir)) {
				file_put_contents($mirrors[$key], $documents[$key]);
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
			$controllerName = $this->view->controllerName;
			if (empty($actionName)) {
				$actionName = $this->view->actionName;
			}
		}
		if ($controllerName == 'stats') {
			if (empty($actionName)) {
				$actionName = 'profile';
			}
			if (empty($userNames)) {
				$userNames = $this->view->userNames;
			}
			if (empty($am)) {
				$am = $this->view->am;
				if ($am != AMModel::ENTRY_TYPE_MANGA) {
					$am = AMModel::ENTRY_TYPE_ANIME;
				}
			}
			if (!is_array($userNames)) {
				$userNames = [$userNames];
			}
			$url = join(',', $userNames);
			if ($actionName != 'profile' or $am == AMModel::ENTRY_TYPE_MANGA) {
				$url .= '/';
				$url .= $actionName;
			}
			if ($am != AMModel::ENTRY_TYPE_ANIME) {
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
		return $this->urlHelper->htmlUrl($url, $get);
	}

}
