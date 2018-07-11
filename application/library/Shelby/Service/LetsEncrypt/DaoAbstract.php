<?php

namespace Shelby\Service\LetsEncrypt;

abstract class DaoAbstract extends \Shelby\Dao\Mongodb\Listing\AbstractClass {

	protected function initFields() : \Shelby\Dao\Mongodb\Field\Object {
		$fields = new \Shelby\Dao\Mongodb\Field\Object();

		// Certificate domains/subdomains list
		$fields->addArray('domains')->setText()->setRequired(true);

		// Domain verification challenge
		$challenge = $fields->addObject('challenge');
		$challenge->addText('token')->setDefault('');
		$challenge->addText('payload')->setDefault('');

		$cert = $fields->addObject('cert');
		$cert->addText('private')->setDefault('');
		$cert->addText('public')->setDefault('');
		$cert->addText('csr')->setDefault('');
		$cert->addArray('chain')->setText()->setDefault(array());

		$valid = $fields->addObject('valid');
		$valid->addInt32('from')->setDefault(0);
		$valid->addInt32('to')->setDefault(0);

		return $fields;
	}

}