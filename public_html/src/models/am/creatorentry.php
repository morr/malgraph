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
	use AnimeModelDecorator;

	public function __toString() {
		return 'producer[' . $this->getID() . ']';
	}
}

class MangaAuthorEntry extends AMCreatorEntry {
	use MangaModelDecorator;

	public function __toString() {
		return 'author[' . $this->getID() . ']';
	}
}
