<?php
class Dao_Fulltext_Sphinx_Sites extends Dao_Fulltext_Sphinx_Abstract {
	
	protected $_index_name = 'sites';
	protected $_dao_name = 'Dao_Mongodb_List_Sbd_Sites';
	
	protected $_field_weights = array(
		'name' => 20,
		'partner_name' => 10,
		'niches' => 5,
		'options' => 3,
		'partner_options' => 1,
		'domain' => 15,
		'partner_domain' => 15
	);
	
}