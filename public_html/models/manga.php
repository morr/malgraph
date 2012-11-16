<?php
require_once 'am.php';
class MangaModel extends AMModel {
	public function __construct() {
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'manga';
	}

	public function isFresh($data) {
		return isset($data['expires']) and time() <= $data['expires'];
	}

	protected function loadManga(&$entry, $doc) {
		$xpath = new DOMXpath($doc);

		//chapter count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Chapter\')]')->item(0)->nextSibling->textContent, $matches);
		$entry['chapters'] = intval($matches[0][0]);

		//volume count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Volume\')]')->item(0)->nextSibling->textContent, $matches);
		$entry['volumes'] = intval($matches[0][0]);

		//serialization
		$q = $xpath->query('//span[starts-with(text(), \'Serialization\')]/../a');
		$entry['serialization'] = false;
		if ($q->length > 0) {
			$node = $q->item(0);
			preg_match('/=([0-9]+)/', $node->getAttribute('href'), $matches);
			$entry['serialization'] = [
				'id' => intval($matches[1]),
				'name' => $this->mgHelper->fixText($q->item(0)->nodeValue)
			];
		}

		//authors
		$entry['authors'] = array();
		foreach ($xpath->query('//span[starts-with(text(), \'Authors\')]/../a') as $node) {
			preg_match('/people\/([0-9]+)\//', $node->getAttribute('href'), $matches);
			if (count($matches) < 2) {
				continue;
			}
			$entry['authors'] []= [
				'id' => intval($matches[1]),
				'name' => $this->mgHelper->fixText($node->nodeValue)
			];
		}
	}

	public function getReal($id) {
		$doc = $this->getXML('manga', $id);

		$entry = [];
		$entry['id'] = $id;
		$entry['generated'] = time();
		$entry['expires'] = time() + 3600 * 24 * 21;

		$this->loadCommon($entry, $doc);
		$this->loadManga($entry, $doc);

		return $entry;
	}
}
