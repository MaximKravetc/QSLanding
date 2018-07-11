<?php

namespace Dao\Service;

class S3 {

	const ACTIONS =
		array(
			'location', 'acl', 'torrent', 'versions', 'versioning', 'logging', 'uploads', 'cors',
			'policy', 'requestPayment', 'lifecycle'
		);

	/**
	 * @param string $bucket
	 * @param string $file
	 * @param string $secret
	 * @param int $expires
	 * @return string
	 */
	public static function signURL(string $bucket, string $file, string $secret, int $expires) : string {
		$type = $md5 = '';

		$sig_str = 'GET' . "\n$md5\n$type\n$expires\n";

		$sig_str .= (empty($bucket) ? '':'/' . $bucket) . $file;


		try {
			return base64_encode(\Zend_Crypt_Hmac::compute($secret, 'sha1', utf8_encode($sig_str), \Zend_Crypt_Hmac::BINARY));
		} catch (\Zend_Crypt_Hmac_Exception $e) {}

		return '';
	}

	/**
	 * Get Signature V2
	 *
	 * @param string $bucket
	 * @param string $file
	 * @param string $secret
	 * @param array $server
	 * @return string
	 */
	public static function getSignatureV2(string $bucket, string $file, string $secret, array $server) : string {
		// Zend_Service_Amazon_S3::addSignature() code start
		$type = $md5 = $date = '';

		// Search for the Content-type, Content-MD5 and Date headers
		foreach ($server as $key => $val) {
			switch ($key) {
				case 'CONTENT_TYPE':
					$type = $val;
					break;
				case 'CONTENT_MD5':
					$md5 = $val;
					break;
				case 'DATE':
					$date = $val;
					break;
			}
		}

		// If we have an x-amz-date header, use that instead of the normal Date
		if (isset($server['X_AMZ_DATE'])) {
			$date = '';
		}

		$sig_str = $server['REQUEST_METHOD'] . "\n$md5\n$type\n$date\n";
		// For x-amz- headers, combine like keys, lowercase them, sort them
		// alphabetically and remove excess spaces around values
		$amz_headers = array();
		foreach ($server as $key => $val) {
			if (strpos($key, 'X_AMZ') === 0) {
				$key = str_replace('_', '-', strtolower($key));
				if (is_array($val)) {
					$amz_headers[$key] = $val;
				} else {
					$amz_headers[$key][] = preg_replace('/\s+/', ' ', $val);
				}
			}
		}
		if (!empty($amz_headers)) {
			ksort($amz_headers);
			foreach ($amz_headers as $key => $val) {
				$sig_str .= $key . ':' . implode(',', $val) . "\n";
			}
		}

		$sig_str .= (empty($bucket) ? '':'/' . $bucket) . $file;

		foreach ($_GET as $key => $val) {
			if (empty($val) && in_array($key, self::ACTIONS)) {
				$sig_str .= '?' . $key;
				if (in_array($key, array('location', 'uploads'))
					&& strpos($_SERVER['QUERY_STRING'], $key . '=') !== false) {

					// ?location= & ?uploads= Transmit 4/5 workaround
					$sig_str .= '=';
				}
				break;
			}
		}

		try {
			return base64_encode(\Zend_Crypt_Hmac::compute($secret, 'sha1', utf8_encode($sig_str), \Zend_Crypt_Hmac::BINARY));
		} catch (\Zend_Crypt_Hmac_Exception $e) {}

		return '';
	}

}