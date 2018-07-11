<?php

namespace Shelby\Dao\Mongodb\Listing\Acl;

class Groups extends \Shelby\Dao\Mongodb\Listing\AbstractClass {
	
	protected $collection = 'acl.groups';

	protected $_id = self::PRIMARY_AUTOINCREMENT;
	
	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();
		
		$fields->addInt32('_id');
		$fields->addText('name')->setRequired();

		/*
		$resources = new \Shelby\Dao\Mongodb\Field\ArrayClass();
		$resources->setBoolean();
		$fields->addArray('resources', $resources)->setRequired();
		*/
		$fields->addMixed('resources');
		
		$fields->addText('description');
		
		return $fields;
	}

	/**
	 * @static
	 * @return \Shelby\Dao\Mongodb\Listing\Acl\Groups|\Shelby\Dao\AbstractClass
	 */
	public static function getInstance() : \Shelby\Dao\AbstractClass {
		return parent::getInstance();
	}
	
}