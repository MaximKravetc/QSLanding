<?php

namespace Dao\Mongodb\Listing\Quantumsystem;

use MongoDB\Database;

abstract class AbstractClass extends \Shelby\Dao\Mongodb\Listing\AbstractClass {
	
	/**
	 * @var Database
	 */
	private $database = null;
	
	public function getDatabase() : Database {
		if (is_null($this->database)) {
			$this->database = self::getConnection()->selectDatabase('quantumsystem');
		}
	
		return $this->database;
	}

}
