<?php

namespace Shelby\Dao\Result\Mongodb;

abstract class DuiAbstract {

	const CODE_SUCCESS = 1;
	const CODE_FAIL = 0;

	/**
	 * @var \MongoDB\DeleteResult|\MongoDB\InsertOneResult|\MongoDB\UpdateResult|null
	 */
	protected $result;

	protected $data;

	/**
	 * @var int|null
	 */
	protected $code_custom = null;

	/**
	 * Return whether this operation was acknowledged by the server.
	 *
	 * @return bool
	 */
	public function getResult() : bool {
		if (is_null($this->result)) {
			return false;
		}

		return $this->result->isAcknowledged();
	}

	/**
	 * Return original MongoDB result object
	 */
	public function getResultObject() {
		return $this->result;
	}

	/**
	 * Return affected database data
	 *
	 * @return array
	 */
	public function getData() : array {
		return $this->data;
	}

	/**
	 * Set custom result code
	 *
	 * @param int $code
	 * @return $this
	 */
	public function setCode(int $code) : DuiAbstract {
		$this->code_custom = $code;

		return $this;
	}

	/**
	 * Get custom result code
	 * If not specified, will return standard code
	 * 1 - success
	 * 0 - failed
	 *
	 * @return int
	 */
	public function getCode() : int {
		if (!is_null($this->code_custom)) {
			return $this->code_custom;
		}
		if ($this->getResult() === true) {
			return 1;
		}

		return 0;
	}

	/**
	 * @note PHP Catchable fatal error:
	 *  Method \Shelby\Dao\Result\Mongodb\Insert::__toString() must return a string value
	 *
	 * @return string
	 */
	public function __toString() : string {
		return (string)$this->result;
	}

}