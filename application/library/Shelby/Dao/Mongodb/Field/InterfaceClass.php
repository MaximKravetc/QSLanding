<?php
namespace Shelby\Dao\Mongodb\Field;

interface InterfaceClass {
	
	const MONGO_ID = 1;
	const TEXT = 2;
	const INT32 = 3;
	const INT64 = 4;
	const DATE = 5;
	const BOOLEAN = 6;
	const DOUBLE = 7;
	const MIXED = 8;

	const MONGO_ARRAY = 10;
	
	public function prepare($value);
	
	public function getField(string $name);
	
}