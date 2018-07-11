<?php

namespace Shelby\Dao\Mongodb;

class AuthAdapter extends AbstractClass implements \Zend_Auth_Adapter_Interface {
	
	const PASS_ENC_NONE = 0;
	const PASS_ENC_MD5 = 1;
	const PASS_ENC_PHP_PASSWORD_HASH = 2;
	const PASS_ENC_DRUPAL7 = 3;
	const PASS_ENC_MD5_STRING = 4;

	private $collection;
	private $userNameField;
	private $passwordField;
	private $passwordEncryption;
	private $saltField;

	private $user;
	private $pass;

	/**
	 * @var null|\MongoDB\Model\BSONDocument
	 */
	private $result = null;
	
	public function __construct($collection, $userNameField, $passwordField, $passwordEncryption = self::PASS_ENC_NONE) {
		$this->collection = $collection;
		$this->userNameField = $userNameField;
		$this->passwordField = $passwordField;
		$this->passwordEncryption = $passwordEncryption;
	}
	
	public function setIdentity($user) {
		$this->user = $user;
	}
	
	public function setCredential($pass) {
		$this->pass = $pass;
	}

	/**
	 * Set collection field to use for salt value in password calculation
	 *
	 * @param string $field
	 */
	public function setSaltField($field) {
		$this->saltField = $field;
	}

	/**
	 * @return \Zend_Auth_Result
	 * @throws \Zend_Auth_Adapter_Exception
	 */
	public function authenticate() {
		/** @var array $entry */
		$entry = $this->getDatabase()->
			selectCollection($this->collection)->
				findOne(
					array($this->userNameField => $this->user)
				);

		$this->result = $entry;

		if (empty($entry)) {
			return new \Zend_Auth_Result(
					\Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND,
					$this->user
				);
		}

		if (!empty($this->saltField)) {
			$this->pass = $entry[$this->saltField] . $this->pass;
		}
		
		switch ($this->passwordEncryption) {
			case self::PASS_ENC_NONE:
				$pass = $this->pass;
				$entry_pass = $entry[$this->passwordField];
				break;
			case self::PASS_ENC_MD5:
				$pass = md5($this->pass, true);
				$entry_pass = $entry[$this->passwordField]->getData();
				break;
			case self::PASS_ENC_MD5_STRING:
				$pass = md5($this->pass);
				$entry_pass = $entry[$this->passwordField];
				break;
			case self::PASS_ENC_PHP_PASSWORD_HASH:
				$pass = $this->pass;
				$entry_pass = $entry[$this->passwordField];
				if (password_verify($pass, $entry_pass)) {
					$pass = $entry_pass;
				}
				break;
			case self::PASS_ENC_DRUPAL7:
				$entry_pass = $entry[$this->passwordField];
				$pass = $entry_pass;
				$check = \Shelby\Service\Drupal\Password::user_check_password($this->pass, $entry_pass);
				if ($check === false) {
					$pass .= uniqid();
				}
				break;
			default:
				throw new \Zend_Auth_Adapter_Exception('Unknown password encryption method');
		}
		
		if ($pass != $entry_pass) {
			return new \Zend_Auth_Result(
					\Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
					$this->user
				);
		}
		
		return new \Zend_Auth_Result(
				\Zend_Auth_Result::SUCCESS,
				$this->user
			);
	}
	
	public function getResultEntry() {
		return $this->result;
	}
	
}
