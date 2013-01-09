<?php
require_once 'src/controllers/abstract.php';
class ErrorController extends AbstractController {
	public function phpAction() {
		$backtrace = debug_backtrace();
		ChibiRegistry::getView()->backtrace = $backtrace;
		ChibiRegistry::getView()->errorString = @$this->errorString;
		ChibiRegistry::getView()->errorId = @$this->errorId;
		ChibiRegistry::getView()->errorFile = @$this->errorFile;
		ChibiRegistry::getView()->errorLine = @$this->errorLine;
	}

	public function httpAction() {
		if (!empty($this->errorCode)) {
			ChibiRegistry::getView()->errorCode = $this->errorCode;
		} elseif (!empty($_REQUEST['code'])) {
			ChibiRegistry::getView()->errorCode = $_REQUEST['code'];
		} else {
			ChibiRegistry::getView()->errorCode = -1;
		}

		if (!empty($this->message)) {
			ChibiRegistry::getView()->message = $this->message;
		} elseif (!empty($_REQUEST['message'])) {
			ChibiRegistry::getView()->messsage = $_REQUEST['message'];
		} elseif ($this->view->errorCode == 404) {
			ChibiRegistry::getView()->message = 'Requested URL: &bdquo;' . $_SERVER['REQUEST_URI'] . '&rdquo; cannot be found.';
		} elseif ($this->view->errorCode == 403) {
			ChibiRegistry::getView()->message = 'Requested URL: &bdquo;' . $_SERVER['REQUEST_URI'] . '&rdquo; is inaccessible.';
		}
	}
}
