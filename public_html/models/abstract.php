<?php
class InvalidEntryException extends Exception {}
class DownloadException extends Exception {}

interface DB {
	public function get($key);
	public function put($key, &$data);
	public function getKeys();
	public function delete($key);
};

interface CachedDB extends DB {
	public function getCached($key);
	public function getReal($key);
	public function isFresh($data);
	public function cacheExists($key);
};

abstract class JSONDB extends ChibiModel implements CachedDB {
	protected $folder;
	protected $suffix = '.json';

	protected function keyToPath($key) {
		return str_replace('//', '/', $this->folder . '/' . strtolower($key) . $this->suffix);
	}

	public function cacheExists($key) {
		return file_exists($this->keyToPath(key));
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
		return unlink($path);
	}

	public function getCached($key) {
		$path = $this->keyToPath($key);
		if (!file_exists($path)) {
			return false;
		}
		$contents = file_get_contents($path);
		$data = json_decode($contents, true);
		return $data;
	}

	public function get($key, $getReal = false) {
		$data = $this->getCached($key);
		if (!$getReal and $data and $this->isFresh($data)) {
			return $data;
		}
		$data = $this->getReal($key);
		if (empty($data)) {
			return null;
		}
		$this->put($key, $data);
		return $data;
	}

	public function put($key, &$data) {
		$path = $this->keyToPath($key);
		return file_put_contents($path, json_encode($data), LOCK_EX);
	}
}
