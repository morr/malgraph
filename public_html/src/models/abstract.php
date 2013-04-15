<?php
class InvalidEntryException extends Exception {
	public function __construct($key, $msg = null) {
		parent::__construct('Invalid entry: ' . $key . ($msg ? ' (' . $msg . ')' : ''));
	}
}
class DownloadException extends Exception {
	public function __construct($url) {
		$this->message = 'Error while downloading ' . $url;
	}
}

interface CRUMModel {
	public function get($key);
	public function put($key, $data);
	public function getKeys();
	public function delete($key);
};

interface CachableModel extends CRUMModel {
	public function getCached($key);
	public function getReal($key);
	public function cacheExists($key);
};

interface CachableModelEntry {
	public function isFresh();
}

abstract class AbstractModelEntry implements CachableModelEntry {
	protected $generationTime = null;
	protected $expirationTime = null;

	public function setGenerationTime($time = null) {
		if ($time === null) {
			$time = time();
		}
		$this->generationTime = $time;
	}

	public function getGenerationTime() {
		return $this->generationTime;
	}

	public function setExpirationTime($time) {
		$this->expirationTime = $time;
	}

	public function getExpirationTime() {
		return $this->expirationTime;
	}


	public function isFresh() {
		return $this->expirationTime > time();
	}

}

abstract class AbstractModel implements CachableModel {
	protected $folder;
	protected $suffix = '.dat';

	protected function keyToPath($key) {
		return str_replace('//', '/', $this->folder . '/' . strtolower($key) . $this->suffix);
	}

	public function cacheExists($key) {
		return file_exists($this->keyToPath($key));
	}

	public function getFolder() {
		return $this->folder;
	}

	public function getKeys() {
		$ret = [];
		foreach (scandir($this->folder) as $_) {
			if (substr($_, 0, 1) == '.') {
				continue;
			}
			if (substr($_, - strlen($this->suffix)) != $this->suffix) {
				continue;
			}
			$ret []= substr($_, 0, - strlen($this->suffix));
		}
		return $ret;
	}

	public function delete($key) {
		$path = $this->keyToPath($key);
		if (!file_exists($path)) {
			return false;
		}
		ChibiRegistry::getHelper('mg')->suppressErrors();
		$ret = unlink($path);
		ChibiRegistry::getHelper('mg')->restoreErrors();
		return $ret;
	}

	public function getCached($key) {
		$path = $this->keyToPath($key);
		ChibiRegistry::getHelper('mg')->suppressErrors();
		$f = fopen($path, 'rb');
		ChibiRegistry::getHelper('mg')->restoreErrors();
		if (!$f) {
			return null;
		}
		if (flock($f, LOCK_SH)) {
			$contents = file_get_contents($path);
			flock($f, LOCK_UN);
			fclose($f);
		} else {
			fclose($f);
			throw new LockException($path);
		}
		$data = unserialize(gzuncompress($contents));
		return $data;
	}

	const CACHE_POLICY_FORCE_REAL = 0; //force to load real data
	const CACHE_POLICY_DEFAULT = 1; //load cache if it hasn't expired, otherwise load real data
	const CACHE_POLICY_FORCE_CACHE = 2; //force to load cached data, if it's available; otherwise load real data

	public function get($key, $cachePolicy = self::CACHE_POLICY_DEFAULT) {
		$data = $this->getCached($key);
		if ($data and (($cachePolicy == self::CACHE_POLICY_DEFAULT and $data->isFresh()) or ($cachePolicy == self::CACHE_POLICY_FORCE_CACHE))) {
			return $data;
		}
		$data = $this->getReal($key);
		if (empty($data)) {
			return null;
		}
		$this->put($key, $data);
		return $data;
	}

	public function put($key, $data) {
		$path = $this->keyToPath($key);
		$contents = gzcompress(serialize($data));
		$f = fopen($path, 'cb');
		if (!$f) {
			throw new LockException($path);
		}
		if (flock($f, LOCK_EX)) {
			ftruncate($f, 0);
			$return = fwrite($f, $contents);
			flock($f, LOCK_UN);
			fclose($f);
		} else {
			fclose($f);
			throw new LockException($path);
		}
		return $return;
	}
}
