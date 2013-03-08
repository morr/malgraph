<?php
class AMGenreEntry {
	private $id;
	private $name;

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		$this->id = $id;
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		$this->name = $name;
	}

	public function __toString() {
		return 'genre[' . $this->getID() . ']';
	}

	public static function factory($type) {
		switch ($type) {
			case AMModel::TYPE_ANIME: return new AnimeGenreEntry();
			case AMModel::TYPE_MANGA: return new MangaGenreEntry();
		}
		throw new InvalidAMTypeException();
	}
}

class AnimeGenreEntry extends AMGenreEntry {
	use AnimeModelDecorator;
}

class MangaGenreEntry extends AMGenreEntry {
	use MangaModelDecorator;
}
