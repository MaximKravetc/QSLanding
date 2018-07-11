<?php

namespace Shelby\Dao\Mongodb\Field;

use MongoDB\BSON\UTCDateTime;

class Date extends Scalar {

	public function __construct() {
		// Nothing to do here for this data type
	}
	
	public function prepare($value) {
		if (!($value instanceof UTCDateTime)) {
			if (is_string($value)) {
				$value = strtotime($value);
			}
			$value = new UTCDateTime($value*1000);
		}
		
		return $value;
	}
	
}