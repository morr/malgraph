<?php
class HTTPException extends Exception {
	public function __construct($httpCode, $message) {
		$this->httpCode = $httpCode;
		$this->message = $message;
	}
	public function getHTTPCode() {
		return $this->httpCode;
	}
}

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

		session_start();
		if (!empty($_GET['user-name'])) {
			$this->view->userName = $_GET['user-name'];
		}
	}

	public function work() {
		try {
			parent::work();
		} catch (HTTPException $e) {
			$c = ChibiController::getInstance($this->config->chibi->basic->errorController, $this->config->chibi->basic->errorHttpAction);
			$c->errorCode = $e->getHTTPCode();
			$c->message = $e->getMessage();
			$c->work();
		} catch (Exception $e) {
			$c = ChibiController::getInstance($this->config->chibi->basic->errorController, $this->config->chibi->basic->errorHttpAction);
			$c->message = $e->getMessage();
			$c->work();
		}
	}

}
