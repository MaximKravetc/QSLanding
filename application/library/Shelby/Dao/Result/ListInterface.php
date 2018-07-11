<?php
/**
 * Results list interface object
 */

namespace Shelby\Dao\Result;

interface ListInterface extends \Iterator {
	
	public function get();
	
	public function resultsRequested();
	
	public function page();
	
	public function count($useCache = true);
	
	public function pages();
	
	public function numFrom();
	
	public function numTo();
	
}