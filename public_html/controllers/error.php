<?php
require_once 'controllers/abstract.php';
class ErrorController extends AbstractController {
	public function phpAction() {
		$backtrace = debug_backtrace();
		$this->view->backtrace = $backtrace;
		$this->view->errorString = @$this->errorString;
		$this->view->errorId = @$this->errorId;
		$this->view->errorFile = @$this->errorFile;
		$this->view->errorLine = @$this->errorLine;
	}

	public function httpAction() {
		if (!empty($this->errorCode)) {
			$this->view->errorCode = $this->errorCode;
		} elseif (!empty($_REQUEST['code'])) {
			$this->view->errorCode = $_REQUEST['code'];
		}
		if (!empty($this->message)) {
			$this->view->message = $this->message;
		} elseif (!empty($_REQUEST['message'])) {
			$this->view->messsage = $_REQUEST['message'];
		}
	}
}
