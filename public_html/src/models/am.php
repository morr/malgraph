<?php
require_once 'abstract.php';
abstract class AMModel extends JSONDB {
	const ENTRY_URL = 'http://myanimelist.net/{type}/{id}';

	const ENTRY_STATUS_NOT_YET_STARTED = 'not yet aired/published';
	const ENTRY_STATUS_NOT_YET_AIRED = self::ENTRY_STATUS_NOT_YET_STARTED;
	const ENTRY_STATUS_NOT_YET_PUBLISHED = self::ENTRY_STATUS_NOT_YET_STARTED;
	const ENTRY_STATUS_PUBLISHING = 'airing/publishing';
	const ENTRY_STATUS_AIRING = self::ENTRY_STATUS_PUBLISHING;
	const ENTRY_STATUS_FINISHED = 'finished';

	const ENTRY_TYPE_ANIME = 'anime';
	const ENTRY_TYPE_MANGA = 'manga';

	protected function getXML($type, $id) {
		$url = $this->mgHelper->replaceTokens(self::ENTRY_URL, ['type' => $type, 'id' => $id]);
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$contents = $this->mgHelper->download($url);
		$this->mgHelper->suppressErrors();
		if (empty($contents)) {
			throw new DownloadException($url);
		}
		$doc->loadHTML($contents);
		$this->mgHelper->restoreErrors();
		return $doc;
	}

	protected function loadCommon(&$entry, $doc) {
		$xpath = new DOMXPath($doc);

		$node = $xpath->query('//div[@class = \'badresult\']');
		if ($node->length >= 1) {
			throw new InvalidEntryException($entry['id']);
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
		$entry['title'] = $this->mgHelper->fixText($node2->textContent);

		//rank
		preg_match_all('/#([0-9]+)/', $xpath->query('//h1')->item(0)->childNodes->item(0)->nodeValue, $matches);
		$entry['ranked'] = intval($matches[1][0]);

		//type
		$entry['type'] = strtolower($this->mgHelper->fixText($xpath->query('//span[starts-with(text(), \'Type\')]')->item(0)->nextSibling->textContent));

		//status
		$malStatus = strtolower($this->mgHelper->fixText($xpath->query('//span[starts-with(text(), \'Status\')]')->item(0)->nextSibling->textContent));
		switch ($malStatus) {
			case 'not yet published':
			case 'not yet aired':
				$entry['status'] = self::ENTRY_STATUS_NOT_YET_PUBLISHED;
				break;
			case 'publishing':
			case 'airing':
				$entry['status'] = self::ENTRY_STATUS_PUBLISHING;
				break;
			case 'finished':
			case 'finished airing':
				$entry['status'] = self::ENTRY_STATUS_FINISHED;
				break;
		}

		//air dates
		$entry['aired-string'] = $this->mgHelper->fixText($xpath->query('//span[starts-with(text(), \'Aired\') or starts-with(text(), \'Published\')]')->item(0)->nextSibling->textContent);
		if (strpos($entry['aired-string'], ' to ') !== false) {
			$entry['aired-from'] = $this->mgHelper->fixDate(substr($entry['aired-string'], 0, strrpos($entry['aired-string'], ' to ')));
			$entry['aired-fo'] = $this->mgHelper->fixDate(substr($entry['aired-string'], strrpos($entry['aired-string'], ' to ') + 4));
		} else {
			$entry['aired-from'] = $entry['aired-to'] = $this->mgHelper->fixDate($entry['aired-string']);
		}

		//genres
		$entry['genres'] = array();
		foreach ($xpath->query('//span[starts-with(text(), \'Genres\')]/../a') as $node) {
			preg_match('/=([0-9]+)/', $node->getAttribute('href'), $matches);
			$entry['genres'] []= [
				'id' => intval($matches[1]),
				'name' => strtolower($this->mgHelper->fixText($node->textContent))
			];
		}

		//tags
		$entry['tags'] = array();
		foreach ($xpath->query('//h2[starts-with(text(), \'Popular Tags\')]/following-sibling::*/a') as $node)
		{
			$entry['tags'] []= [
				'count' => intval($node->getAttribute('title')),
				'name' => $this->mgHelper->fixText($node->textContent)
			];
		}

		//relations
		$entry['related'] = array();
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
			$relation = strtolower($this->mgHelper->fixText($node->previousSibling->textContent));
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
				$type = self::ENTRY_TYPE_ANIME;
			} elseif (strpos($link, '/manga') !== false) {
				$type = self::ENTRY_TYPE_MANGA;
			} else {
				continue;
			}

			$entry['related'] []= [
				'relation' => $relation,
				'type' => $type,
				'id' => $id
			];
		}

	}
}
