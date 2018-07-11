<?php

namespace Shelby\Dao\Mongodb\Field;

class Object implements InterfaceClass {

	/**
	 * @var array
	 */
	private $fields = array();

	/**
	 * @var \Closure|null
	 */
	protected $prepareFunction = null;

	/**
	 * General add method for scalar types
	 *
	 * @param string $name
	 * @param int $type
	 * @return Scalar
	 */
	private function add(string $name, $type) : Scalar {
		$field = new Scalar($name, $type);
		$this->fields[$name] = $field;
		return $field;
	}

	/**
	 * Adds driver-generated _id field \MongoDB\BSON\ObjectID
	 *
	 * @param string $name
	 * @return Scalar
	 */
	public function addMongoId(string $name = '_id') : Scalar {
		return $this->add($name, InterfaceClass::MONGO_ID);
	}

	/**
	 * Adds and object to the current object as an element
	 * 
	 * @param string $name
	 * @return \Shelby\Dao\Mongodb\Field\Object
	 */
	public function addObject(string $name) : \Shelby\Dao\Mongodb\Field\Object {
		$field = new Object();
		$this->fields[$name] = $field;
		return $field;
	}

	/**
	 * Adds an array element to the object.
	 * The first parameter is the object's name.
	 * The second one is it's elements type. Can be null, then it must be set via any of the
	 * Dao_Mongodb_Field_Array::set* methods
	 *
	 * @param string                            $name
	 * @param InterfaceClass|null $field
	 * @return ArrayClass
	 */
	public function addArray(string $name, InterfaceClass $field = null) : ArrayClass {
		$field = new ArrayClass($name, $field);
		$this->fields[$name] = $field;
		return $field;
	}

	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addInt32(string $name) : Scalar {
		return $this->add($name, InterfaceClass::INT32);
	}

	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addInt64(string $name) : Scalar {
		return $this->add($name, InterfaceClass::INT64);
	}

	/**
	 * Binary field
	 * type is one of the \MongoDB\BSON\Binary::TYPE_* types
	 * 
	 * @param string $name
	 * @param int $type
	 * @return Binary
	 */
	public function addBinary(string $name, $type = \MongoDB\BSON\Binary::TYPE_GENERIC) : Binary {
		$field = new Binary($name, $type);
		$this->fields[$name] = $field;
		return $field;
	}

	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addText(string $name) : Scalar {
		return $this->add($name, InterfaceClass::TEXT);
	}

	/**
	 * @param string $name
	 * @return Date
	 */
	public function addDate(string $name) : Date {
		$field = new Date();
		$this->fields[$name] = $field;
		return $field;
	}

	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addBoolean(string $name) : Scalar {
		return $this->add($name, InterfaceClass::BOOLEAN);
	}

	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addDouble(string $name) : Scalar {
		return $this->add($name, InterfaceClass::DOUBLE);
	}
	
	/**
	 * @param string $name
	 * @return Scalar
	 */
	public function addMixed(string $name) : Scalar {
		return $this->add($name, InterfaceClass::MIXED);
	}
	
	public function setPrepareUserFunction(\Closure $function) : \Shelby\Dao\Mongodb\Field\Object {
		$this->prepareFunction = $function;
		return $this;
	}

	/**
	 * @param $name
	 * @return InterfaceClass|null
	 */
	public function getField(string $name) {
		if (isset($this->fields[$name])) {
			return $this->fields[$name];
		}
		
		// Trying to extract field.key notation
		$names_array = explode('.', $name);
		$fObj = null;
		foreach ($names_array as $key => $n) {
			if ($key == 0) {
				if (isset($this->fields[$n])) {
					$fObj = $this->fields[$n];
				} else {
					return null;
				}
			} elseif (ctype_digit($n)) {
				// Array access by index, e.g. "hosts.0.status"
				continue;
			} elseif (!is_null($fObj)) {
				/** @var InterfaceClass $fObj */
				$fObj = $fObj->getField($n);
			}
		}
		
		return $fObj;
	}

	/**
	 * Return object fields array
	 *
	 * @return array
	 */
	public function getFields() : array {
		return $this->fields;
	}

	/**
	 * Prepares a hash-map
	 *
	 * @param array $data
	 * @throws Exception
	 * @return array
	 */
	public function prepare($data) : array {
		$data_result = array();
		foreach ($this->fields as $key => $field) {
			if (is_array($data) && array_key_exists($key, $data) === true) {
				/** @var InterfaceClass $field */
				$data_result[$key] = $field->prepare($data[$key]);
			} else {
				if ($field instanceof Scalar) {
					/** @var Scalar $field */
					if ($field->isDefault() === true) {
						$data_result[$key] = $field->getDefault();
					} else if ($field->isRequired() === true) {
						throw new Exception('Required field not set - ' . $key);
					}
				} elseif ($field instanceof Object) { // Nested objects
					/** @var Object $field */
					$tmp = $field->prepare(array());
					if (!empty($tmp)) {
						$data_result[$key] = $tmp;
					}
				}
			}
		}
		
		if (!is_null($this->prepareFunction)) {
			$prepareFunction = $this->prepareFunction;
			$prepareFunction($data_result);
		}

		return $data_result;
	}
	
}