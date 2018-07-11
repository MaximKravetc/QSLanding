<?php

namespace Shelby\Dao\Result\Mongodb;

use MongoDB\DeleteResult;

/**
 * @method \MongoDB\DeleteResult getResultObject()
 */
class Delete extends DuiAbstract {
	
	public function __construct(DeleteResult $res = null) {
		$this->result = $res;
	}
	
}