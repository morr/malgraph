<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/abstract.php';

class HTMLCacheModel extends AbstractModel {
	public function getReal($get) {
		ob_start();
		$request = $get['request'];
		$router = ChibiRegistry::get('router');
		$router->handleRequest($request);
		$contents = ob_get_contents();
		ob_end_clean();
		$entry = new HTMLCacheEntry($contents, $request);
		$entry->setExpirationTime(time() + 12 * 3600);
		$entry->setGenerationTime(time());
		return $entry;
	}

	public static function deleteUser($userName) {
		$modelCache = new self();
		$u = strtolower($userName);
		foreach ($modelCache->getKeys() as $key) {
			if (in_array($u, explode(',', strtolower($key['u'])))) {
				$modelCache->delete($key);
			}
		}
	}

	public function get($key, $policy = AbstractModel::CACHE_POLICY_DEFAULT) {
		if (!ChibiConfig::getInstance()->misc->htmlCacheEnabled) {
			return parent::get($key, AbstractModel::CACHE_POLICY_FORCE_REAL);
		}
		return parent::get($key, $policy);
	}

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->htmlCacheDir;
	}

	public function getKeys() {
		$keys = parent::getKeys();
		$keysFinal = [];
		foreach ($keys as $key) {
			$keysFinal []= $this->pathToKey($key);
		}
		return $keysFinal;
	}

	public function pathToKey($path) {
		$key = str_replace($this->suffix, '', basename($path));
		$key = hex2bin($key);
		$key = gzuncompress($key);
		$key = json_decode($key, true);
		return $key;
	}

	public function keyToPath($key) {
		$path = json_encode($key);
		$path = strtolower($path);
		$path = gzcompress($path);
		$path = md5($path) . sha1($path);
		$path = str_replace('//', '/', $this->folder . '/' . $path . $this->suffix);
		return $path;
	}
}

class HTMLCacheEntry extends AbstractModelEntry {
	private $contents = null;
	private $request = null;

	public function __construct($contents, $request) {
		$this->contents = gzcompress($contents);
		$this->request = $request;
	}

	public function getContents() {
		return gzuncompress($this->contents);
	}
}
