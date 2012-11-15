<?php
class AbstractController extends ChibiController {
	public function init() {
		if (!session_id()) {
			session_start();
		}
		$this->registry->loadModel('user');
		$this->registry->loadModel('anime');
		$this->registry->loadModel('manga');

		$this->view->controllerName = $this->config->chibi->runtime->controllerName;
		$this->view->actionName = $this->config->chibi->runtime->actionName;

		//basic settings
		$this->headHelper->setTitle('MALgraph');
		$this->headHelper->setFavicon($this->urlHelper->url('media/img/favicon.png'));

		//stylesheets
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/bootstrap.min.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/jquery.jscrollpane.css'));
		$this->headHelper->addStylesheet($this->urlHelper->url('media/css/core.css'));
		if (file_exists($p = 'media/css/'. $this->view->controllerName . '.css')) {
			$this->headHelper->addStylesheet($this->urlHelper->url($p));
		}
		if (file_exists($p = 'media/css/' . $this->view->controllerName . '-' . $this->view->actionName . '.css')) {
			$this->headHelper->addStylesheet($this->urlHelper->url($p));
		}

		//scripts
		$this->headHelper->addScript($this->urlHelper->url('media/js/jquery.min.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/jquery.mousewheel.min.js'));
		$this->headHelper->addScript($this->urlHelper->url('media/js/jquery.jscrollpane.min.js'));
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '.js')) {
			$this->headHelper->addScript($this->urlHelper->url($p));
		}
		if (file_exists($p = 'media/js/' . $this->view->controllerName . '-' . $this->view->actionName . '.js')) {
			$this->headHelper->addScript($this->urlHelper->url($p));
		}
		//load core script after dynamic scripts, since it works with some special css classes
		//and dynamic scripts linked above might want to set/unset such classes first.
		$this->headHelper->addScript($this->urlHelper->url('media/js/core.js'));

	}

}
