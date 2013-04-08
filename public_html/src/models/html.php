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
			if (strpos($key, $u) !== false) {
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

	public function keyToPath($key) {
		if (!is_array($key) and preg_match('/^[0-9a-f]{72}_/i', $key))
		{
			$path = $key;
		}
		else
		{
			$path = json_encode($key);
			$path = strtolower($path);
			$path = gzcompress($path);
			$path = md5($path) . sha1($path);
			if (isset($key['u']))
			{
				$path .= '_' . $key['u'];
			}
		}
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
