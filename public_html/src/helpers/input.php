<?php
class InputHelper {
	private static $allowPOST = true;
	private static $allowGET = true;
	private static $allowCookie = false;

	public static function allowPost($x) { self::$allowPOST = $x; }
	public static function allowGET($x) { self::$allowGET = $x; }
	public static function allowCookie($x) { self::$allowCookie = $x; }

	public static function get($key) {
		if (self::$allowPOST and isset($_POST[$key])) {
			return $_POST[$key];
		} elseif (self::$allowGET and isset($_GET[$key])) {
			return $_GET[$key];
		} elseif (self::$allowCookie and isset($_COOKIE[$key])) {
			return $_COOKIE[$key];
		}
		return null;
	}

	public function getStringSafe($key) {
		$value = $this->get($key);
		if (is_array($value)) {
			return array_map('htmlentities', $value);
		}
		return htmlentities($value);
	}

	public function getInt($key) {
		return intval($this->get($key));
	}
}
