<?php
class SessionHelper {
	private static $stopped = false;
	private static $oldData = [];
	private static $oldId = '';

	public static function stopped() {
		return self::$stopped;
	}

	/*public static function init() {
		if (!session_id()) {
			self::$stopped = true;
		}
	}*/

	public static function close() {
		if (!self::$stopped) {
			self::$stopped = true;
			self::$oldData = $_SESSION;
			self::$oldId = session_id();
			session_write_close();
		}
	}

	public static function restore() {
		if (self::$stopped) {
			ini_set('session.use_only_cookies', false);
			ini_set('session.use_cookies', false);
			ini_set('session.use_trans_sid', false);
			ini_set('session.cache_limiter', null);
			session_id(self::$oldId);
			session_start();
			$_SESSION = self::$oldData;
			self::$stopped = false;
		}
	}
}

#SessionHelper::init();
