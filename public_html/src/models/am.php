<?php
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/abstract.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/entry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/genreentry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/tagentry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/relationentry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/creatorentry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/serializationentry.php';
require_once ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/src/models/am/service.php';

class InvalidAMTypeException extends Exception {
	public function __construct($type = null) {
		parent::__construct('Invalid entry type' . ($type === null ? '' : ' (' . $type . ')'));
	}
}

abstract class AMModel extends AbstractModel {
	const URL = 'http://myanimelist.net/{type}/{id}';

	const TYPE_ANIME = 'anime';
	const TYPE_MANGA = 'manga';

	private static $entryTypes = [
		self::TYPE_ANIME,
		self::TYPE_MANGA
	];

	public static function getTypes() {
		return self::$entryTypes;
	}

	public static function factory($type) {
		switch ($type) {
			case AMModel::TYPE_ANIME: return new AnimeModel();
			case AMModel::TYPE_MANGA: return new MangaModel();
		}
		throw new InvalidAMTypeException();
	}

	protected function getXML($type, $id) {
		$url = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL, ['type' => $type, 'id' => $id]);
		$document = new DOMDocument;
		$document->preserveWhiteSpace = false;
		list(, $contents) = ChibiRegistry::getInstance()->getHelper('mg')->download($url);
		ChibiRegistry::getInstance()->getHelper('mg')->suppressErrors();
		if (empty($contents)) {
			throw new DownloadException($url);
		}
		$document->loadHTML($contents);
		ChibiRegistry::getInstance()->getHelper('mg')->restoreErrors();
		return $document;
	}

	protected function loadCommon(AMEntry &$entry, $document) {
		$xpath = new DOMXPath($document);

		$node = $xpath->query('//div[@class = \'badresult\']');
		if ($node->length >= 1) {
			throw new InvalidEntryException($entry->getID());
		}

		//title
		$node1 = $xpath->query('//h1')->item(0);
		if (empty($node1)) {
			throw new InvalidEntryException($entry->getID(), 'Title node broken (1)');
		}
		$node2 = $node1->childNodes->item(1);
		if (empty($node2)) {
			throw new InvalidEntryException($entry->getID(), 'Title node broken (2)');
		}
		$entry->setTitle(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node2->textContent));

		//picture
		$node = $xpath->query('//td[@class = \'borderClass\']//img')->item(0);
		if (!empty($node)) {
			$entry->setPictureURL($node->getAttribute('src'));
		}

		//rank
		preg_match_all('/#([0-9]+)/', $xpath->query('//h1')->item(0)->childNodes->item(0)->nodeValue, $matches);
		$entry->setRanking(intval($matches[1][0]));

		//type
		//todo: remove this ugly hack with strtolower
		$entry->setSubType(strtolower(ChibiRegistry::getInstance()->getHelper('mg')->fixText($xpath->query('//span[starts-with(text(), \'Type\')]')->item(0)->nextSibling->textContent)));

		//status
		$malStatus = strtolower(ChibiRegistry::getInstance()->getHelper('mg')->fixText($xpath->query('//span[starts-with(text(), \'Status\')]')->item(0)->nextSibling->textContent));
		switch ($malStatus) {
			case 'not yet published':
			case 'not yet aired':
				$entry->setStatus(AMEntry::STATUS_NOT_YET_PUBLISHED);
				break;
			case 'publishing':
			case 'currently airing':
				$entry->setStatus(AMEntry::STATUS_PUBLISHING);
				break;
			case 'finished':
			case 'finished airing':
				$entry->setStatus(AMEntry::STATUS_FINISHED);
				break;
		}

		//air dates
		$airedString = ChibiRegistry::getInstance()->getHelper('mg')->fixText($xpath->query('//span[starts-with(text(), \'Aired\') or starts-with(text(), \'Published\')]')->item(0)->nextSibling->textContent);
		if (strpos($airedString, ' to ') !== false) {
			$entry->setAiredFrom(ChibiRegistry::getInstance()->getHelper('mg')->fixDate(substr($airedString, 0, strrpos($airedString, ' to '))));
			$entry->setAiredTo(ChibiRegistry::getInstance()->getHelper('mg')->fixDate(substr($airedString, strrpos($airedString, ' to ') + 4)));
		} else {
			$entry->setAiredFrom(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($airedString));
			$entry->setAiredTo(ChibiRegistry::getInstance()->getHelper('mg')->fixDate($airedString));
		}

		//genres
		$entry->resetGenres();
		foreach ($xpath->query('//span[starts-with(text(), \'Genres\')]/../a') as $node) {
			preg_match('/=([0-9]+)/', $node->getAttribute('href'), $matches);
			$genre = AMGenreEntry::factory($entry->getType());
			$genre->setID(intval($matches[1]));
			$genre->setName(strtolower(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->textContent)));
			$entry->addGenre($genre);
		}

		//tags
		$entry->resetTags();
		foreach ($xpath->query('//h2[starts-with(text(), \'Popular Tags\')]/following-sibling::*/a') as $node) {
			$tag = new AMTagEntry();
			$tag->setCount(intval($node->getAttribute('title')));
			$tag->setName(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->textContent));
			$entry->addTag($tag);
		}

		//relations
		$entry->resetRelations();
		$lastRelationType = '';
		foreach ($xpath->query('//h2[starts-with(text(), \'Related\')]/../*') as $node) {
			if ($node->nodeName == 'h2' and strpos($node->textContent, 'Related') === false) {
				break;
			}
			if ($node->nodeName != 'a') {
				continue;
			}
			$link = $node->attributes->getNamedItem('href')->nodeValue;

			//relation
			$relationType = strtolower(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->previousSibling->textContent));
			if ($relationType == ',') {
				$relationType = $lastRelationType;
			} else {
				$lastRelationType = $relationType;
			}

			//id
			preg_match_all('/([0-9]+)/', $link, $matches);
			if (!isset($matches[0][0])) {
				continue;
			}
			$id = intval($matches[0][0]);

			//type
			if (strpos($link, '/anime') !== false) {
				$type = self::TYPE_ANIME;
			} elseif (strpos($link, '/manga') !== false) {
				$type = self::TYPE_MANGA;
			} else {
				continue;
			}

			$relation = new AMRelationEntry();
			$relation->setType($type);
			$relation->setRelation($relationType);
			$relation->setID($id);
			$entry->addRelation($relation);
		}

	}
}

