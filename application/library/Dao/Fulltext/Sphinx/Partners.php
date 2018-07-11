<?php
class Dao_Fulltext_Sphinx_Partners extends Dao_Fulltext_Sphinx_Abstract {
	
	protected $_index_name = 'partners';
	protected $_dao_name = 'Dao_Mongodb_List_Sbd_Partners';
	
	protected $_field_weights = array(
		'name' => 20,
		'options' => 3,
		'domain' => 15
	);
	
}