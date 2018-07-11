<?php
class Dao_Fulltext_Sphinx_Fhg extends Dao_Fulltext_Sphinx_Abstract {
	
	protected $_index_name = 'fhg';
	protected $_dao_name = 'Dao_Mongodb_List_Sbd_Sites_Fhg';
	
	protected $_field_weights = array(
		'title' => 20,
		'description' => 3,
		'model' => 15,
		'type' => 5
	);
	
}