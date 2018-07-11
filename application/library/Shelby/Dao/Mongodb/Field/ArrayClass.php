<?php

namespace Shelby\Dao\Mongodb\Field;

class ArrayClass extends Scalar {

	/**
	 * Allow associative array by default
	 *
	 * @var bool
	 */
	private $allow_assoc = true;

	/**
	 * Allow associative arrays to be saved as objects to the MongoDB
	 *
	 * @param bool $allow
	 * @return ArrayClass
	 */
	public function setAllowAssoc(bool $allow) : ArrayClass {
		$this->allow_assoc = $allow;
		return $this;
	}

	/**
	 * @return ArrayClass
	 */
	public function setInt32() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::INT32);
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setInt64() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::INT64);
		return $this;
	}
	
	/**
	 * @param int $type
	 * @return ArrayClass
	 */
	public function setBinary(int $type = \MongoDB\BSON\Binary::TYPE_GENERIC) : ArrayClass {
		$this->type = new Binary(null, $type);
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setText() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::TEXT);
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setDate() : ArrayClass {
		$this->type = new Date();
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setBoolean() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::BOOLEAN);
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setDouble() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::DOUBLE);
		return $this;
	}
	
	/**
	 * @return ArrayClass
	 */
	public function setMixed() : ArrayClass {
		$this->type = new Scalar(null, InterfaceClass::MIXED);
		return $this;
	}
	
	public function getField(string $name) {
		return $this->type->getField($name);
	}

	/**
	 * @param $value
	 * @return ArrayClass|Scalar
	 */
	public function setDefault($value) : Scalar {
		return parent::setDefault($value);
	}
	
	public function prepare($value) {
		if ($this->prepare === false) {
			return $value;
		}

		if (!is_array($value)) {
			$value = $this->type->prepare($value);
		} else {
			if (!isset($value[0]) && !empty($value) && $this->allow_assoc === false) {
				// Associative array, must be array element search, convert it to the regular array
				$value = array_values($value);
			}
			// Regular or empty array, looks like new entry
			foreach ($value as &$el) {
				$el = $this->type->prepare($el);
			}
		}

		if (!is_null($this->prepareFunction)) {
			$prepareFunction = $this->prepareFunction;
			$prepareFunction($value);
		}
		
		return $value;
	}

}