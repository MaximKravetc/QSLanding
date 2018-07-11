<?php

namespace Shelby\Dao\Result\Mongodb;

use MongoDB\BSON\ObjectID;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;

abstract class AbstractClass {

	/**
	 * @var Cursor
	 */
	protected $cursor;

	protected $extraParams;

	/**
	 * @var Collection
	 */
	protected $collection;

	/**
	 * Data processing closure
	 *
	 * @var \Closure
	 */
	protected $dataPrepareClosure = null;

	/**
	 * @param Cursor $cursor
	 * @param array $extraParams
	 * @param Collection $collection
	 */
	public function __construct(Cursor $cursor, Array $extraParams, Collection $collection) {
		$this->cursor = $cursor;
		$this->extraParams = $extraParams;
		$this->collection = $collection;
	}

	public function getSearchParams() : array {
		return $this->extraParams;
	}

	public function getSearchParamValue($column) {
		foreach($this->extraParams as $el) {
			if ($el['field'] == $column) {
				return $el['value'];
			}
		}

		return null;
	}

	/**
	 * Return result cursor Mongo object
	 *
	 * @return Cursor
	 */
	public function getCursor() : Cursor {
		return $this->cursor;
	}

	/**
	 * Set a closure function to prepare data after retrieval
	 *
	 * @param \Closure $function
	 */
	public function setDataPrepareFunction(\Closure $function) {
		$this->dataPrepareClosure = $function;
	}

	/**
	 * Extract timestamp from MongoDB ObjectID
	 *
	 * @param ObjectID $id
	 * @return int
	 */
	public static function mongoId2TS(ObjectID $id) : int {
		return hexdec(substr((string)$id, 0, 8));
	}

	/**
	 * Convert stdClass to array recursively
	 * @see https://jira.mongodb.org/browse/PHPC-314
	 *
	 * @param array|\stdClass|null $array
	 * @return array
	 */
	public static function stdClass2Array($array) : array {
		if (is_null($array)) {
			return array();
		}

		if (is_array($array)) {
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					$array[$key] = self::stdClass2Array($value);
				}
				if ($value instanceof \stdClass) {
					$array[$key] = self::stdClass2Array((array)$value);
				}
			}
		}
		if ($array instanceof \stdClass) {
			return self::stdClass2Array((array)$array);
		}

		return $array;
	}

}