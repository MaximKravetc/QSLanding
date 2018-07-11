<?php

class ErrorController extends ControllerAbstract {

	public function errorAction() {
		$exception = $this->getRequest()->getException();

		switch ($exception->getCode()) {
			case YAF_ERR_NOTFOUND_MODULE:
			case YAF_ERR_NOTFOUND_CONTROLLER:
            case YAF_ERR_NOTFOUND_ACTION:
			case YAF_ERR_NOTFOUND_VIEW:

				header('HTTP/1.1 404 Not Found', true);
				$this->_view->assign('error_message', '404 Not Found');
				break;
			default:
				header('HTTP/1.1 500 Internal Application Error', true);
				$this->_view->assign('error_message', '500 Internal Application Error');
				break;
		}
	}

}
