<?php
class MGHelper extends ChibiHelper {
	public function currentUrl() {
		return 'http://' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI']);
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
		if ($this->config->misc->useMirror) {
			$mirror = unpack('H*', $url);
			$mirror = array_shift($mirror);
			$mirror = $this->config->chibi->runtime->rootFolder . '/mirror/' . $mirror . '.dat';
			if (file_exists($mirror)) {
				return file_get_contents($mirror);
			}
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

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

		curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
		//curl_setopt($ch, CURLOPT_TIMEOUT, 1);
		$contents = curl_exec($ch);

		$contents = mb_convert_encoding($contents, 'HTML-ENTITIES', 'UTF-8');

		if (curl_errno($ch)) {
			$e = curl_error($ch);
			curl_close($ch);
			throw new Exception($e);
			//return null;
		} else {
			curl_close($ch);
		}

		if ($this->config->misc->useMirror) {
			file_put_contents($mirror, $contents);
		}
		return $contents;
	}

	public function suppressErrors() {
		set_error_handler(function($errno, $errstr, $errfile, $errline) {});
	}
	public function restoreErrors() {
		restore_error_handler();
	}


	public function url($s, array $p = []) {
		return str_replace('&', '&amp;', $this->urlHelper->url($s, $p));
	}
}
