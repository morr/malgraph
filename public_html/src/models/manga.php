<?php
require_once 'am.php';
require_once 'am/serializationentry.php';
require_once 'am/creatorentry.php';

class MangaModel extends AMModel {
	public function getType() {
		return AMModel::TYPE_MANGA;
	}

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->mangaCacheDir;
	}

	protected function loadManga(&$mangaEntry, $doc) {
		$xpath = new DOMXpath($doc);

		//chapter count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Chapter\')]')->item(0)->nextSibling->textContent, $matches);
		$mangaEntry->setChapterCount(intval($matches[0][0]));

		//volume count
		preg_match_all('/([0-9]+|Unknown)/', $xpath->query('//span[starts-with(text(), \'Volume\')]')->item(0)->nextSibling->textContent, $matches);
		$mangaEntry->setVolumeCount(intval($matches[0][0]));

		//serialization
		$q = $xpath->query('//span[starts-with(text(), \'Serialization\')]/../a');
		if ($q->length > 0) {
			$node = $q->item(0);
			preg_match('/=([0-9]+)/', $node->getAttribute('href'), $matches);
			$serialization = new MangaSerializationEntry();
			$serialization->setID(intval($matches[1]));
			$serialization->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($q->item(0)->nodeValue));
			$mangaEntry->setSerialization($serialization);
		}

		//authors
		$mangaEntry->resetAuthors();
		foreach ($xpath->query('//span[starts-with(text(), \'Authors\')]/../a') as $node) {
			preg_match('/people\/([0-9]+)\//', $node->getAttribute('href'), $matches);
			if (count($matches) < 2) {
				continue;
			}
			$author = new MangaAuthorEntry();
			$author->setID(intval($matches[1]));
			$author->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->nodeValue));
			$mangaEntry->addAuthor($author);
		}
	}

	public function getReal($id) {
		$doc = $this->getXML('manga', $id);

		$mangaEntry = new MangaEntry($id);
		$mangaEntry->setGenerationTime(time());
		$mangaEntry->setExpirationTime(time() + 3600 * 24 * 21);

		try {
			$this->loadCommon($mangaEntry, $doc);
			$this->loadManga($mangaEntry, $doc);
		} catch (InvalidEntryException $e) {
			$animeEntry->invalidate(true);
		}

		return $mangaEntry;
	}
}
