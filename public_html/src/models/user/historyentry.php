<?php
class UserAnimeHistoryEntry extends UserHistoryEntry {
	private $episode = null;

	public function getType() {
		return AMModel::TYPE_ANIME;
	}

	public function setEpisode($episode) {
		$this->episode = $episode;
	}

	public function getEpisode() {
		return $this->episode;
	}
}

class UserMangaHistoryEntry extends UserHistoryEntry {
	private $chapter = null;

	public function getType() {
		return AMModel::TYPE_MANGA;
	}

	public function setChapter($chapter) {
		$this->chapter = $chapter;
	}

	public function getChapter() {
		return $this->chapter;
	}
}

class UserHistoryEntry {
	private $id;
	private $timestamp;
	private $history = null;

	public function __construct(UserHistory $parentHistory, $id) {
		$this->history = $parentHistory;
		$this->id = $id;
	}

	public function getHistory() {
		return $this->history;
	}

	public static function factory(UserHistory $history, $id) {
		switch ($history->getType()) {
			case AMModel::TYPE_ANIME:
				return new UserAnimeHistoryEntry($history, $id);
			case AMModel::TYPE_MANGA:
				return new UserMangaHistoryEntry($history, $id);
		}
		throw new Exception('Unknown entry type');
	}

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		$this->id = $id;
	}

	public function setTimestamp($time) {
		$this->timestamp = $time;
	}

	public function getTimestamp() {
		return $this->timestamp;
	}

	public function getTime() {
		return date('H:i:s', $this->getTimestamp());
	}

	public function getDate() {
		return date('Y-m-d', $this->getTimestamp());
	}

	public function getAMEntry() {
		return AMEntryRuntimeCacheService::lookup($this->getHistory()->getAMModel(), $this->getID());
	}
}
