<?PHP

namespace Shelby\View;

abstract class AbstractClass implements \Yaf_View_Interface {

	/**
	 * Application options
	 * 
	 * @var array
	 */
	protected $_options;
	
	public function __construct(Array $options) {
		$this->_options = $options;
	}
	
	public function __set($name, $value) {
		$this->assign($name, $value);
	}

	public function __get($name) {}

	public function getScriptPaths() {
		return array();
	}

}