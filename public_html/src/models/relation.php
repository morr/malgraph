<?php
class AMRelationEntry {
	private $id;
	private $type;
	private $relation;

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		$this->id = $id;
	}

	public function getType() {
		return $this->type;
	}

	public function setType($type) {
		$this->type = $type;
	}

	public function getRelation() {
		return $this->relation;
	}

	public function setRelation($relation) {
		$this->relation = $relation;
	}
}
