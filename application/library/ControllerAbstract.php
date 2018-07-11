<?php
/**
 * @property \View\Frontend _view
 * @property ModelAbstract model
 */
abstract class ControllerAbstract extends Yaf_Controller_Abstract {

	/**
	 * Model object instance.
	 *
	 * @var ModelAbstract
	 */
	protected $model = null;

    /**
     * @var \View\Helpers\Url
     */
	protected $urlHelper = null;

	/**
	 * Initialization
	 */
	public function init() {
		// Model creation
		$this->_initModel();

        $this->urlHelper = new \View\Helpers\Url();

		$req = $this->getRequest();
		$params = $req->getParams();
		$params['controller'] = strtolower($req->getControllerName());
		$params['action'] = strtolower($req->getActionName());
		$this->_view->setRequestParams($params);
    }

    /**
     * Model Initialization
     *
     */
    protected function _initModel() {
        $modelName = $this->getRequest()->getControllerName() . 'Model';
        $this->model = new $modelName();
    }

	protected function _pageNotFound() {
		throw new Yaf_Exception_LoadFailed('Not found', YAF_ERR_NOTFOUND_MODULE);
	}
	
	/**
	 * Last action result helper method
	 *
	 * @param mixed $result
	 */
	protected function _setLastActionResult($result = null) {
	    if (!is_null($result)) {
            $this->_view->assign('result', $result);
        }
	}


	public function redirect($url, $code = 302) {
		header('Location: ' . $url, true, $code);
		exit;
	}
	
}
