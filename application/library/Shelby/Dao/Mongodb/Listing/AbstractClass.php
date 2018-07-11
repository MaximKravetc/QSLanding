<?php

namespace Shelby\Dao\Mongodb\Listing;

use Shelby\Dao\Builder\Search;
use Shelby\Dao\Builder\Sort;
use Shelby\Dao\Result\Mongodb\Delete;
use Shelby\Dao\Result\Mongodb\Entry;
use Shelby\Dao\Result\Mongodb\Insert;
use Shelby\Dao\Result\Mongodb\Listing;
use Shelby\Dao\Result\Mongodb\Update;

abstract class AbstractClass extends \Shelby\Dao\Mongodb\AbstractClass {
	
	const PRIMARY_AUTOINCREMENT = 1;
	const PRIMARY_DEFAULT = 2;
	const PRIMARY_USER_DEFINED = 3;
	
	/**
	 * Collection name to use
	 * 
	 * @var string
	 */
	protected $collection;
	
	/**
	 * MongoCollection instance
	 * 
	 * @var \MongoDB\Collection
	 */
	private $_collection_obj = null;
	
	/**
	 * Fields description class
	 * 
	 * @var \Shelby\Dao\Mongodb\Field\Object
	 */
	protected $fields = null;
	
	/**
	 * List of fields to retrieve on getList
	 * Leave it empty to retrieve all fields from the collection
	 * Example: array('_id' => 0, 'field' => 1)
	 * Al
	 * @var array
	 */
	protected $fields_list = array();
	
	/**
	 * Primary id generation type
	 * self::PRIMARY_AUTOINCREMENT - using AI emulation
	 * self::PRIMARY_DEFAULT - using default internal ObjectId
	 * 
	 * @var int
	 */
	protected $_id = self::PRIMARY_AUTOINCREMENT;
	
	/**
	 * Default sort order
	 * @var array
	 */
	protected $sort = array();
	
	/**
	 * Whether to use safe operations or not
	 * By default we are using safe operations and waiting for the server response before proceed
	 * 
	 * @var boolean
	 */
	protected $safe = true;

	/**
	 * The number of documents to return per batch in getList function
	 *
	 * @var int
	 */
	protected $batchSize = 0;

	/**
	 * Initialize collection fields
	 * Must be implemented in each List method
	 * 
	 * @return \Shelby\Dao\Mongodb\Field\Object
	 */
	abstract protected function initFields() : \Shelby\Dao\Mongodb\Field\Object;

	/**
	 * Set Safe flag for all modification operations
	 * such as insert/replace/update/delete
	 *
	 * @param boolean $s
	 */
	public function setSafe(bool $s) {
		$this->safe = (bool)$s;
	}

	/**
	 * Get Safe mode for insert/replace/update/delete operations
	 *
	 * @return bool
	 */
	public function getSafe() : bool {
		return $this->safe;
	}

	/**
	 * Set read preference for the collection
	 *
	 * @param int $read_preference \MongoDB\Driver\ReadPreference::RP_*
	 */
	public function setReadPreference(int $read_preference) {
		$this->_collection_obj = $this->getCollection()->withOptions(
			array(
				'readPreference' => new \MongoDB\Driver\ReadPreference($read_preference)
			)
		);
	}

	/**
	 * Set the number of documents to return per batch in getList function
	 *
	 * @param int $size
	 */
	public function setBatchSize(int $size) {
		$this->batchSize = $size;
	}

	/**
	 * Returns 1 specific entry by Id
	 * 
	 * @param mixed $id
	 * @param Search $search
	 * @return Entry
	 */
	public function getEntry($id, Search $search = null) : Entry {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$extraParams[] = array('field' => '_id', 'value' => $id, 'type' => '=');
		$query = $this->_getQuery($extraParams);
		$cursor = $this->getCollection()->find(
			$query,
			array(
				'limit' => 1
			)
		);
		
		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.findOne(' . json_encode($query) . ')',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		return new Entry($cursor, $extraParams, $this->getCollection());
	}

	/**
	 * Return first entry from the list
	 * Similar to the getEntry() function but does not require to provide a primary _id
	 *
	 * @param Search $search
	 * @param Sort $sort
	 * @return Entry
	 */
	public function getFirstEntry(Search $search = null, Sort $sort = null) : Entry {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}
		$query = $this->_getQuery($extraParams);

