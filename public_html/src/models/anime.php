<?php
require_once 'am.php';
require_once 'producer.php';

class AnimeModel extends AMModel {
	public function getType() {
		return AMModel::TYPE_ANIME;
	}

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->animeCacheDir;
	}

	public function isFresh($data) {
		return isset($data['expires']) and time() <= $data['expires'];
	}

	protected function loadAnime(&$animeEntry, $doc) {
		$xpath = new DOMXpath($doc);

		$animeEntry->setDuration(0);
		preg_match_all('/([0-9]+)/', $xpath->query('//span[starts-with(text(), \'Duration\')]')->item(0)->nextSibling->textContent, $matches);
		array_reverse($matches);
		foreach($matches[0] as $r) {
			$animeEntry->setDuration($animeEntry->getDuration() * 60 + $r);
		}

		$animeEntry->resetProducers();
		foreach ($xpath->query('//span[starts-with(text(), \'Producers\')]/../a') as $node) {
			preg_match('/\?p=([0-9]+)/', $node->getAttribute('href'), $matches);
			if (count($matches) < 2) {
				continue;
			}
			$producer = new AnimeProducerEntry();
			$producer->setID(intval($matches[1]));
			$producer->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
			$animeEntry->addProducer($producer);
		}

		//episode count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Episodes\')]')->item(0)->nextSibling->textContent, $matches);
		$animeEntry->setEpisodeCount(intval($matches[0][0]));
	}

	public function getReal($id) {
		$doc = $this->getXML('anime', $id);

		$animeEntry = new AnimeEntry($id);
		$animeEntry->setGenerationTime(time());
		$animeEntry->setExpirationTime(time() + 3600 * 24 * 21);

		$this->loadCommon($animeEntry, $doc);
		$this->loadAnime($animeEntry, $doc);

		return $animeEntry;
	}
}
