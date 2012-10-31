<?php
class AbstractController extends ChibiController {
	public function init() {
		$this->headHelper->setTitle('Title of any page');
		$this->headHelper->addStylesheet($this->urlHelper->url('media/style.css'));
	}
}
