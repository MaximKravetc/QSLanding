<?php

namespace Shelby\Dao\Result\Mongodb;

class Entry extends AbstractClass implements \ArrayAccess {
	
	/**
	 * @var array
	 */
	private $cache_result = null;

	/**
	 * Get entry from a database
	 * Performs an actual query
	 *
	 * @param string $key
	 * @return array
	 */
	public function get($key = null) {
		if (is_null($this->cache_result)) {
			$this->cache_result = current($this->cursor->toArray());
			if (!is_null($this->dataPrepareClosure)) {
				$cl = $this->dataPrepareClosure;
				$cl($this->cache_result);
			}
		}

		if (is_null($key)) {
			return $this->cache_result;
		} else {
			return $this->cache_result[$key];
		}
	}
	
	public function keyExists($key) : bool {
		$this->get();
		
		if (is_array($this->cache_result)) {
			return array_key_exists($key, $this->cache_result);
		} else {
			return false;
		}
	}
		
	/**
	 * Check if entry is exists in database
	 * @return boolean
	 */
	public function exists() : bool {
		$res = $this->get();
		if (empty($res)) {
			return false;
		}
		return true;
	}
	
	/* ArrayAccess Methods */
	public function offsetExists($offset) : bool {
		return $this->keyExists($offset);
	}
	public function offsetGet($offset) {
		return $this->get($offset);
	}
	public function offsetSet($offset, $value) {
		
	}
	public function offsetUnset($offset) {
		
	}
	
}