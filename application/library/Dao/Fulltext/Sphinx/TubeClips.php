<?php
class Dao_Fulltext_Sphinx_TubeClips extends Dao_Fulltext_Sphinx_Abstract {
	
	protected $_index_name = 'tube_clips';
	protected $_dao_name = 'Dao_Mongodb_List_Sbd_Sites_TubeClips';
	
	protected $_field_weights = array(
		'title' => 20,
		'description' => 3,
		'model' => 15,
		'keywords' => 10
	);
	
}