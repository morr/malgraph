<?php
class AMTagEntry {
	private $count;
	private $name;

	public function getCount() {
		return $this->count;
	}

	public function setCount($count) {
		$this->count = $count;
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		$this->name = $name;
	}
}
