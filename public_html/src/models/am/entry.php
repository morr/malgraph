<?php
class AnimeEntry extends AMEntry {
	use AnimeModelDecorator;

	const SUBTYPE_OVA = 'ova';
	const SUBTYPE_ONA = 'ona';
	const SUBTYPE_TV = 'tv';
	const SUBTYPE_SPECIAL = 'special';
	const SUBTYPE_MUSIC = 'music';
	const SUBTYPE_MOVIE = 'movie';

	private $episodes = null;
	private $duration = null;
	private $producers = [];

	public function __construct($id) {
		parent::__construct($id);
	}

	public function getTotalDuration() {
		return $this->getEpisodeCount() * $this->getDuration();
	}

	public function getEpisodeCount() {
		if (!$this->episodes) {
			return null;
		}
		return $this->episodes;
	}

	public function setEpisodeCount($count) {
		$this->episodes = $count;
	}

	public function getDuration() {
		if (!$this->duration) {
			return null;
		}
		return $this->duration;
	}

	public function setDuration($duration) {
		$this->duration = $duration;
	}

	public function getProducers() {
		return $this->producers;
	}

	public function getCreators() {
		return $this->getProducers();
	}

	public function addProducer(AnimeProducerEntry $producer) {
		$this->producers []= $producer;
	}

	public function resetProducers() {
		$this->producers = [];
	}
}

class MangaEntry extends AMEntry {
	use MangaModelDecorator;

	const SUBTYPE_MANGA = 'manga';
	const SUBTYPE_MANHWA = 'manhwa';
	const SUBTYPE_MANHUA = 'manhua';
	const SUBTYPE_NOVEL = 'novel';
	const SUBTYPE_ONESHOT = 'one shot';
	const SUBTYPE_DOUJIN = 'doujin';

	private $volumes = null;
	private $chapters = null;
	private $serialization = null;
	private $authors = [];

	public function __construct($id) {
		parent::__construct($id);
	}

	public function getTotalDuration() {
		return $this->getChapterCount() * $this->getDuration();
	}

	public function getDuration() {
		return 10 /* 10 minutes per chapter */;
	}

	public function getVolumeCount() {
		return $this->volumes;
	}

	public function setVolumeCount($count) {
		$this->volumes = $count;
	}

	public function getChapterCount() {
		return $this->chapters;
	}

	public function setChapterCount($count) {
		$this->chapters = $count;
	}

	public function getSerialization() {
		return $this->serialization;
	}

	public function setSerialization(MangaSerializationEntry $serialization) {
		$this->serialization = $serialization;
	}

	public function getAuthors() {
		return $this->authors;
	}

	public function getCreators() {
		return $this->getAuthors();
	}

	public function addAuthor(MangaAuthorEntry $author) {
		$this->authors []= $author;
	}

	public function resetAuthors() {
		$this->authors = [];
	}
}


abstract class AMEntry extends AbstractModelEntry {
	const STATUS_NOT_YET_STARTED = 'not yet aired/published';
	const STATUS_NOT_YET_AIRED = self::STATUS_NOT_YET_STARTED;
	const STATUS_NOT_YET_PUBLISHED = self::STATUS_NOT_YET_STARTED;
	const STATUS_PUBLISHING = 'airing/publishing';
	const STATUS_AIRING = self::STATUS_PUBLISHING;
	const STATUS_FINISHED = 'finished';

	private $id = null;
	private $subType = null;
	private $status = null;
	private $title = null;
	private $ranking = null;
	private $airedFrom = null;
	private $airedTo = null;
	private $genres = [];
	private $tags = [];
	private $relations = [];
	private $valid;

	public function invalidate($invalid) {
		$this->valid = !$invalid;
		if ($invalid) {
			$this->subType = null;
			$this->status = null;
			$this->title = null;
			$this->ranking = null;
			$this->airedFrom = null;
			$this->airedTo = null;
			$this->genres = [];
			$this->tags = [];
			$this->relations = [];
		}
	}

	public function isValid($valid) {
		return $this->valid;
	}

	public function __construct($id) {
		$this->id = $id;
	}

	public function isDead() {
		return empty($this->title);
	}

	public function setDead($dead) {
		if ($dead) {
			$this->title = null;
		}
	}

	public function getTitle() {
		return $this->title;
	}

	public function setTitle($title) {
		$this->title = $title;
	}

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		$this->id = $id;
	}

	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = $status;
	}

	public function getSubType() {
		return $this->subType;
	}

	public function setSubType($type) {
		$this->subType = $type;
	}

	public function getRanking() {
		return $this->ranking;
	}

	public function setRanking($ranking) {
		$this->ranking = $ranking;
	}

	abstract public function getCreators();

	public function getAiredFrom() {
		return $this->airedFrom;
	}

	public function setAiredFrom($airDate) {
		$this->airedFrom = $airDate;
	}

	public function getAiredTo() {
		return $this->airedTo;
	}

	public function setAiredTo($airDate) {
		$this->airedTo = $airDate;
	}

	public function getGenres() {
		return $this->genres;
	}

	public function addGenre(AMGenreEntry $genre) {
		$this->genres []= $genre;
	}

	public function resetGenres() {
		$this->genres = [];
	}

	public function getRelations() {
		return $this->relations;
	}

	public function addRelation(AMRelationEntry $relation) {
		$this->relations []= $relation;
	}

	public function resetRelations() {
		$this->relations = [];
	}

	public function getTags() {
		return $this->tags;
	}

	public function addTag(AMTagEntry $tag) {
		$this->tags []= $tag;
	}

	public function resetTags() {
		$this->tags = [];
	}

}
