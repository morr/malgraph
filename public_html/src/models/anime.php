<?php
require_once 'am.php';
class AnimeModel extends AMModel {
	const ENTRY_SUBTYPE_OVA = 'ova';
	const ENTRY_SUBTYPE_ONA = 'ona';
	const ENTRY_SUBTYPE_TV = 'tv';
	const ENTRY_SUBTYPE_SPECIAL = 'special';
	const ENTRY_SUBTYPE_MUSIC = 'music';
	const ENTRY_SUBTYPE_MOVIE = 'movie';

	public function __construct() {
		$this->folder = $this->config->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . $this->config->misc->animeCacheDir;
	}

	public function isFresh($data) {
		return isset($data['expires']) and time() <= $data['expires'];
	}

	protected function loadAnime(&$entry, $doc) {
		$xpath = new DOMXpath($doc);

		//am
		$entry['type'] = self::ENTRY_TYPE_ANIME;

		$entry['duration'] = 0;
		preg_match_all('/([0-9]+)/', $xpath->query('//span[starts-with(text(), \'Duration\')]')->item(0)->nextSibling->textContent, $matches);
		array_reverse($matches);
		foreach($matches[0] as $r) {
			$entry['duration'] *= 60;
			$entry['duration'] += $r;
		}

		$entry['producers'] = [];
		foreach ($xpath->query('//span[starts-with(text(), \'Producers\')]/../a') as $node) {
			preg_match('/\?p=([0-9]+)/', $node->getAttribute('href'), $matches);
			if (count($matches) < 2) {
				continue;
			}
			$entry['producers'] []= [
				'id' => intval($matches[1]),
				'name' => $this->mgHelper->fixText($node->nodeValue)
			];
		}

		//episode count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Episodes\')]')->item(0)->nextSibling->textContent, $matches);
		$entry['episodes'] = intval($matches[0][0]);
	}

	public function getReal($id) {
		$doc = $this->getXML('anime', $id);

		$entry = [];
		$entry['id'] = $id;
		$entry['generated'] = time();
		$entry['expires'] = time() + 3600 * 24 * 21;

		$this->loadCommon($entry, $doc);
		$this->loadAnime($entry, $doc);

		return $entry;
	}
}
