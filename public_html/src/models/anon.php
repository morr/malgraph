<?php
class AnonService {
	private static $lookupTable = [
		'u2a' => [],
		'a2u' => [],
	];
	private static $lookupTableFile = null;

	public static function init() {
		self::$lookupTableFile = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->anonLookupFile;
		if (file_exists(self::$lookupTableFile)) {
			self::$lookupTable = json_decode(file_get_contents(self::$lookupTableFile), true);
		}
	}

	public static function getByAnonName($anonName) {
		if (!isset(self::$lookupTable['a2u'][$anonName])) {
			return null;
		}
		return self::$lookupTable['a2u'][$anonName];
	}

	public static function getByUserName($userName) {
		if (!isset(self::$lookupTable['u2a'][$userName])) {
			return null;
		}
		return self::$lookupTable['u2a'][$userName];
	}

	public static function setPair($userName, $anonName) {
		if ($anonName === null) {
			unset(self::$lookupTable['u2a'][$userName]);
			unset(self::$lookupTable['a2u'][$anonName]);
		} else {
			self::$lookupTable['u2a'][$userName] = $anonName;
			self::$lookupTable['a2u'][$anonName] = $userName;
		}
		file_put_contents(self::$lookupTableFile, json_encode(self::$lookupTable), LOCK_EX);
	}
}
AnonService::init();
