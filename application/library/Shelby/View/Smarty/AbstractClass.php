<?PHP

namespace Shelby\View\Smarty;

abstract class AbstractClass extends \Shelby\View\AbstractClass {

	/**
	 * @var \Smarty
	 */
	protected $_smarty = null;

	/**
	 * @var array
	 */
	protected $_requestParams = array();

	/**
	 * Template name for rendering
	 *
	 * @var string
	 */
	protected $_template = null;

	/**
	 * @var \Shelby\View\Helpers\Url\AbstractClass
	 */
	protected $_urlObj;
	
	public function __get($name) {
		return $this->getEngine()->getTemplateVars($name);
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function get(string $name) {
		return $this->getEngine()->getTemplateVars($name);
	}

	/**
	* Returns Url object
	* 
	* @return \Shelby\View\Helpers\Url\AbstractClass
	*/
	public function getUrlObj() {
		$this->getEngine();
		return $this->_urlObj;
	}

	/**
	 * @return \Smarty
	 */
	public function getEngine() : \Smarty {
		if (is_null($this->_smarty)) {
			$this->_smarty = new \Smarty();
			$this->_smarty->setCompileDir($this->_options['compile_dir']);
			$this->_smarty->setTemplateDir($this->_options['template_dir']);
			$this->_smarty->use_sub_dirs = $this->_options['use_sub_dirs'];
			$this->_smarty->compile_check = $this->_options['compile_check'];
			$this->_smarty->compile_id = $this->_options['compile_id'];
			$this->_smarty->debugging = $this->_options['debugging'];
			
			if ($this->_options['caching'] == true) {
				$this->_smarty->caching = true;
				$this->_smarty->cache_lifetime = $this->_options['cache_lifetime'];
				$this->_smarty->setCacheDir($this->_options['cache_dir']);
			}

			//Pass by reference because these parameter may be modified in future
			$this->_smarty->assignByRef('user_params', $this->_requestParams);
			
			$this->_attachHelpers();
		}
		
		return $this->_smarty;
	}

	protected function _attachHelpers() {

	}
	
	public function setRequestParams(array $params) {
		$this->_requestParams = $params;
	}

	public function setScriptPath($path) {
		$this->getEngine()->setTemplateDir($path);
	}

	public function getScriptPath() {
		return $this->getEngine()->getTemplateDir();
	}

	public function __isset($key) {
		$res = $this->getEngine()->getTemplateVars($key);

		return (is_null($res) ? false:true);
	}


	public function __unset($key) {
		$this->getEngine()->clearAssign($key);
	}

	public function assign($spec, $value = null) {
		$this->getEngine()->assign($spec, $value);
	}

	public function clearVars() {
		$this->getEngine()->clearAllAssign();
	}
	
	public function render($name, $value = NULL) {
		$name = preg_replace('/[^a-z0-9_\-\/\.]/', '', strtolower($name));
		$output = $this->getEngine()->fetch($name);

		// Firebug debug output
		$resp = \Zend_Controller_Front::getInstance()->getResponse();
		$wf = \Zend_Wildfire_Channel_HttpHeaders::getInstance(true);
		if (!is_null($resp) && !is_null($wf)) {
			$wf->flush();

			$headers = $resp->getHeaders();
			foreach ($headers as $val) {
				if (strpos($val['name'], 'X-Wf-') === 0) {
					header($val['name'] . ':' . $val['value'], $val['replace']);
				}
			}
		}

		return $output;
	}

	public function display($name, $value = NULL) {
		$name = preg_replace('/[^a-z0-9_\-\/\.]/', '', strtolower($name));
		$this->getEngine()->display($name);
		return true;
	}

}