<?php

/**
 * Class Dao_Service_Payment_CCBill
 */
class Dao_Service_Payment_CCBill {

	const URL_SIGNUP = 'https://bill.ccbill.com/jpost/signup.cgi';

	/**
	 *  An integer representing the 3-digit currency code that will be used for the transaction.
	 *  978 - EUR, 036 - AUD, 124 - CAD, 826 - GBP, 392 - JPY, 840 - USD
	 */
	const CURRENCY_CODE_USD = 840;

	/**
	 * An integer value representing the 6-digit merchant account number.
	 *
	 * @var int
	 */
	private $accnum;

	/**
	 * An integer value representing the 4-digit merchant subaccount number the customer should be charged on.
	 *
	 * @var int
	 */
	private $subacc;

	/**
	 * CCBill uses your salt value to verify the hash
	 *
	 * @var string
	 */
	private $salt;

	private $formName = '';

	private $customFields = array();

	/**
	 * Dao_Service_Payment_CCBill constructor.
	 * @param int $accnum
	 * @param int $subacc
	 * @param string $salt
	 */
	public function __construct($accnum, $subacc, $salt = '') {
		$this->accnum = $accnum;
		$this->subacc = $subacc;
		$this->salt = $salt;
	}

	/**
	 * Set sign up form name
	 *
	 * @param string $name
	 */
	public function setFormName($name) {
		$this->formName = $name;
	}

	/**
	 * Add/change custom field to be sent with the payment request
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function addCustomField($name, $value) {
		$this->customFields[$name] = $value;
	}

	/**
	 * Recurring transactions
	 * @see https://www.ccbill.com/cs/manuals/CCBill_Dynamic_Pricing.pdf
	 *
	 * @param double $formPrice		A decimal value representing the initial price.
	 * @param double $formPeriod	An integer representing the length, in days, of the initial billing period.
	 * @param double $formRecurringPrice	A decimal value representing the recurring billing price
	 * @param int $formRecurringPeriod		An integer representing the number of days between each rebill.
	 * @return string string
	 */
	public function dynamicPricingRecurringURL($formPrice, $formPeriod, $formRecurringPrice, $formRecurringPeriod) {
		$formDigest = $this->formDigestGenerate(
			array(
				$formPrice, $formPeriod, $formRecurringPrice, $formRecurringPeriod,
				99, self::CURRENCY_CODE_USD, $this->salt
			)
		);

		$query = array(
			'clientAccnum' => $this->accnum,
			'clientSubacc' => sprintf('%04d', $this->subacc),
			'formName' => $this->formName,
			'formPrice' => $formPrice,
			'formPeriod' => $formPeriod,
			'formRecurringPrice' => $formRecurringPrice,
			'formRecurringPeriod' => $formRecurringPeriod,
			'formRebills' => 99, // 99 means indefinitely
			'currencyCode' => self::CURRENCY_CODE_USD,
			'formDigest' => $formDigest
		);

		$query = array_merge($this->customFields, $query);
		$this->customFields = array();

		return self::URL_SIGNUP . '?' . http_build_query($query);
	}

	/**
	 * Single billing transaction
	 * @todo test it before use
	 *
	 * @see https://www.ccbill.com/cs/manuals/CCBill_Dynamic_Pricing.pdf
	 */
	public function dynamicPricingURL($formPrice, $formPeriod) {
		$formDigest = $this->formDigestGenerate(
			array(
				$formPrice, $formPeriod,
				self::CURRENCY_CODE_USD, $this->salt
			)
		);

		$query = array(
			'clientAccnum' => $this->accnum,
			'clientSubacc' => sprintf('%04d', $this->subacc),
			'formName' => $this->formName,
			'formPrice' => $formPrice,
			'formPeriod' => $formPeriod,
			'currencyCode' => self::CURRENCY_CODE_USD,
			'formDigest' => $formDigest
		);

		$query = array_merge($this->customFields, $query);
		$this->customFields = array();

		return self::URL_SIGNUP . '?' . http_build_query($query);
	}

	/**
	 * Hash digest validation of a Dynamic Pricing response.
	 *
	 * @param array $post
	 * @return bool
	 */
	public function validateSignature(Array $post) {
		if (empty($post['dynamicPricingValidationDigest'])) {
			return false;
		}

		if (!empty($post['subscriptionId'])) {
			$hash = $post['subscriptionId'];
		} else {
			// Use transactionId for NewSaleFailure request
			$hash = $post['transactionId'];
		}
		if (in_array($post['eventType'], array('NewSaleSuccess', 'UpgradeSuccess', 'UpSaleSuccess', 'CrossSaleSuccess'))) {
			$hash .= '1';
		} else {
			$hash .= '0';
		}
		$hash .= $this->salt;

		return md5($hash) == $post['dynamicPricingValidationDigest'];
	}

	/**
	 * @param array $values
	 * @return string
	 */
	private function formDigestGenerate(Array $values) {
		return md5(implode('', $values));
	}

}