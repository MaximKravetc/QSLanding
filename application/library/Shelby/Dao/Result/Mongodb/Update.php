<?php

namespace Shelby\Dao\Result\Mongodb;

use MongoDB\UpdateResult;

/**
 * @method \MongoDB\UpdateResult getResultObject()
 */
class Update extends DuiAbstract {
	
	public function __construct(UpdateResult $res = null, array $data = array()) {
		$this->result = $res;
		$this->data = $data;
	}

}