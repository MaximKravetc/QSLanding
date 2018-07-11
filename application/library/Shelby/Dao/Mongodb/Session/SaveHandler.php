<?php

namespace Shelby\Dao\Mongodb\Session;

class SaveHandler extends \Shelby\Dao\Mongodb\AbstractClass
								implements \Zend_Session_SaveHandler_Interface {
	
	/**
	 * Database name
	 * @var string
	 */
	protected $database = null;
	
	/**
	 * Session collection
	 * @var \MongoDB\Collection
	 */
	private $collection = null;
	
	/**
	 * Flag for bot request
	 * If set to true we should not save session data to storage nor read it
	 *
	 * @var boolean
	 */
	private $bot = false;
	
	public function setBot($bot = true) {
		$this->bot = (boolean)$bot;
	}

	public function open($save_path, $name) {
		if (is_null($this->database)) {
			$this->database = self::$options['dbname'];
		}
		$this->collection = self::getConnection()
			->selectDatabase($this->database)
			->selectCollection('session');

		return true;
	}
	
	public function close() {
		return true;
	}
	
	public function read($id) {
		if ($this->bot === true) {
			return '';
		}
		
		// Log
		self::getLogger()->log(
			'Read: ' . $id,
			\Zend_Log::DEBUG,
			array('source' => 'Session')
		);

		$res = $this->collection->findOne(array('_id' => $id));

		if (!empty($res)) {
			return $res['data'];
		}
		
		return '';
	}

	/**
	 * @param string $id
	 * @param mixed $data
	 * @return bool
	 */
	public function write($id, $data) {
		// Log
		// Fatal error: Access to undeclared static property: Dao_Abstract::$logger in /Dao/Abstract.php on line 14
		/*self::getLogger()->log(
			'Write: ' . $id,
			Zend_Log::DEBUG,
			array('source' => 'Session')
		);*/
		
		if ($this->bot === true) {
			return true;
		}

		try {
			$this->collection->replaceOne(
					array('_id' => $id),
					array(
						'_id' => $id,
						'time' => time(),
						'data' => $data
					),
					array(
						'upsert' => true,
						'writeConcern' => new \MongoDB\Driver\WriteConcern(1)
					)
				);
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			// In case of Master fail we can always proceed in read-only mode
			trigger_error($e->getTraceAsString() . "\n" . $e->getMessage(), E_USER_NOTICE);
			return false;
		}
		
		return true;
	}
	
	public function destroy($id) {
		$this->collection->deleteOne(
			array('_id' => $id)
		);
		
		return true;
	}
	
	public function gc($maxlifetime) {
		$this->collection->deleteMany(
			array(
				'time' => array('$lt' => (time() - $maxlifetime))
			)
		);
				
		return true;
	}
	
}