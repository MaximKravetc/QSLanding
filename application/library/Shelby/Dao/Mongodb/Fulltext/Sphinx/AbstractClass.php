<?php

namespace Shelby\Dao\Mongodb\Fulltext\Sphinx;

abstract class AbstractClass extends \Shelby\Dao\AbstractClass {
	
	protected $_index_name = null;
	protected $_dao_name = null;
	
	protected $_field_weights = array();
	
	/**
	 * @var \Shelby\Dao\Mongodb\Listing\AbstractClass
	 */
	protected $_dao_obj = null;
	
	/**
	 * @var \SphinxClient
	 */
	protected $_sphinx_obj = null;

	protected static $_options = array();
	
	public static function setOptions(Array $options) {
		self::$_options = $options;
	}
	
	/**
	 * General FullText site search
	 * 
	 * @param string $query
	 * @param int $results
	 * @param int $page
	 * @param array $search
	 * @return array
	 */
	public function performSearch($query, $results, $page, Array $search) {
		$this->_connect();
		
		$this->_sphinx_obj->setMatchMode(SPH_MATCH_EXTENDED2);
		$this->_sphinx_obj->setRankingMode(SPH_RANK_PROXIMITY_BM25);
		
		$offset = ($page - 1) * $results;
		$this->_sphinx_obj->setLimits($offset, $results, self::$_options['max_matches']); //, 100000);
		
		$this->_addFilter($search);
		
		$query = $this->_escape($query);
		return $this->_sphinx_obj->query($query, $this->_index_name);
	}
		
	/**
	 * Attach data from DB to search results
	 * 
	 * @param array $matches
	 * @return \Shelby\Dao\Result\Mongodb\Listing
	 */
	public function attachDetails(Array $matches) {
		$ids_array = array();
		foreach ($matches as $id => $el) {
			$ids_array[] = $id;
		}

		$extraParams = array(
			array('field' => '_id', 'value' => $ids_array, 'type' => 'in', 'prepare' => false)
		);
		
		return $this->_dao_obj->getList(null, null, $extraParams);
	}
	
	public function search($query, $results, $page, Array $search) {
		return new \Shelby\Dao\Result\Mongodb\Sphinx\Listing($this, $query, $results, $page, $search);
	}
	
	
	/**
	 * Reset FullText search engine
	 * and prepare it for the next search query
	 */
	public function cleanup() {
		if (!is_null($this->_sphinx_obj)) {
			$this->_sphinx_obj->resetFilters();
		}
	}
	
	protected function _connect() {
		if (is_null($this->_sphinx_obj)) {
			$this->_sphinx_obj = new SphinxClient();
			$this->_sphinx_obj->setServer(self::$_options['host'], (int)self::$_options['port']);
			$this->_sphinx_obj->setMaxQueryTime((int)self::$_options['max_query_time']);
			$this->_sphinx_obj->setFieldWeights($this->_field_weights);
			
			if (!is_null($this->_dao_name)) {
				$this->_dao_obj = new $this->_dao_name;
			}
		}
	}
	
	protected function _escape($query) {
		$query = trim(str_replace(
			array('|', '-', '!', '@', '~', '"', "'", '/', '(', ')', ':', '.', ','),
			' ',
			$query
		));
		if(empty($query)) {
			$query = 'EMPTYQUERYFORBITTEN';
		}
		return $query;
	}
	
	protected function _addFilter(Array $search) {
		$ranges = array();
		
		foreach ($search as $el) {
			switch ($el['type']) {
				case '=':
					$this->_sphinx_obj->setFilter($el['field'], (array)$el['value']);
					break;
				case '>=':
					$ranges[$el['column']]['min'] = $el['value'];
					break;
				case '<=':
					$ranges[$el['column']]['max'] = $el['value'];
					break;
			}
		}
		
		foreach ($ranges as $col => $el) {
			$min = (isset($el['min']) ? $el['min']:0);
			$max = (isset($el['max']) ? $el['max']:2147483647);
			
			if ($col == 'id') {
				$this->_sphinx_obj->setIDRange($min, $max);
			} else {
				$this->_sphinx_obj->setFilterRange($col, $min, $max);
			}
		}
	}
	
}