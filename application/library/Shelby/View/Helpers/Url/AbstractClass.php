<?php

namespace Shelby\View\Helpers\Url;

abstract class AbstractClass implements \Zend_View_Helper_Interface {
	
	/**
	 * Initial request parameters
	 * 
	 * @var array
	 */
	protected $initialParams = array();
	
	/**
	 * User constructed parameters
	 * 
	 * @var array
	 */
	protected $params = array();

	/**
	 * GET-params to pass-over
	 *
	 * @var array
	 */
	protected $getParams = array();

	/**
	 * List of parameters that must be deleted from the current parameters list
	 * 
	 * @var array
	 */
	protected $deleteParams = array();

	/**
	 * Set request parameters by reference
	 * 
	 * @param array $params
	 */
	public function setRequestParams(array &$params) {
		$this->initialParams = &$params;
	}
	
	public function setView(\Zend_View_Interface $view) {
		// Do nothing in Smarty implementation
	}

	/**
	 * Add current GET parameter value
	 * If $param is null or not specified, will add all GET-parameters
	 *
	 * @param string|null $param
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function addGet($param = null) {
		if (is_null($param)) {
			$this->getParams = array_merge($_GET, $this->getParams);
		} elseif (isset($_GET[$param])) {
			$this->getParams[$param] = $_GET[$param];
		}

		return $this;
	}

	public function query($name, $value) {
		$this->getParams[$name] = (string)$value;
		return $this;
	}
	
	/**
	 * Shortcut for controller
	 * @param string $controller
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function c($controller) {
		return $this->controller($controller);
	}
	
	/**
	 * Set controller
	 * @param string $controller
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function controller($controller) {
		$this->params['controller'] = $controller;
		return $this;
	}
	
	/**
	 * Shortcut for action
	 * @param string $action
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function a($action) {
		return $this->action($action);
	}
	
	/**
	 * Set action
	 * @param string $action
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function action($action) {
		$this->params['action'] = $action;
		return $this;
	}
	
	/**
	 * Shortcut for param
	 * 
	 * @param string $param
	 * @param string $value
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function p($param, $value) {
		return $this->param($param, $value);
	}
	
	/**
	 * Add an arbitrary parameter to the URL
	 * 
	 * @param string $param
	 * @param string $value
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function param($param, $value) {
		$this->params[$param] = $value;
		return $this;
	}
	
	public function page($p) {
		if ($p == 1) {
			if (isset($this->initialParams['page'])) {
				return $this->del('page');
			}
			return $this;
		}
		return $this->param('page', $p);
	}
	
	/**
	 * Add current parameter(s) value
	 * 
	 * @param string $param
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function add($param = null) {
		if (!is_null($param)) {
			if (isset($this->initialParams[$param])) {
				$this->params[$param] = $this->initialParams[$param];
			}
		} else {
			$this->params = array_merge($this->initialParams, $this->params);
		}

		return $this;
	}
		
	/**
	 * Delete the specified parameter from the list of current ones
	 * 
	 * @param string $param
	 * @return \Shelby\View\Helpers\Url\AbstractClass
	 */
	public function del($param) {
		$this->deleteParams[] = $param;
		return $this;
	}

	/**
	 * Assemble URL and reset object
	 * @return string
	 */
	public function get() {
		$params = $this->params;

		foreach ($this->deleteParams as $el) {
			unset($params[$el]);
		}

		// Sort keys to avoid URL duplication
		krsort($params);

		if ($params['controller'] == 'index' && $params['action'] == 'index' && sizeof($params) == 2) {
			$res = '/';
		} else {
			$res = '/' . $params['controller'];
			if ($params['action'] != 'index') {
				$res .= '/' . $params['action'];
			}

			unset($params['controller'], $params['action']);

			foreach ($params as $key => $p) {
				$res .= '/' . $key . '/' . $p;
			}
		}

		if (!empty($this->getParams)) {
			$res .= '?' . http_build_query($this->getParams);
		}

		$this->reset();

		return $res;
	}

	/**
	 * Convenient function, automatically call get() when trying to print an object
	 * 
	 * @return string
	 */
	public function __toString() {
		return $this->get();
	}
	
	/**
	 * Reset current object and prepare it for next request
	 */
	protected function reset() {
		$this->params = array();
		$this->getParams = array();
		$this->deleteParams = array();
	}
	
	public function direct() {
		// Nothing to do here
	}
	
	public function escape($keyword) {
		$res = trim(preg_replace('/[^0-9a-z]/u', '_', mb_strtolower($keyword)), '_');

		$count = 0;
		do {
			$res = str_replace('__', '_', $res, $count);
		} while ($count > 0);

		return $res;
	}
}