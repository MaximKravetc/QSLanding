<?php

namespace Dao\Mongodb\Listing\Quantumsystem;

class Subscribers extends AbstractClass {

	protected $collection = 'subscribers';

	protected $_id = self::PRIMARY_AUTOINCREMENT;

	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();

		$fields->addInt32('_id');

		$fields->addText('ip');
		$fields->addText('ua');
		$fields->addText('name')->setRequired();
		$fields->addText('email')->setRequired();

		$fields->addDate('date')->setDefault(new \MongoDB\BSON\UTCDateTime());

		return $fields;
	}

}
