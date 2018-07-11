<?php

namespace Dao\Service;

class S3Protocol {

	const LIST_TYPE_DIR = 1;
	const LIST_TYPE_FILE = 2;

	private $key = '';
	private $secret = '';
	private $host = '';

	public function __construct($key, $secret, $host = 'rest.s3for.me') {
		$this->key = $key;
		$this->secret = $secret;
		$this->host = $host;
	}

	/**
	 * Returns an array with all objects and directories in the specified prefix
	 *
	 * @param string $bucket
	 * @param string $prefix
	 * @param string $marker
	 * @return array|int
	 */
	public function getList($bucket, $prefix, $marker = '') {
		$uri = 'http://' . $bucket . '.' . $this->host . '/?delimiter=%2F';

		if (!empty($prefix)) {
			$uri .= '&prefix=' . rawurlencode($prefix);
		}
		if (!empty($marker)) {
			$uri .= '&marker=' . rawurlencode($marker);
		}

		$headers = array(
			'Date' => date('r')
		);

		$headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($bucket, '', $headers, 'GET', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code != 200) {
			return $http_code;
		}
		if (empty($response)) {
			return array();
		}

		$result = array();
		$dom = new \SimpleXMLElement($response);
		foreach ($dom->CommonPrefixes as $val) {
			$r = array(
				'name' => (string)$val->Prefix,
				'type' => self::LIST_TYPE_DIR
			);
			$result[] = $r;
		}
		foreach ($dom->Contents as $val) {
			$r = array(
				'name' => (string)$val->Key,
				'type' => self::LIST_TYPE_FILE,
				'date' => strtotime($val->LastModified),
				'md5' => trim($val->ETag, '"'),
				'size' => (int)$val->Size
			);
			$result[] = $r;
		}
		if ((string)$dom->IsTruncated == 'true') {
			$marker = (string)$dom->Marker;
			$pos = strrpos($marker, '/');
			if ($pos !== false) {
				$marker = substr($marker, $pos + 1);
			}
			$marker = rtrim((string)$dom->Prefix, '/') . '/' . $marker;
			unset($dom);
			$result = array_merge(
				$result,
				$this->getList($bucket, $prefix, $marker)
			);
		}

		return $result;
	}

	public function get($bucket, $name, $stream) {
		$name = $this->prepareFilename($name);
		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name;

		$headers = array(
			'Date' => date('r')
		);

		$headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($bucket, $name, $headers, 'GET', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_FILE, $stream);

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $http_code;
	}

	/**
	 * Returns HTTP response code
	 *
	 * @param string $bucket
	 * @param string $name
	 * @param Resource $stream
	 * @param array $options
	 * @return int
	 */
	public function put($bucket, $name, $stream, Array $options = array()) {
		$name = $this->prepareFilename($name);

		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name;

		$headers = array(
			'Date' => date('r')
		);
		if (isset($options['Content-Type'])) {
			$headers['Content-Type'] = $options['Content-Type'];
		}
		if (isset($options['Content-Length'])) {
			$headers['Content-Length'] = $options['Content-Length'];
		}
		if (isset($options['public']) && $options['public'] == true) {
			$headers['x-amz-acl'] = 'public-read';
		}

		$headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($bucket, $name, $headers, 'PUT', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_INFILE, $stream);
		curl_setopt($ch, CURLOPT_UPLOAD, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $http_code;
	}

	public function putMultipart($bucket, $name, $stream, Array $options = array()) {
		$name = $this->prepareFilename($name);

		$max_part = $options['Max-Part'];

		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name . '?uploads';

		$headers = array(
			'Date' => date('r')
		);
		if (isset($options['Content-Type'])) {
			$headers['Content-Type'] = $options['Content-Type'];
		}

		$headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($bucket, $name, $headers, 'POST', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($http_code != 200) {
			return $http_code;
		}

		$dom = new \SimpleXMLElement($response);
		$uploadId = (string)$dom->UploadId;
		$partNumber = 1;
		$n = 0;

		$parts = array();
		do {
			// Save stream position
			$seek = ftell($stream);

			$name2 = $name . '?partNumber=' . $partNumber . '&uploadId=' . $uploadId;
			$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name2;

			echo 'Uploading part ' . $partNumber . ' ... ';

			$headers = array(
				'Date' => date('r')
			);

			$headers['Authorization'] = 'AWS ' . $this->key . ':' .
				$this->getSignature($bucket, $name2, $headers, 'PUT', $uri, $this->secret);

			$headers_curl = array();
			foreach ($headers as $key => $val) {
				$headers_curl[] = $key . ': ' . $val;
			}

			$ch = curl_init($uri);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_INFILE, $stream);
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);

			$total = 0;
			$ctx = hash_init('md5');
			curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fh, $length) use (&$total, &$ctx, $max_part) {
				if ($total > $max_part) {
					return '';
				}
				$buffer = fread($fh, $length);
				hash_update($ctx, $buffer);
				$total += strlen($buffer);
				return $buffer;
			});

