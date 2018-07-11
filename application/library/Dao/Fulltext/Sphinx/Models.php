<?php
class Dao_Fulltext_Sphinx_Models extends Dao_Fulltext_Sphinx_Abstract {
	
	protected $_index_name = 'models';
	protected $_dao_name = 'Dao_Mongodb_List_BabeCake_Models';
	
	protected $_field_weights = array(
		'name' => 30,
		'aliases' => 10,
		'niches' => 5,
		'sites' => 5
	);
	
}