/* speed up looking am entries up by caching responses from model for duration of one web browser request */
class AMEntryRuntimeCacheService {
	static private $entries;

	public static function lookup($model, $id) {
		$key = $model->getType() . '_' . $id;
		if (empty(self::$entries[$key])) {
			self::$entries[$key] = $model->get($id);
		}
		return self::$entries[$key];
	}
}



trait AnimeModelDecorator {
	public function getType() {
		return AMModel::TYPE_ANIME;
	}

	public function getAMModel() {
		return new AnimeModel();
	}
}

class AnimeModel extends AMModel {
	public function getType() {
		return AMModel::TYPE_ANIME;
	}

	public function __construct() {
		$this->folder = ChibiConfig::getInstance()->chibi->runtime->rootFolder . DIRECTORY_SEPARATOR . ChibiConfig::getInstance()->misc->animeCacheDir;
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
		$animeEntry->setExpirationTime(time() + 3600 * 24 * (30 + mt_rand() % 30));

		try {
			$this->loadCommon($animeEntry, $doc);
			$this->loadAnime($animeEntry, $doc);
			if ($animeEntry->getStatus() != AMEntry::STATUS_FINISHED) {
				$animeEntry->setExpirationTime(time() + 3600 * 24 * 7);
			}
		} catch (InvalidEntryException $e) {
			$animeEntry->invalidate(true);
			$animeEntry->setExpirationTime(time() + 3600 * 24 * 3);
		}

		return $animeEntry;
	}
}



trait MangaModelDecorator {
	public function getType() {
		return AMModel::TYPE_MANGA;
	}

	public function getAMModel() {
		return new MangaModel();
	}
}

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
		$mangaEntry->setExpirationTime(time() + 3600 * 24 * (30 + mt_rand() % 30));

		try {
			$this->loadCommon($mangaEntry, $doc);
			$this->loadManga($mangaEntry, $doc);
			if ($mangaEntry->getStatus() != AMEntry::STATUS_FINISHED) {
				$mangaEntry->setExpirationTime(time() + 3600 * 24 * 7);
			}
		} catch (InvalidEntryException $e) {
			$mangaEntry->invalidate(true);
			$mangaEntry->setExpirationTime(time() + 3600 * 24 * 3);
		}

		return $mangaEntry;
	}
}



