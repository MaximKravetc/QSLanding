<?php

namespace Shelby\Dao\Mongodb\Listing\Aggregate;

class Groups extends \Shelby\Dao\Mongodb\Listing\AbstractClass {
	
	protected $collection = 'aggregate.groups';

	protected $safe = false;
	
	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();
		
		$_id = $fields->addObject('_id');
		$_id->addText('ns')->setRequired();
		$_id->addArray('query')->setMixed()->setPrepareUserFunction(
			function (&$value) {
				$res = '';
				array_walk_recursive(
					$value,
					function($val, $key) use (&$res) {
						$res .= $key . ':' . $val . ';';
					}
				);
				
				$value = $res;
			}
		);
		
		$fields->addArray('condition')->setMixed()->setRequired();
		$fields->addDate('date')->setDefault(new \MongoDB\BSON\UTCDateTime(time()*1000));
		$fields->addArray('groups')->setMixed()->setRequired();
		
		return $fields;
	}
	
	/**
	 * Grouping and caching
	 * 
	 * @param string $collection
	 * @param array $condition
	 * @param boolean $recalculate
	 * @param \Closure $grouper
	 * @return array
	 */
	public function group($collection, Array $condition = array(), $recalculate = false, \Closure $grouper) {
		$query = $this->_getQuery($condition);
		
		$id = array(
			'ns' => $collection,
			'query' => $query
		);
		
		if ($recalculate == false) {
			$res_groups = $this->getEntry($id)->get();
			if (!empty($res_groups)) {
				return $res_groups['groups'];
			}
		}
		
		// Grouping using user-provided function
		$res = $grouper($query);
		
		$this->replaceEntry(
				array(
					'_id' => $id,
					'condition' => $condition,
					'groups' => $res
				)
			);
		
		return $res;
	}

	/**
	 * @static
	 * @return \Shelby\Dao\AbstractClass|\Shelby\Dao\Mongodb\Listing\Aggregate\Groups
	 */
	public static function getInstance() : \Shelby\Dao\AbstractClass {
		return \Shelby\Dao\AbstractClass::getInstance();
	}

}