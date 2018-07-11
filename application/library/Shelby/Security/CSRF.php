<?php
/**
 * Manage tokens that protects users from CSRF attacks.
 * Based on default PHP sessions.
 */

namespace Shelby\Security;

class CSRF {

	private $salt = null;
	private $timeTerm = 3600;

	public function __construct() {
		if (!isset($_SESSION['csrf'])) {
			$_SESSION['csrf'] = [];
		}

		$this->deleteExpiredTokens();
	}

	/**
	 * Update salt with random data to improve token security.
	 * DON'T put any sensitive data here.
	 *
	 * @param string|int|float $salt
	 *
	 * @return void
	 */
	public function setSalt($salt) {
		if (is_string($salt) || is_numeric($salt)) {
			$this->salt = $salt;
		}
	}

	/**
	 * Update time of token life.
	 *
	 * @param int $seconds
	 *
	 * @return void
	 */
	public function setTimeTerm($seconds) {
		$seconds = (int)$seconds;

		if ($seconds > 0) {
			$this->timeTerm = $seconds;
		}
	}

	/**
	 * Generates and adds token to user session.
	 * Salt shouldn't contain any sensitive data.
	 * Uses low secure hash algorithm to improve performance.
	 *
	 * @return string Returns generated user token.
	 */
	public function addToken() : string {
		$token = sha1(mt_rand(11111111111111, 99999999999999) . $this->salt);

		$_SESSION['csrf'][$token] = time() + $this->timeTerm;

		return $token;
	}

	/**
	 * Validates user token. If token is valid removes it to prevent reuse.
	 *
	 * @param string $userToken Token that user sent.
	 *
	 * @return bool Returns true if token is valid and false otherwise.
	 */
	public function validateToken($userToken) : bool {
		foreach ($_SESSION['csrf'] as $token => $term) {
			if (hash_equals($token, (string)$userToken)) {
				unset($_SESSION['csrf'][$token]);
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes tokens that were expired.
	 *
	 * @return void
	 */
	public function deleteExpiredTokens() {
		$time = time();

		foreach ($_SESSION['csrf'] as $token => $term) {
			if ($time > $term) {
				unset($_SESSION['csrf'][$token]);
			}
		}
	}

}
