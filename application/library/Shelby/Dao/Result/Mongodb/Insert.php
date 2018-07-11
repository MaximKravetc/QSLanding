<?php

namespace Shelby\Dao\Result\Mongodb;

use MongoDB\InsertOneResult;

/**
 * @method \MongoDB\InsertOneResult getResultObject()
 */
class Insert extends DuiAbstract {

	public function __construct(InsertOneResult $res = null, Array $data = array()) {
		$this->result = $res;
		$this->data = $data;
	}
	
	/**
	 * Returns data inserted to the database
	 * 
	 * @return array
	 */
	public function getData() : array {
		if ($this->getResult() === true && empty($this->data['_id'])) {
			$this->data['_id'] = $this->getResultObject()->getInsertedId();
		}

		return parent::getData();
	}

	/**
	 * Return inserted document _id, generated or assigned
	 *
	 * @return mixed
	 */
	public function getInsertedId() {
		if ($this->getResult() === false) {
			return null;
		}

		return $this->result->getInsertedId();
	}
	
}