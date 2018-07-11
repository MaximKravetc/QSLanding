<?php
namespace Shelby\Dao\Result\Mongodb\Sphinx;

use Shelby\Dao\Result\Sphinx\AbstractClass;

class Listing extends AbstractClass implements \Shelby\Dao\Result\ListInterface {

	/**
	 * Dao List Object
	 * @var AbstractClass
	 */
	protected $dao;
	
	private $query;
	private $results_num;
	private $page;
	
	private $extraParams = array();
	
	private $search_results = null;
	private $search_results_attached = null;
	
	private $iterator_position = 0;
	
	public function __construct(\Shelby\Dao\Mongodb\Fulltext\Sphinx\AbstractClass $dao, $query, $results_num, $page, Array $extraParams) {
		$this->dao = $dao;
		
		$this->extraParams = $extraParams;
		
		$this->query = $query;
		$this->results_num = $results_num;
		$this->page = $page;
	}
	
	public function get() {
		if (!is_null($this->search_results_attached)) {
			return $this->search_results_attached;
		}
		$this->search_results_attached = array();

		$this->_performSearch();

/*		echo '<!-- ';
		print_r($this->search_results);
		echo ' -->'; */

		if ($this->search_results['total'] == 0) {
			return $this->search_results_attached;
		}
		
		$res_dao = $this->dao->attachDetails($this->search_results['matches'])->getAssoc();
		
		foreach ($this->search_results['matches'] as $id => $el) {
			if (isset($res_dao[$id])) {
				$this->search_results_attached[] = $res_dao[$id];
			}
		}
		
		return $this->search_results_attached;
	}
	
	
	public function resultsRequested() {
		return $this->results_num;
	}
	
	public function page() {
		return $this->page;
	}
	
	public function count($useCache = true) {
		$this->_performSearch();
		
		return $this->search_results['total_found'];
	}
	
	public function pages() {
		return ceil($this->count() / $this->results_num);
	}
	
	public function numFrom() {
		if ($this->count() < 1) {
			return 0;
		}
		
		return ($this->page - 1) * $this->results_num + 1;
	}
	
	public function numTo() {
		$res = $this->page * $this->results_num;
		if ($res > $this->count()) {
			return $this->count();
		}
		return $res;
	}
	
	public function getSphinxResults() {
		$this->_performSearch();
		
		return $this->search_results;
	}
	
	private function _performSearch() {
		if (is_null($this->search_results)) {
			$this->search_results = $this->dao->
				performSearch($this->query, $this->results_num, $this->page, $this->extraParams);
		}
	}
	
	// Iterator methods
	public function current() {
		return $this->search_results_attached[$this->iterator_position];
	}
	public function rewind() {
		$this->get();
		$this->iterator_position = 0;
	}
	public function key() {
		return $this->iterator_position;
	}
	public function next() {
		$this->iterator_position++;
	}
	public function valid() {
		return isset($this->search_results_attached[$this->iterator_position]);
	}
}