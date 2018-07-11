<?php

namespace Shelby\Dao\Mongodb\Listing\Acl;

class Resources extends \Shelby\Dao\Mongodb\Listing\AbstractClass {
	
	protected $collection = 'acl.resources';

	protected $_id = self::PRIMARY_USER_DEFINED;
	
	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();
		
		$fields->addText('_id')->setRequired();
		$fields->addText('description');
		
		$fields->addArray('actions')->setText()->setDefault(array());
		
		return $fields;
	}
	
	protected function onPostDeleteEntry(&$id, Array &$extraParams, \MongoDB\DeleteResult $res) {
		if ($res->getDeletedCount() == 1) {
			// Delete this resource from all groups
			$dao_groups = new \Shelby\Dao\Mongodb\Listing\Acl\Groups();
			$dao_groups->setListFields(array('_id' => 1, 'resources' => 1));
			$list = $dao_groups->getList();
			
			foreach ($list as $el) {
				if (array_key_exists($id, $el['resources'])) {
					$group_id = $el['_id'];
					unset($el['resources'][$id], $el['_id']);
					$dao_groups->updateEntry($group_id, $el);
				}
			}
		}
	}

	/**
	 * @static
	 * @return \Shelby\Dao\Mongodb\Listing\Acl\Resources|\Shelby\Dao\AbstractClass
	 */
	public static function getInstance() : \Shelby\Dao\AbstractClass {
		return parent::getInstance();
	}
	
}