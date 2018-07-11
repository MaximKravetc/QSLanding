<?php
/**
 * Session lazy loader
 */

namespace Dao;

class Session {

	/**
	 * Current session object instance
	 * 
	 * @var \Zend_Session_Namespace
	 */
	private $_session = null;
	
	/**
	 * Zend Auth object instance
	 * @var \Zend_Auth
	 */
	private $_auth = null;
	
	private $_namespace = 'default';
	
	public function __construct($namespace) {
		$this->_namespace = $namespace;
	}
	
	/**
	 * Get Zend Session object
	 * 
	 * @return \Zend_Session_Namespace
	 */
	public function getSession() : \Zend_Session_Namespace {
		if (is_null($this->_session)) {
			$this->_session = new \Zend_Session_Namespace($this->_namespace);
		}
		
		return $this->_session;
	}
	
	/**
	 * Get Zend Auth object
	 * 
	 * @return \Zend_Auth
	 */
	public function getAuth() : \Zend_Auth {
		if (is_null($this->_auth)) {
			$this->_auth = \Zend_Auth::getInstance();
			$this->_auth->setStorage(new \Zend_Auth_Storage_Session('Auth_' . $this->_namespace));
		}
		
		return $this->_auth;
	}
	
	public function isAuthenticated() : bool {
		return false;
	}
	
	/**
	 * Put a named value to the Guest user info array
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function putGuestInfo(string $key, $value) {
		if ($this->getSession()->__isset('guest')) {
			$guest = $this->getSession()->__get('guest');
		} else {
			$guest = array();
		}
		
		$guest[$key] = $value;
		$this->_session->__set('guest', $guest);
	}
	
	/**
	 * Get a named value from the Guest user data array, null if not exists
	 * 
	 * @param string $key
	 * @return mixed|null
	 */
	public function getGuestInfo(string $key) {
		if ($this->getSession()->__isset('guest')) {
			if (isset($this->getSession()->guest[$key])) {
				return $this->getSession()->guest[$key];
			}
		}
		
		return null;
	}

	/**
	 * Put named arbitrary data to the session
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function putData(string $key, $value) {
		if ($this->getSession()->__isset('d')) {
			$data = $this->getSession()->__get('d');
		} else {
			$data = array();
		}
		
		$data[$key] = $value;
		$this->_session->__set('d', $data);
	}
	
	/**
	* Get a named value from the arbitrary data array, null if not exists
	*
	* @param string $key
	* @return mixed|null
	*/
	public function getData(string $key) {
		if ($this->getSession()->__isset('d')) {
			if (isset($this->getSession()->d[$key])) {
				return $this->getSession()->d[$key];
			}
		}
	
		return null;
	}

    /**
     * Removes the specified data from the session
     *
     * @param string $key
     */
    public function unsetData(string $key) {
        if ($this->getSession()->__isset('d')) {
            if (isset($this->getSession()->d[$key])) {
                unset($this->getSession()->d[$key]);
            }
        }
    }
}