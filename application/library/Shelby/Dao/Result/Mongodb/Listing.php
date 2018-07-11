<?php

namespace Shelby\Dao\Result\Mongodb;

use Shelby\Dao\Result\ListInterface;

class Listing extends AbstractClass implements ListInterface {
	
	const COUNT_APPROX_MAX_RESULTS = 10000;
	
	private $results_num;
	private $page;
	private $sortBy;
	
	private $cache_count = -1;
	private $cache_count_approx = -1;
	
	private $cache_get = null;
	private $cache_get_assoc = null;
	
	private $time_count_cache = 43200; // 12 hours

	private $query = array();

	/**
	 * @var \IteratorIterator
	 */
	private $cursor_iterator = null;

	/**
	 * @param \MongoDB\Driver\Cursor $cursor
	 * @param int $results_num
	 * @param int $page
	 * @param array $extraParams
	 * @param array $sortBy
	 * @param \MongoDB\Collection $collection
	 * @param array $query
	 */
	public function __construct(\MongoDB\Driver\Cursor $cursor, $results_num, $page, Array $extraParams, Array $sortBy, \MongoDB\Collection $collection, Array $query) {
		parent::__construct($cursor, $extraParams, $collection);
		$this->results_num = $results_num;
		$this->page = $page;
		$this->sortBy = $sortBy;
		$this->query = $query;
	}

	/**
	 * @return array
	 */
	public function get() : array {
		if (is_null($this->cache_get)) {
			// Do not use iterator keys as index to avoid problems with complex keys (_id)s
			$this->cache_get = $this->cursor->toArray();

			if (!is_null($this->dataPrepareClosure)) {
				$cl = $this->dataPrepareClosure;
				foreach ($this->cache_get as &$val) {
					$cl($val);
				}
			}
		}
		return $this->cache_get;
	}
	
	public function isEmpty() : bool {
		$this->get();
		return empty($this->cache_get);
	}

	/**
	 * @return array
	 */
	public function getAssoc() : array {
		if (is_null($this->cache_get_assoc)) {
			$this->cache_get_assoc = array();
			foreach ($this->cursor as $val) {
				$this->prepare($val);
				$this->cache_get_assoc[$val['_id']] = $val;
			}
		}
		return $this->cache_get_assoc;
	}
	
	public function isEmptyAssoc() : bool {
		$this->getAssoc();
		return empty($this->cache_get_assoc);
	}
		
	public function resultsRequested() {
		return $this->results_num;
	}
	
	public function page() {
		return $this->page;
	}
	
	public function sortOrder() : array {
		return $this->sortBy;
	}
	
	public function numFrom() : int {
		if ($this->count() < 1) {
			return 0;
		}
		
		return ($this->page - 1) * $this->results_num + 1;
	}
	
	public function numTo() : int {
		$res = $this->page * $this->results_num;
		if ($res > $this->count()) {
			return $this->count();
		}
		return $res;
	}
	
	public function numFromApprox() : int {
		if ($this->countApprox() < 1) {
			return 0;
		}
	
		return ($this->page - 1) * $this->results_num + 1;
	}
	
	public function numToApprox() : int {
		$res = $this->page * $this->results_num;
		if ($res > $this->countApprox()) {
			return $this->countApprox();
		}
		return $res;
	}
	
	/**
	 * Exact results count
	 *
	 * @param bool
	 * @return int
	 */
	public function count($useCache = true) : int {
		if ($this->cache_count != -1) {
			return $this->cache_count;
		}

		$id = array();
		$countsObj = null;
		if ($useCache === true) {
			$countsObj = new \Shelby\Dao\Mongodb\Listing\Aggregate\Counts();

			$id = array(
				'ns' => $this->collection->getNamespace(),
				'query' => $this->query
			);
			$res_count = $countsObj->getEntry($id)->get();
			/** @var \MongoDB\BSON\UTCDateTime $date */
			$date = $res_count['date'];
			if (!empty($res_count) && $date->toDateTime()->getTimestamp() > (time() - $this->time_count_cache)) {
				$this->cache_count = $res_count['count'];
				return $this->cache_count;
			}
		}

		$this->cache_count = $this->collection->count($this->query);
		
		if ($useCache === true) {
			$data = array(
				'_id' => $id,
				'count' => $this->cache_count
			);
			try {
				$countsObj->replaceEntry($data);
			} catch (\Exception $e) {
				// Race condition exception fix
			}
		}
		
		return $this->cache_count;
	}
	
