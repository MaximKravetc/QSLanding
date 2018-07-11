<?php
abstract class Dao_Crawler_Abstract {
	
	/**
	 * @var Zend_Http_Client
	 */
	private $httpObj = null;
	
	/**
	 * @return Zend_Http_Client
	 */
	protected function getHttpClientSingleton() {
		if (is_null($this->httpObj)) {
			$this->httpObj = new Zend_Http_Client();
		}
		
		return $this->httpObj;
	}
	
}