		$sortBy = $this->sort;
		if (!is_null($sort)) {
			$sortBy = $sort->get();
		}

		$cursor = $this->getCollection()->find(
			$query,
			array(
				'sort' => $sortBy,
				'limit' => 1
			)
		);

		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.findOne(' . json_encode($query) . ')',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		return new Entry($cursor, $extraParams, $this->getCollection());
	}
	
	/**
	 * Returns results list
	 * 
	 * @param int|null $results_num
	 * @param int|null $page
	 * @param Search $search
	 * @param Sort $sort
	 * @return Listing
	 */
	public function getList(int $results_num = null, int $page = null, Search $search = null, Sort $sort = null) : Listing {

		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$sortBy = $this->sort;
		if (!is_null($sort)) {
			$sortBy = $sort->get();
		}

		$options = array(
			'projection' => $this->fields_list,
			'sort' => $sortBy
		);

		if (!empty($this->batchSize)) {
			$options['batchSize'] = $this->batchSize;
		}
		
		$skip = -1;
		if (!is_null($results_num)) {
			$options['limit'] = $results_num;
			
			if (!empty($page) && $page > 1) {
				$skip = $results_num * ($page-1);
				$options['skip'] = $skip;
			}
		}

		$query = $this->_getQuery($extraParams);
		$cursor = $this->getCollection()->find($query, $options);

		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.find(' . json_encode($query) .
				(!empty($this->fields_list) ? ', ' . json_encode($this->fields_list) : '') .
				')' .
				(!empty($sortBy) ? '.sort(' . json_encode($sortBy) . ')' : '') .
				(!is_null($results_num) ? '.limit(' . $results_num . ')' : '') .
				($skip != -1 ? '.skip(' . $skip . ')' : ''),
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		return new Listing($cursor, $results_num, $page, $extraParams, $sortBy, $this->getCollection(), $query);
	}
	
	/**
	 * Insert a new entry in a collection
	 * 
	 * @param array $data
	 * @return Insert
	 */
	public function insertEntry(array $data) : Insert {
		if ($this->_id == self::PRIMARY_AUTOINCREMENT && !isset($data['_id'])) {
			$ac = $this->getDatabase()->selectCollection('autoincrement')
				->findOneAndUpdate(
					array('_id' => $this->collection),
					array('$inc' => array('sequence' => 1)),
					array(
						'upsert' => true,
						'new' => true,
						'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
					)
				);

			$ac = \Shelby\Dao\Result\Mongodb\AbstractClass::stdClass2Array($ac);
			$data['_id'] = $ac['sequence'];
		}
		
		$allow = $this->onPreInsertEntry($data);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Insert entry is forbidden');
		}
		
		$this->_prepareData($data);
				
		$res = $this->getCollection()->insertOne(
			$data,
			array(
				'writeConcern' => new \MongoDB\Driver\WriteConcern($this->safe === true ? 1:0)
			)
		);
		$this->onPostInsertEntry($data, $res);
		
		return new Insert($res, $data);
	}

	protected function onPreInsertEntry(/** @noinspection PhpUnusedParameterInspection */ array &$data) : bool {return true;}
	protected function onPostInsertEntry(/** @noinspection PhpUnusedParameterInspection */ array &$data, \MongoDB\InsertOneResult $res) {}
	
	/**
	 * Insert a new entry or replace an existed one
	 * 
	 * @param array $data
	 * @return Update
	 */
	public function replaceEntry(array $data) : Update {
		$allow = $this->onPreReplaceEntry($data);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Replace entry is forbidden');
		}

		$this->_prepareData($data);

		if (!isset($data['_id'])) {
			throw new \InvalidArgumentException('_id must be set to replace an entry');
		}
		
		$query = array('_id' => $data['_id']);
		
		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.update(' . json_encode($query) . ', ' . json_encode($data) . ', true, false)',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		$res = $this->getCollection()->replaceOne(
				$query,
				$data,
				array(
					'upsert' => true,
					'w' => ($this->safe === true ? 1:0)
				)
			);
		$this->onPostReplaceEntry($data, $res);

		return new Update($res, $data);
	}

	protected function onPreReplaceEntry(/** @noinspection PhpUnusedParameterInspection */ array &$data) : bool {return true;}
	protected function onPostReplaceEntry(array &$data, \MongoDB\UpdateResult $res) {}
	
	/**
	 * Updates a specified entry
	 * 
	 * @param mixed $id
	 * @param array $data
	 * @param Search $search
	 * @return Update
	 */
	public function updateEntry($id, Array $data, Search $search = null) : Update {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$allow = $this->onPreUpdateEntry($id, $data, $extraParams);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Update entry is forbidden');
		}

		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}

		foreach ($data as $key => &$el) {
			$field = $this->fields->getField($key);
			if (!is_null($field)) {
				/** @var \Shelby\Dao\Mongodb\Field\InterfaceClass $field */
				$el = $field->prepare($el);
			} else {
				unset($data[$key]);
			}
		}

		if (empty($data)) {
			throw new \InvalidArgumentException('Wrong data array');
		}

		$extraParams[] = array('field' => '_id', 'value' => $id, 'type' => '=');
		$query = $this->_getQuery($extraParams);

		$data = array('$set' => $data);

		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.update(' . json_encode($query) . ', ' . json_encode($data) . ', false, false)',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		$res = $this->getCollection()->updateOne(
			$query,
			$data,
			array(
				'upsert' => false,
				'w' => ($this->safe === true ? 1:0)
			)
		);
		$this->onPostUpdateEntry($id, $data, $extraParams, $res);

		return new Update($res, $data);
	}

	protected function onPreUpdateEntry(/** @noinspection PhpUnusedParameterInspection */ $id, array &$data, array &$extraParams) : bool {return true;}
	protected function onPostUpdateEntry($id, array &$data, array &$extraParams, \MongoDB\UpdateResult $res) {}

	/**
	 * Updates a multiple records
	 *
	 * @param array $data
	 * @param Search $search
	 * @return Update
	 */
	public function updateEntries(array $data, Search $search = null) : Update {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$allow = $this->onPreUpdateEntries($data, $extraParams);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Update entries is forbidden');
		}
		
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}
		
		foreach ($data as $key => &$el) {
			$field = $this->fields->getField($key);
			if (!is_null($field)) {
				/** @var \Shelby\Dao\Mongodb\Field\InterfaceClass $field */
				$el = $field->prepare($el);
			} else {
				unset($data[$key]);
			}
		}
		
		if (empty($data)) {
			throw new \InvalidArgumentException('Wrong data array');
		}

		$query = $this->_getQuery($extraParams);

		$data = array('$set' => $data);

		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.update(' . json_encode($query) . ', ' . json_encode($data) . ', false, true)',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		$res = $this->getCollection()->updateMany(
			$query,
			$data,
			array(
				'upsert' => false,
				'w' => ($this->safe === true ? 1:0)
			)
		);
		$this->onPostUpdateEntries($data, $extraParams, $res);

		return new Update($res, $data);
	}
	
	protected function onPreUpdateEntries(/** @noinspection PhpUnusedParameterInspection */ array &$data, Array &$extraParams) : bool {return true;}
	protected function onPostUpdateEntries(array &$data, Array &$extraParams, \MongoDB\UpdateResult $res) {}
		
	/**
	 * Delete a specific entry
	 * 
	 * @param mixed $id
	 * @param Search $search
	 * @return Delete
	 */
	public function deleteEntry($id, Search $search = null) : Delete {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$allow = $this->onPreDeleteEntry($id, $extraParams);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Delete entry is forbidden');
		}
			
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}
		
		$extraParams[] = array('field' => '_id', 'value' => $id, 'type' => '=');
		$query = $this->_getQuery($extraParams);
		
		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.remove(' . json_encode($query) . ')',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		$res = $this->getCollection()->deleteOne(
			$query,
			array(
				'w' => ($this->safe === true ? 1:0)
			)
		);
		$this->onPostDeleteEntry($id, $extraParams, $res);

		return new Delete($res);
	}
	
	protected function onPreDeleteEntry(/** @noinspection PhpUnusedParameterInspection */ &$id, array &$extraParams) : bool {return true;}
	protected function onPostDeleteEntry(&$id, Array &$extraParams, \MongoDB\DeleteResult $res) {}
	
	/**
	 * Delete multiple entries
	 * 
	 * @param Search $search
	 * @return Delete
	 */
	public function deleteEntries(Search $search = null) : Delete {
		$extraParams = array();
		if (!is_null($search)) {
			$extraParams = $search->get();
		}

		$allow = $this->onPreDeleteEntries($extraParams);
		if ($allow !== true) {
			throw new \InvalidArgumentException('Delete entries is forbidden');
		}
		
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}
		
		$query = $this->_getQuery($extraParams);
		
		// Log
		try {
			self::getLogger()->log(
				'db.' . $this->collection .
				'.remove(' . json_encode($query) . ')',
				\Zend_Log::DEBUG,
				array('source' => 'Mongo')
			);
		} catch (\Zend_Log_Exception $e) {}

		$res = $this->getCollection()->deleteMany(
			$query,
			array(
				'w' => ($this->safe === true ? 1:0)
			)
		);
		$this->onPostDeleteEntries($query, $res);
		
		return new Delete($res);
	}
	
	protected function onPreDeleteEntries(/** @noinspection PhpUnusedParameterInspection */ array &$extraParams) : bool {return true;}
	protected function onPostDeleteEntries(array &$extraParams, \MongoDB\DeleteResult $res) {}

	/**
	 * Return current collection instance
	 *
	 * @return \MongoDB\Collection
	 */
	public function getCollection() : \MongoDB\Collection {
		if (is_null($this->_collection_obj)) {
			$this->_collection_obj = $this->getDatabase()->selectCollection($this->collection);
		}
		return $this->_collection_obj;
	}
	
	/**
	 * Add fields to the list of getList call
	 * 
	 * @param array $fields
	 */
	public function addListFields(array $fields) {
		$this->fields_list = array_merge($this->fields_list, $fields);
	}
	
	/**
	 * Set fields list to get from getList call
	 * 
	 * @param array $fields
	 */
	public function setListFields(array $fields) {
		$this->fields_list = $fields;
	}

	/**
	 * Return fields object
	 *
	 * @return \Shelby\Dao\Mongodb\Field\Object
	 */
	public function getFields() : \Shelby\Dao\Mongodb\Field\Object {
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}

		return $this->fields;
	}

	/**
	 * Prepares a data according to each field data types and callback functions
	 * It is used to keep data types in DB permanent
	 *
	 * @param array $data
	 * @throws \Shelby\Dao\Mongodb\Field\Exception
	 */
	protected function _prepareData(array &$data) {
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}
		
		// Prepare and validate
		$data = $this->fields->prepare($data);
	}
	
	/**
	 * Return a query array using standard DAO parameters format
	 * 
	 * @param array $extraParams
	 * @return array
	 * @throws \Exception
	 */
	protected function _getQuery(array $extraParams) : array {
		if (is_null($this->fields)) {
			$this->fields = $this->initFields();
		}

		$query = array();
		foreach ($extraParams as $el) {
			$field = $this->fields->getField($el['field']);
			
			if (!is_null($field) && !is_null($el['value']) && (!isset($el['prepare']) || $el['prepare'] === true)) {
				/** @var \Shelby\Dao\Mongodb\Field\InterfaceClass $field */
				if ($el['type'] == 'in') {
					foreach ($el['value'] as &$el1) {
						$el1 = $field->prepare($el1);
					}
				} else {
					$el['value'] = $field->prepare($el['value']);
				}
			}
			
			switch ($el['type']) {
				case '=':
					if (isset($query[$el['field']])) {
						$key = key($query[$el['field']]);
						if ($key == '$all') {
							$query[$el['field']]['$all'][] = $el['value'];
						} else {
							$query[$el['field']] = array('$all' => array($query[$el['field']], $el['value']));
						}
					} else {
						$query[$el['field']] = $el['value'];
					}
					break;
				case 'in':
					$query[$el['field']]['$in'] = array_values((array)$el['value']);
					break;
				case '>':
					$query[$el['field']]['$gt'] = $el['value'];
					break;
				case '<':
					$query[$el['field']]['$lt'] = $el['value'];
					break;
				case '>=':
					$query[$el['field']]['$gte'] = $el['value'];
					break;
				case '<=':
					$query[$el['field']]['$lte'] = $el['value'];
					break;
				case '!=':
					$query[$el['field']]['$ne'] = $el['value'];
					break;
				case '~%':
					$query[$el['field']]['$regex'] =
						new \MongoDB\BSON\Regex('^' . preg_quote($el['value']), '');
					break;
				case '~':
					$query[$el['field']]['$regex'] =
						new \MongoDB\BSON\Regex(preg_quote($el['value']), 'i');
					break;
				case 'custom':
					$query = array_merge($query, $el['value']);
					break;
				default:
					throw new \Exception('Unknown type - ' . $el['type']);
			}
		}

		return $query;
	}

}