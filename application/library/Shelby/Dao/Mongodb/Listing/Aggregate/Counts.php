<?php

namespace Shelby\Dao\Mongodb\Listing\Aggregate;

use Shelby\Dao\Mongodb\Field\Object;
use Shelby\Dao\Mongodb\Listing\AbstractClass;

class Counts extends AbstractClass {
	
	protected $collection = 'aggregate.counts';
	
	protected $safe = false;

	/**
	 * I had to create a user function to convert "query" part of PK to string because
	 * these two arrays are the same for mongo:
	 * {'s6n4m' : 12}
	 * and
	 * {'n4m' : 12}
	 *
	 * @return \Shelby\Dao\Mongodb\Field\Object
	 */
	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new Object();
		
		$_id = $fields->addObject('_id');
		$_id->addText('ns')->setRequired();
		$_id->addArray('query')->setMixed()->setPrepareUserFunction(
			function (&$value) {
				$res = '';
				ksort($value, SORT_STRING);
				array_walk_recursive(
					$value,
					function($val, $key) use (&$res) {
						$res .= $key . ':' . $val . ';';
					}
				);
				
				$value = $res;
			}
		);
		$_id->addBoolean('approx');
		
		$fields->addDate('date')->setDefault(new \MongoDB\BSON\UTCDateTime(time()*1000));
		$fields->addInt32('count')->setRequired();
		
		return $fields;
	}

	/**
	 * @static
	 * @return \Shelby\Dao\AbstractClass|Counts
	 */
	public static function getInstance() : \Shelby\Dao\AbstractClass {
		return \Shelby\Dao\AbstractClass::getInstance();
	}

}