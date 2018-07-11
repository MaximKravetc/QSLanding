<?php

namespace Shelby\Dao\Mongodb\Field;

use MongoDB\BSON\ObjectID;

class Scalar implements InterfaceClass {
		
	private $name;

	/**
	 * @var InterfaceClass|null|int
	 */
	protected $type;
	
	private $required = false;
	
	private $defaultUse = false;
	private $defaultValue;
	
	protected $allow_null = true;
	
	protected $prepare = true;

	/**
	 * @var \Closure
	 */
	protected $prepareFunction = null;
	
	public function __construct(string $name = null, $type = null) {
		$this->name = $name;
		$this->type = $type;
	}
	
	public function setRequired(bool $required = true) : Scalar {
		$this->required = $required;
		return $this;
	}
	
	public function setDefault($value) : Scalar {
		$this->defaultUse = true;
		$this->defaultValue = $value;
		return $this;
	}
	
	public function setAllowNull(bool $allow = true) {
		$this->allow_null = $allow;
		return $this;
	}
	
	public function setPrepare($prepare = true) : Scalar {
		$this->prepare = $prepare;
		return $this;
	}

	/**
	 * $function must be in form function(&$value) {}
	 *
	 * @param \Closure $function
	 * @return Scalar
	 */
	public function setPrepareUserFunction(\Closure $function) : Scalar {
		$this->prepareFunction = $function;
		return $this;
	}
	
	public function isDefault() : bool {
		return $this->defaultUse;
	}
	
	public function getDefault() {
		return $this->defaultValue;
	}
	
	public function isRequired() : bool {
		return $this->required;
	}
	
	public function getField(string $name) {
		return null;
	}
	
	public function prepare($value) {
		if (!is_null($this->prepareFunction)) {
			$prepareFunction = $this->prepareFunction;
			$prepareFunction($value);
		}

		if ($this->prepare === true) {
			if (!is_null($value) || $this->allow_null === false) {
				switch ($this->type) {
					case self::TEXT:
						$value = (string)$value;
						break;
					case self::INT32:
						$value = (int)$value;
						break;
					case self::INT64:
						$value = (int)$value;
						break;
					case self::BOOLEAN:
						$value = (boolean)$value;
						break;
					case self::DOUBLE:
						$value = (float)$value;
						break;
					case self::MONGO_ID:
						$value = new ObjectID($value);
						break;
				}
			}
		}
		
		return $value;
	}
	
}