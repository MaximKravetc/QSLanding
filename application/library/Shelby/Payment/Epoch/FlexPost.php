<?php

class Shelby_Payment_Epoch_FlexPost {

	// Epoch API Services
	const URL = 'https://wnu.com/secure/services/';

	// HMAC key
	private $key;
	// Epoch product code
	private $pi_code;

	function __construct($pi_code = null) {
		$local_ini = new Yaf_Config_Ini(ROOT_PATH . '/application/local.ini');
		$payment_data = $local_ini->offsetGet('payment.epoch');

		$this->key = (string)$payment_data['hmac_key'];

		if (empty($pi_code) && isset($payment_data['pi_code'])) {
			$this->pi_code = (string)$payment_data['pi_code'];
		} elseif (!empty($pi_code)) {
			$this->pi_code = $pi_code;
		}
	}

	public function setPiCode($code) {
		$this->pi_code = (string)$code;
	}

	public function joinPaymentURL(array $options) {
		$options = $this->filterOptions($options);

		if (empty($options)) {
			return false;
		}

		$password = 'YOUR SLR PASSWORD';
		if (isset($options['password'])) {
            $password = $options['password'];
        }

		$options = array_merge($options, [
			'api' => 'join',
			'reseller' => 'a',
			'pi_code' => $this->pi_code,
			'username' => $options['email'],
			'password' => $password
		]);

		if (!isset($this->pi_code)) {
		    $options['currency'] = 'USD';
        }

		$digest = $this->generateHMAC($options);

		$options['epoch_digest'] = $digest;

		return self::URL . '?' . http_build_query($options);
	}

	public function camChargePaymentURL(array $options) {
		$options = $this->filterOptions($options);

		if (empty($options)) {
			return false;
		}

		$options = array_merge($options, [
			'api' => 'camcharge',
			'action' => 'authandclose'
		]);

        if (!isset($this->pi_code)) {
            $options['currency'] = 'USD';
        }

		$digest = $this->generateHMAC($options);

		$options['epoch_digest'] = $digest;

		return self::URL . '?' . http_build_query($options);
	}

	/**
	 * Validate response that was sent from Epoch server after purchase.
	 *
	 * @param array $response [mandatory] Parameters that was sent from Epoch server.
	 *
	 * @return bool Returns true if signature is present and correct, and false otherwise.
	 */
	public function validateResponse(array $response) {
		if (!isset($response['epoch_digest'])) {
			return false;
		}

		$digest_response = (string)$response['epoch_digest'];
		$digest = $this->generateHMAC($response);

		if ($digest === $digest_response) {
			return true;
		}

		return false;
	}

	/**
	 * Generates signature for payment data.
	 *
	 * @param array $options [mandatory] Parameters of the payment request or response.
	 *
	 * @return string HMAC signature.
	 */
	public function generateHMAC(array $options) {
		if (isset($options['epoch_digest'])) {
			unset($options['epoch_digest']);
		}

		ksort($options);

		$hmac_str = '';
		foreach ($options as $key => $val) {
			// Replace spaces, ampersand and equal sign with multibyte support
			$hmac_str .= mb_eregi_replace('(^\s+)|(\s+$)|(&+)|(=+)','', $key) . mb_eregi_replace('(^\s+)|(\s+$)|(&+)|(=+)','', $val);
		}

		return hash_hmac('md5', $hmac_str, $this->key);
	}

	private function filterOptions(array $options) {
		$filtered = [];
		$keys = array_keys($options);

		$regexp = '/^(
            api
            | x_id
            | email
            | pburl
            | amount
            | action
            | pi_code
            | username
            | password
            | currency
            | reseller
            | returnurl
            | member_id
            | auth_amount
            | description
            | pi_returnurl
            | epoch_digest
            )$/x';

		foreach ($keys as $key) {
			if (preg_match($regexp, $key)) {
				$filtered[$key] = $options[$key];
			}
		}

		return $filtered;
	}

}
