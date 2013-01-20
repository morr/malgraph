<?php
class UserAnimeListEntry extends UserListEntry {
	private $episodes;

	public function getType() {
		return AMModel::TYPE_ANIME;
	}

	public function getCompletedDuration() {
		return $this->getCompletedEpisodes() * $this->getAMEntry()->getDuration();
	}

	public function getCompletedEpisodes() {
		return $this->episodes;
	}

	public function setCompletedEpisodes($episodes) {
		$this->episodes = $episodes;
	}
}

class UserMangaListEntry extends UserListEntry {
	public function getType() {
		return AMModel::TYPE_MANGA;
	}

	public function getCompletedDuration() {
		return $this->getCompletedChapters() * $this->getAMEntry()->getDuration();
	}

	public function getCompletedVolumes() {
		return $this->volumes;
	}

	public function setCompletedVolumes($volumes) {
		$this->volumes = $volumes;
	}

	public function getCompletedChapters() {
		return $this->chapters;
	}

	public function setCompletedChapters($chapters) {
		$this->chapters = $chapters;
	}
}

abstract class UserListEntry {
	const STATUS_DROPPED = 'dropped';
	const STATUS_ONHOLD = 'onhold';
	const STATUS_COMPLETING = 'completing';
	const STATUS_WATCHING = self::STATUS_COMPLETING;
	const STATUS_READING = self::STATUS_COMPLETING;
	const STATUS_COMPLETED = 'completed';
	const STATUS_FINISHED = self::STATUS_COMPLETED;
	const STATUS_PLANNED = 'planned';
	const STATUS_UNKNOWN = '???';

	const STATUS_MAL_DROPPED = 4;
	const STATUS_MAL_ONHOLD = 3;
	const STATUS_MAL_COMPLETING = 1;
	const STATUS_MAL_COMPLETED = 2;
	const STATUS_MAL_PLANNED = 6;

	private $score = null;
	private $id = null;
	private $status = null;
	private $startDate = null;
	private $finishDate = null;
	private $list = null;

	public abstract function getType();

	public function __construct(UserList $parentList, $id) {
		$this->list = $parentList;
		$this->id = $id;
	}

	public function getList() {
		return $this->list;
	}

	public static function factory(UserList $list, $id) {
		switch ($list->getType()) {
			case AMModel::TYPE_ANIME:
				return new UserAnimeListEntry($list, $id);
			case AMModel::TYPE_MANGA:
				return new UserMangaListEntry($list, $id);
		}
		throw new Exception('Unknown entry type');
	}

	public function getID() {
		return $this->id;
	}

	public function getScore() {
		return $this->score;
	}

	public function setScore($score) {
		$this->score = $score;
	}

	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = $status;
	}

	public function getStartDate() {
		return $this->startDate;
	}

	public function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	public function getFinishDate() {
		return $this->finishDate;
	}

	public function setFinishDate($finishDate) {
		$this->finishDate = $finishDate;
	}

	public function getAMEntry() {
		return AMEntryRuntimeCacheService::lookup($this->getList()->getAMModel(), $this->getID());
		return $this->getList()->getAMModel()->get($this->getID());
	}
}
