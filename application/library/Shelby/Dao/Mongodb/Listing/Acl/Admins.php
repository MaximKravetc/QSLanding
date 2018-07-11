<?php

namespace Shelby\Dao\Mongodb\Listing\Acl;

use Shelby\Dao\Builder\Search;

class Admins extends \Shelby\Dao\Mongodb\Listing\AbstractClass {
	
	protected $collection = 'acl.admins';

	protected $_id = self::PRIMARY_AUTOINCREMENT;
	
	protected $fields_list = array('_id' => 1, 'active' => 1, 'login' => 1, 'groups' => 1, 'name' => 1);
	
	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();
		
		$fields->addInt32('_id');
		$fields->addBoolean('active')->setDefault(false);
		$fields->addText('login')->setRequired();
		
		$fields->addBinary('password', \MongoDB\BSON\Binary::TYPE_MD5)
			->setRequired()
			->setPrepareUserFunction(
				function(&$value) {
					$value = md5($value, true);
				}
			);
			
		$fields->addArray('groups')->setDefault(array())->setInt32();
		
		$fields->addText('name');
		
		return $fields;
	}
	
	public function getEntryByLogin($login) : array {
		return $this->getFirstEntry(
			Search::instance()->equals('login', $login)
		)->get();
	}

	/**
	 * @static
	 * @return \Shelby\Dao\Mongodb\Listing\Acl\Admins|\Shelby\Dao\AbstractClass
	 */
	public static function getInstance() : \Shelby\Dao\AbstractClass {
		return parent::getInstance();
	}
	
}