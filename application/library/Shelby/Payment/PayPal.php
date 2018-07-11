<?php

namespace Shelby\Payment;

class PayPal {

	const VERSION = '87.0';

	const ACK_SUCCESS = 'Success';

	/**
	 * Payment PayPal options array from config.ini
	 *
	 * @var array
	 */
	protected $_options = array();

	protected $_api_endpoint = 'https://api-3t.paypal.com/nvp';
	protected $_pp_url = 'https://www.paypal.com/webscr&cmd=_express-checkout&token=';

	private $_url_return = null;
	private $_url_cancel = null;

	private $_currency = 'USD';
	private $_locale = 'US';

	/**
	 * Instantiate object instance and initialize it
	 * payment.paypal.* part from config.ini must be passed as the first parameter
	 *
	 * @param array $options
	 */
	public function __construct(Array $options) {
		$this->_options = $options;
	}

	/**
	 * Set another payment currency
	 *
	 * @param $currency
	 */
	public function setCurrency($currency) {
		$this->_currency = $currency;
	}

	/**
	 * Url to redirect user after success payment, must be set
	 *
	 * @param $url
	 */
	public function setReturnUrl($url) {
		$this->_url_return = $url;
	}

	/**
	 * Url to redirect user if he decide to cancel an order
	 *
	 * @param $url
	 */
	public function setCancelUrl($url) {
		$this->_url_cancel = $url;
	}

	/**
	 * Set desired PayPal interface locale
	 *
	 * @param string $locale
	 */
	public function setLocale($locale) {
		$this->_locale = $locale;
	}

	/**
	 * Contact with PayPal to get TOKEN
	 * 1st Step
	 *
	 * @param float $price
	 * @param string $user_email
	 * @return bool|string
	 */
	public function setExpressCheckout($price, $user_email) {
		$price = round($price, 2);

		$nvpstr =
			'&RETURNURL=' . urlencode($this->_url_return) .
			'&CANCELURL=' . urlencode($this->_url_cancel) .
			'&PAYMENTREQUEST_0_AMT=' . $price .
			'&PAYMENTREQUEST_0_CURRENCYCODE=' . $this->_currency .
			'&LOCALECODE=' . $this->_locale .
			'&PAYMENTREQUEST_0_PAYMENTACTION=Sale&ALLOWNOTE=1&NOSHIPPING=1';

		$i = 0;
		$nvpstr .=
			'&L_PAYMENTREQUEST_0_NAME' . $i . '=' . urlencode('Add funds to ' . $user_email) .
			'&L_PAYMENTREQUEST_0_AMT' . $i . '=' . $price .
			'&L_PAYMENTREQUEST_0_QTY' . $i . '=1';

		$resArray = $this->hash_call('SetExpressCheckout', $nvpstr);

		if ($resArray['ACK'] == self::ACK_SUCCESS) {
			return $this->_pp_url . $resArray['TOKEN'];
		}

		//Dao_Abstract::getLogger()->log(
		//	"Acd_Payment_PayPal: setExpressCheckout() failed\n" . print_r($resArray, true), Zend_Log::DEBUG);

		return false;
	}

	/**
	 * Get order and customer information from PayPal
	 * 2nd Step
	 *
	 * @param $token
	 * @return array
	 */
	public function getExpressCheckoutDetails($token) {
		$nvpstr = '&TOKEN=' . $token;

		return $this->hash_call('GetExpressCheckoutDetails', $nvpstr);
	}

	/**
	 * Perform actual payment
	 * 3rd Step
	 *
	 * @param array $orderDetails
	 * @return array|boolean
	 */
	public function doExpressCheckoutPayment(Array $orderDetails) {
		$nvpstr =
			'&TOKEN=' . $orderDetails['TOKEN'] .
			'&PAYERID=' . $orderDetails['PAYERID'] .
			'&PAYMENTREQUEST_0_AMT=' . $orderDetails['PAYMENTREQUEST_0_AMT'] .
			'&PAYMENTREQUEST_0_CURRENCYCODE=' . $orderDetails['PAYMENTREQUEST_0_CURRENCYCODE'] .
			'&PAYMENTREQUEST_0_PAYMENTACTION=Sale';

		$resArray = $this->hash_call('DoExpressCheckoutPayment', $nvpstr);

		if ($resArray['ACK'] == self::ACK_SUCCESS) {
			return $resArray;
		}

		//Dao_Abstract::getLogger()->log(
		//	"Acd_Payment_PayPal: doExpressCheckoutPayment() failed\n" . print_r($resArray, true), Zend_Log::DEBUG);

		return false;
	}

	/**
	 * Perform a transaction using reference Transaction ID
	 * @note it's a draft function, not intended for production use yet
	 * @param string $ref BILLINGAGREEMENTID
	 */
	public function doReferenceTransaction($ref) {
		$nvpstr =
			'&REFERENCEID=' . $ref .
			'&AMT=10' .
			'&PAYMENTACTION=Sale&REQCONFIRMSHIPPING=0';

		$this->hash_call('DoReferenceTransaction', $nvpstr);
	}

	/**
	 * hash_call: Function to perform the API call to PayPal using API signature
	 * returns an associative array containing the response from the server.
	 *
	 * @param string $methodName
	 * @param string $nvpStr
	 * @throws \Exception
	 * @return array
	 */
	private function hash_call($methodName, $nvpStr) {

		// form header string
		$nvpheader = $this->nvpHeader();
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->_api_endpoint);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

		$nvpStr=$nvpheader.$nvpStr;

		$nvpStr = "&VERSION=" . urlencode(self::VERSION) . $nvpStr;

		$nvpreq="METHOD=".urlencode($methodName).$nvpStr;

		// set the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		//getting response from server
		$response = curl_exec($ch);

		if (curl_errno($ch) == 60) {
			curl_setopt($ch, CURLOPT_CAINFO,
				dirname(__FILE__) . '/PayPal_cacert.pem');
			$response = curl_exec($ch);
		}

		//converting NVPResponse to an Associative Array
		$nvpResArray = $this->deformatNVP($response);

		if (curl_errno($ch)) {
			throw new \Exception(curl_error($ch), curl_errno($ch));
		} else {
			//closing the curl
			curl_close($ch);
		}

		return $nvpResArray;
	}

	private function nvpHeader() {
		return '&PWD=' . urlencode($this->_options['password']) .
			'&USER=' . urlencode($this->_options['username']) .
			'&SIGNATURE=' . urlencode($this->_options['signature']);
	}

	/** This function will take NVPString and convert it to an Associative Array and it will decode the response.
	 * It is useful to search for a particular key and displaying arrays.
	 *
	 * @param string $nvpstr
	 * @return array
	 */
	private function deformatNVP($nvpstr) {

		$intial=0;
		$nvpArray = array();


		while(strlen($nvpstr)){
			//position of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr, $intial, $keypos);
			$valval=substr($nvpstr, $keypos+1, $valuepos-$keypos-1);
			//decoding the response
			$nvpArray[urldecode($keyval)] = urldecode($valval);
			$nvpstr = substr($nvpstr, $valuepos+1, strlen($nvpstr));
		}
		return $nvpArray;
	}

}