			$response = curl_exec($ch);

			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $header_size);

			curl_close($ch);

			$pos = strpos($header, 'ETag:');
			$ETag_received = '';
			if ($pos !== false) {
				$ETag_received = substr($header, $pos+6, 34);
			}
			$ETag_expected = '"' . hash_final($ctx, false) . '"';

			if ($http_code != 200) {
				echo ' response code ' . $http_code . ' ';
				if ($n < 5 && fseek($stream, $seek) === 0) {
					$n++;
					echo "retrying...\n";
					continue;
				}

				echo "aborting!\n";
				$this->abortMultipartUpload($bucket, $name, $uploadId);
				return $http_code;
			}

			if ($ETag_received != $ETag_expected) {
				echo ' wrong MD5 sum. Expected: ' . $ETag_expected . ', received: ' . $ETag_received . ' ';
				if ($n < 5 && fseek($stream, $seek) === 0) {
					$n++;
					echo "retrying...\n";
					continue;
				}

				echo "aborting!\n";
				$this->abortMultipartUpload($bucket, $name, $uploadId);
				return 400;
			}

			$parts[] = array(
				'PartNumber' => $partNumber,
				'ETag' => $ETag_expected
			);
			echo 'done, ' . number_format($total) . " bytes uploaded\n";

			$n = 0;
			$partNumber++;
		} while (!feof($stream));

		// Complete multipart upload, try it several times, if failed - abort it
		$n = 0;
		do {
			$n++;
			$res = $this->completeMultipartUpload($bucket, $name, $uploadId, $parts);
			if ($res != 200) {
				echo 'Unable to complete multipart upload (code ' . $res . ')';
				if ($n < 5) {
					echo ', retrying in 5 seconds';
				} else {
					echo ', aborting';
					$this->abortMultipartUpload($bucket, $name, $uploadId);
					break;
				}
				sleep(5);
				echo "\n";
			}
		} while ($res != 200);

		return $res;
	}

	public function delete($bucket, $name) {
		$name = $this->prepareFilename($name);
		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name;

		$headers = array(
			'Date' => date('r')
		);

		$headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($bucket, $name, $headers, 'DELETE', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $http_code;
	}

	private function completeMultipartUpload($bucket, $name, $uploadId, Array $parts) {
		$name = $this->prepareFilename($name);
		$name2 = $name . '?uploadId=' . $uploadId;
		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name2;

		$headers = array(
			'Date' => date('r'),
			'Content-Type' => 'application/x-www-form-urlencoded'
		);

		$headers['Authorization'] = 'AWS ' . $this->key . ':' .
			$this->getSignature($bucket, $name2, $headers, 'POST', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->setIndent(false);

		$xml->startDocument('1.0', 'UTF-8');
		 $xml->startElement('CompleteMultipartUpload');
		foreach($parts as $val) {
			$xml->startElement('Part');
			 $xml->writeElement('PartNumber', $val['PartNumber']);
			 $xml->writeElement('ETag', $val['ETag']);
			$xml->endElement();
		}
		 $xml->endElement();
		$xml->endDocument();

		$data = $xml->flush();

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $http_code;
	}

	private function abortMultipartUpload($bucket, $name, $uploadId) {
		$name = $this->prepareFilename($name);
		$name2 = $name . '?uploadId=' . $uploadId;
		$uri = 'http://' . $bucket . '.' . $this->host . '/' . $name2;

		$headers = array(
			'Date' => date('r')
		);

		$headers['Authorization'] = 'AWS ' . $this->key . ':' .
			$this->getSignature($bucket, $name2, $headers, 'DELETE', $uri, $this->secret);

		$headers_curl = array();
		foreach ($headers as $key => $val) {
			$headers_curl[] = $key . ': ' . $val;
		}

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_curl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $http_code;
	}

	/**
	 * Get signature code
	 *
	 * @param string $bucket
	 * @param string $file
	 * @param array $headers
	 * @param string $method
	 * @param string $uri
	 * @param string $secret
	 * @internal param array $server
	 * @return string
	 */
	private function getSignature($bucket, $file, Array $headers, $method, $uri, $secret) {
		$type = $md5 = $date = '';

		// Search for the Content-type, Content-MD5 and Date headers
		foreach ($headers as $key => $val) {
			switch ($key) {
				case 'Content-Type':
					$type = $val;
					break;
				case 'Content-MD5':
					$md5 = $val;
					break;
				case 'Date':
					$date = $val;
					break;
			}
		}

		// If we have an x-amz-date header, use that instead of the normal Date
		if (isset($headers['x-amz-date'])) {
			$date = '';
		}

		$sig_str = $method . "\n$md5\n$type\n$date\n";
		// For x-amz- headers, combine like keys, lowercase them, sort them
		// alphabetically and remove excess spaces around values
		$amz_headers = array();
		foreach ($headers as $key=>$val) {
			if (strpos($key, 'x-amz') === 0) {
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

		$sig_str .= (empty($bucket) ? '':'/' . $bucket) . '/' . $file;
		$path = $uri;
		if (strpos($path, '?location') !== false) {
			$sig_str .= '?location';
		} else if (strpos($path, '?acl') !== false) {
			$sig_str .= '?acl';
		} else if (strpos($path, '?torrent') !== false) {
			$sig_str .= '?torrent';
		} else if (strpos($path, '?versions') !== false) {
			$sig_str .= '?versions';
		} else if (strpos($path, '?versioning') !== false) {
			$sig_str .= '?versioning';
		} else if (strpos($path, '?logging') !== false) {
			$sig_str .= '?logging';
		} else if (strpos($path, '?uploads') !== false) {
			$sig_str .= '?uploads';
		}

		//echo "-----\n" . $sig_str . "\n-----\n";

		//return base64_encode(Zend_Crypt_Hmac::compute($secret, 'sha1', utf8_encode($sig_str), Zend_Crypt_Hmac::BINARY));
		return base64_encode(hash_hmac('sha1', utf8_encode($sig_str), $secret, true));
	}

	/**
	 * Prepare filename for upload
	 * - Encode to URL-safe string
	 * - Encode non-UTF8 characters
	 *
	 * @param string $name
	 * @return string
	 */
	private function prepareFilename($name) {
		$name_array = explode('/', $name);
		foreach ($name_array as &$val) {
			if (@iconv('UTF-8', 'UTF-8//IGNORE', $val) !== $val) {
				$val = rawurlencode($val);
			}
			$val = rawurlencode($val);
		}
		return implode('/', $name_array);
	}

}