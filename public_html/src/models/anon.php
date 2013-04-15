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
			$path = self::$lookupTableFile;
			$f = fopen($path, 'rb');
			if (!$f) {
				$contents = '[]';
			} else {
				if (flock($f, LOCK_SH)) {
					$contents = file_get_contents($path);
					flock($f, LOCK_UN);
					fclose($f);
				} else {
					fclose($f);
					throw new LockException($path);
				}
			}
			self::$lookupTable = json_decode($contents, true);
		}
	}

	public static function getByAnonName($anonName) {
		if (!isset(self::$lookupTable['a2u'][strtolower($anonName)])) {
			return null;
		}
		return self::$lookupTable['a2u'][strtolower($anonName)];
	}

	public static function getByUserName($userName) {
		if (!isset(self::$lookupTable['u2a'][strtolower($userName)])) {
			return null;
		}
		return self::$lookupTable['u2a'][strtolower($userName)];
	}

	public static function setPair($userName, $anonName) {
		if ($anonName === null) {
			unset(self::$lookupTable['u2a'][strtolower($userName)]);
		} else {
			self::$lookupTable['u2a'][strtolower($userName)] = strtolower($anonName);
			self::$lookupTable['a2u'][strtolower($anonName)] = strtolower($userName);
		}
		$path = self::$lookupTableFile;
		$contents = json_encode(self::$lookupTable);
		$f = fopen($path, 'cb');
		if (!$f) {
			throw new LockException($path);
		}
		if (flock($f, LOCK_EX)) {
			fwrite($f, $contents);
			flock($f, LOCK_UN);
			fclose($f);
		} else {
			fclose($f);
			throw new LockException();
		}
	}
}
AnonService::init();