	public function countApprox($useCache = true) : int {
		if ($this->cache_count_approx != -1) {
			return $this->cache_count_approx;
		}

		$id = array();
		$countsObj = null;
		if ($useCache === true) {
			$countsObj = new \Shelby\Dao\Mongodb\Listing\Aggregate\Counts();
			
			$id = array(
				'ns' => $this->collection->getNamespace(),
				'query' => $this->query,
				'approx' => true
			);
			$res_count = $countsObj->getEntry($id)->get();
			// Cached results are valid if they are not too old or if they are equal to a maximum value
			/** @var \MongoDB\BSON\UTCDateTime $date */
			$date = $res_count['date'];
			if (!empty($res_count) &&
					($res_count['count'] == self::COUNT_APPROX_MAX_RESULTS ||
						$date->toDateTime()->getTimestamp() > (time() - $this->time_count_cache))) {
			
				$this->cache_count_approx = $res_count['count'];
				return $this->cache_count_approx;
			}
		}

		$this->cache_count_approx = $this->collection
			->count(
				$this->query,
				array(
					'limit' => self::COUNT_APPROX_MAX_RESULTS
				)
			);

		if ($useCache === true) {
			$data = array(
				'_id' => $id,
				'count' => $this->cache_count_approx
			);
			try {
				$countsObj->replaceEntry($data);
			} catch (\Exception $e) {
				// Race condition exception fix
			}
		}
		
		return $this->cache_count_approx;
	}

	/**
	 * Exact pages count
	 *
	 * @param bool $useCache
	 * @return int
	 */
	public function pages($useCache = true) : int {
		if (is_null($this->results_num)) {
			return -1;
		}
		
		return (int)ceil($this->count($useCache) / $this->results_num);
	}
	
	public function pagesApprox($useCache = true) : int {
		if (is_null($this->results_num)) {
			return -1;
		}
		
		return (int)ceil($this->countApprox($useCache) / $this->results_num);
	}

	/**
	 * Set cache expiration time for count requests in seconds
	 * Default time is 43200 seconds (12 hours)
	 *
	 * @param int $time
	 */
	public function setCountCacheExpireTime($time) {
		$this->time_count_cache = intval($time);
	}

	/**
	 * Apply user defined function to the data element
	 *
	 * @param array $val
	 */
	public function prepare(Array &$val) {
		if (!is_null($this->dataPrepareClosure)) {
			$cl = $this->dataPrepareClosure;
			$cl($val);
		}
	}

	// Iterator methods
	public function current() {
		$val = $this->getCursorIterator()->current();

		if (!is_null($this->dataPrepareClosure)) {
			$cl = $this->dataPrepareClosure;
			$cl($val);
		}

		return $val;
	}
	public function rewind() {
		$this->getCursorIterator()->rewind();
	}
	public function key() {
		return $this->getCursorIterator()->key();
	}
	public function next() {
		$this->getCursorIterator()->next();
	}
	public function valid() {
		return $this->getCursorIterator()->valid();
	}

	/**
	 * The only way to use new MongoDB Cursor as iterator
	 * Do not try to use original cursor after IteratorIterator was applied
	 *
	 * @return \IteratorIterator
	 */
	private function getCursorIterator() {
		if (is_null($this->cursor_iterator)) {
			$this->cursor_iterator = new \IteratorIterator($this->cursor);
		}

		return $this->cursor_iterator;
	}
}