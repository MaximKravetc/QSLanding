<?php

/**
 * @method \View\Helpers\Url getUrlObj()
 */

namespace View;

/**
 * Class Frontend
 * @package View
 * @method \View\Helpers\Url getUrlObj
 */
class Frontend extends \Shelby\View\Smarty\AbstractClass {

	protected function _attachHelpers() {
		$mixedObj = new Helpers\Mixed();
		$this->_smarty->assignByRef('HMixed', $mixedObj);
		
		$this->_urlObj = new Helpers\Url();
		$this->_urlObj->setRequestParams($this->_requestParams);

		$this->_smarty->assignByRef('Url', $this->_urlObj);

        $seoObj = new Helpers\Seo();
        $this->_smarty->assignByRef('Seo', $seoObj);
	}
	
}
