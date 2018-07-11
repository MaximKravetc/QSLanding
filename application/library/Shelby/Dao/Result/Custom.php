<?php

namespace Shelby\Dao\Result;

use Shelby\Dao\AbstractClass;

class Custom {
	
	/**
	 * @var \Closure
	 */
	private $callback;
	
	/**
	 * @var AbstractClass
	 */
	private $dao;
	
	private $cache = null;
	
	public function __construct(\Closure $callback, AbstractClass $dao) {
		$this->callback = $callback;
		$this->dao = $dao;
	}
	
	public function get() {
		if (!is_null($this->cache)) {
			return $this->cache;
		}
		
		$callback = $this->callback;
		$this->cache = $callback($this->dao);
		
		return $this->cache;
	}
	
}