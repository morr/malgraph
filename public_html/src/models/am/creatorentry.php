<?php
class AMCreatorEntry {
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
}


class AnimeProducerEntry extends AMCreatorEntry {
	public function __toString() {
		return 'producer[' . $this->getID() . ']';
	}
}

class MangaAuthorEntry extends AMCreatorEntry {
	public function __toString() {
		return 'author[' . $this->getID() . ']';
	}
}
