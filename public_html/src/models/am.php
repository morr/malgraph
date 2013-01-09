<?php
require_once 'abstract.php';
require_once 'amentry.php';
require_once 'genre.php';
require_once 'tag.php';
require_once 'relation.php';

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

	protected function getXML($type, $id) {
		$url = ChibiRegistry::getInstance()->getHelper('mg')->replaceTokens(self::URL, ['type' => $type, 'id' => $id]);
		$document = new DOMDocument;
		$document->preserveWhiteSpace = false;
		$contents = ChibiRegistry::getInstance()->getHelper('mg')->download($url);
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
			throw new Exception('Title node broken');
		}
		$node2 = $node1->childNodes->item(1);
		if (empty($node2)) {
			throw new Exception('Title node broken');
		}
		$entry->setTitle(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node2->textContent));

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
			$genre = new AMGenreEntry();
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
		$lastRelation = '';
		foreach ($xpath->query('//h2[starts-with(text(), \'Related\')]/../*') as $node) {
			if ($node->nodeName == 'h2' and strpos($node->textContent, 'Related') === false) {
				break;
			}
			if ($node->nodeName != 'a') {
				continue;
			}
			$link = $node->attributes->getNamedItem('href')->nodeValue;

			//relation
			$relation = strtolower(ChibiRegistry::getInstance()->getHelper('mg')->fixText($node->previousSibling->textContent));
			if ($relation == ',') {
				$relation = $lastRelation;
			} else {
				$lastRelation = $relation;
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
			$relation->setRelation($relation);
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
