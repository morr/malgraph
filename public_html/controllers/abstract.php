<?php
class AbstractController extends ChibiController {
	public function init() {
		$this->view->controllerName = $this->config->chibi->runtime->controllerName;
		$this->view->actionName = $this->config->chibi->runtime->actionName;

		//basic settings
		$this->headHelper->setTitle('MALgraph');
		$this->headHelper->addStylesheet($this->urlHelper->url('/media/style.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('/media/bootstrap.min.css'));
		$this->headHelper->addScript($this->urlHelper->url('/media/jquery.min.js'));

		//dynamic css
		if (file_exists($p = 'media' . DIRECTORY_SEPARATOR . $this->view->controllerName . '.css')) {
			$this->headHelper->addStylesheet($this->urlHelper->url($p));
		}
		if (file_exists($p = 'media' . DIRECTORY_SEPARATOR . $this->view->controllerName . '-' . $this->view->actionName . '.css')) {
			$this->headHelper->addStylesheet($this->urlHelper->url($p));
		}

		//dynamic js
		if (file_exists($p = 'media' . DIRECTORY_SEPARATOR . $this->view->controllerName . '.js')) {
			$this->headHelper->addScript($this->urlHelper->url($p));
		}
		if (file_exists($p = 'media' . DIRECTORY_SEPARATOR . $this->view->controllerName . '-' . $this->view->actionName . '.js')) {
			$this->headHelper->addScript($this->urlHelper->url($p));
		}

	}
}
