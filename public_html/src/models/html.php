<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/abstract.php';

class HTMLCacheModel extends AbstractModel {
	public function getReal($get) {
		error_log(print_r($get, true));
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

	public function get($key) {
		if (!ChibiConfig::getInstance()->misc->htmlCacheEnabled) {
			return self::getReal($key);
		}
		return parent::get($key);
	}

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->htmlCacheDir;
	}

	public function keyToPath($key) {
		$key = json_encode($key);
		$key = strtolower($key);
		$key = gzcompress($key);
		$key = base64_encode($key);
		$key = str_replace('=', '', $key);
		return str_replace('//', '/', $this->folder . '/' . $key . $this->suffix);
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
