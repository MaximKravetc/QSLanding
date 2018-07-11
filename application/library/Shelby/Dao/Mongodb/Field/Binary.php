<?php

namespace Shelby\Dao\Mongodb\Field;

class Binary extends Scalar {

	/**
	 * Binary field
	 * type is one of the \MongoDB\BSON\Binary::TYPE_* types
	 * 
	 * @param string $name
	 * @param int $type
	 */
	public function __construct(string $name = null, int $type) {
		parent::__construct($name, $type);
	}
	
	public function prepare($value) {
		if ($this->prepare === false) {
			return $value;
		}
				
		if (!is_null($this->prepareFunction)) {
			$prepareFunction = $this->prepareFunction;
			$prepareFunction($value);
		}
		
		if (!($value instanceof \MongoDB\BSON\Binary)) {
			$value = new \MongoDB\BSON\Binary($value, $this->type);
		}
		
		return $value;
	}
	
}