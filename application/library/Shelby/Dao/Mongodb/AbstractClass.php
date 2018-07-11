<?php

namespace Shelby\Dao\Mongodb;

use MongoDB\Database;

abstract class AbstractClass extends \Shelby\Dao\AbstractClass {
	
	/**
	 * @var array
	 */
	protected static $options;
	
	/**
	 * @var \MongoDB\Client
	 */
	private static $connection = null;
	
	/**
	 * @var Database
	 */
	private $database = null;
	
	public static function init(array $options) {
		self::$options = $options;
	}
	
	/**
	 * @return \MongoDB\Client
	 */
	public static function getConnection() : \MongoDB\Client {
		if (is_null(self::$connection)) {
			$options = array();
			if (!empty(self::$options['replicaSet'])) {
				$options['replicaSet'] = self::$options['replicaSet'];
			}
			if (!empty(self::$options['readPreference'])) {
				$options['readPreference'] = self::$options['readPreference'];
			}
			if (!empty(self::$options['connectTimeoutMS'])) {
				$options['connectTimeoutMS'] = (int)self::$options['connectTimeoutMS'];
			}

			$uri = 'mongodb://';
			if (is_array(self::$options['host'])) {
				$uri .= implode(',', self::$options['host']);
			} else {
				$uri .= self::$options['host'];
			}

			self::$connection = new \MongoDB\Client(
				$uri,
				$options,
				array(
					'typeMap' => array(
						'array' => 'array',
						'document' => 'array',
						'root' => 'array'
					)
				)
			);
		}
		
		return self::$connection;
	}

	/**
	 * @return Database
	 */
	public function getDatabase() : Database {
		if (is_null($this->database)) {
			$this->database = self::getConnection()->selectDatabase(self::$options['dbname']);
		}
		
		return $this->database;
	}

	/**
	 * Set database for the current Dao instance
	 *
	 * @param Database $db
	 */
	public function setDatabase(Database $db) {
		$this->database = $db;
	}

}