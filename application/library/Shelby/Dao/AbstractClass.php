<?php
namespace Shelby\Dao;

abstract class AbstractClass {
	
	/**
	 * @var \Zend_Log
	 */
	private static $logger = null;
	
	public static function setLogger(\Zend_Log $logger) {
		self::$logger = $logger;
	}
	
	public static function getLogger() {
		return self::$logger;
	}
	
	/**
	 * Returns a DAO singleton object
	 * @param string $name
	 * @return \Shelby\Dao\AbstractClass
	 */
	public static function getSingleton(string $name) : \Shelby\Dao\AbstractClass {
		if (\Zend_Registry::isRegistered($name)) {
			try {
				return \Zend_Registry::get($name);
			} catch (\Zend_Exception $e) {
				// Can not happen, avoid exception handling
				return new $name();
			}
		} else {
			$DB_obj = new $name();
			\Zend_Registry::set($name, $DB_obj);
			return $DB_obj;
		}
	}

	/**
	 * @return $this
	 */
	public static function getInstance() {
		return self::getSingleton(get_called_class());
	}
	
}