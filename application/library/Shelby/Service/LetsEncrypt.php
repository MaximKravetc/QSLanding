<?php
/**
 * Class \Shelby\Service\LetsEncrypt
 * Based on a very simple and nicely done library "Simplified PHP ACME client"
 * @see https://github.com/analogic/lescript
 */

namespace Shelby\Service;

class LetsEncrypt {
	
	private $ca = 'https://acme-v01.api.letsencrypt.org';
	private $license = 'https://letsencrypt.org/documents/LE-SA-v1.1.1-August-1-2016.pdf';
	private $countryCode = 'DE';
	private $state = 'Germany';

	private $certificatesDir;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;
	private $client;
	private $accountKeyPath;

	private $daoCerts;

	/**
	 * @var \Closure
	 */
	private $challengeCallback = null;

	public function __construct($certificatesDir, LetsEncrypt\DaoAbstract $daoCerts, $logger = null) {
		$this->certificatesDir = $certificatesDir;
		$this->logger = $logger;
		$this->client = new LetsEncrypt\Client($this->ca);
		$this->accountKeyPath = $certificatesDir . '/private.pem';

		$this->daoCerts = $daoCerts;
		$this->daoCerts->setSafe(true);
	}

	public function setTestingMode() {
		$this->ca = 'https://acme-staging.api.letsencrypt.org'; // testing
		$this->client = new LetsEncrypt\Client($this->ca);
		$this->accountKeyPath = str_replace('/private.pem', '/private_test.pem', $this->accountKeyPath);
	}

	/**
	 * Set callback function to be called once verification challenge is generated
	 * function(string $token, string $payload)
	 * If function returns false, then process stops
	 *
	 * @param \Closure $function
	 */
	public function setChallengeCallback(\Closure $function) {
		$this->challengeCallback = $function;
	}

	public function initAccount() {
		if (!is_file($this->accountKeyPath)) {

			// generate and save new private key for account
			// ---------------------------------------------

			$this->log('Starting new account registration');

			$save_to = dirname($this->accountKeyPath);
			$key = $this->generateKey();

			file_put_contents($save_to . '/private.pem', $key['private']);
			file_put_contents($save_to . '/public.pem', $key['public']);

			$this->postNewReg();
			$this->log('New account certificate registered');

		} else {
			$this->log('Account already registered. Continuing.');
		}
	}

	public function signDomains($id, $reuseCsr = false) {
		$this->log('Starting certificate generation process for domains');

		$entry_cert = $this->daoCerts->getEntry($id);
		if ($entry_cert->exists() === false) {
			throw new LetsEncrypt\Exception('Database entry not found');
		}

		$domains = $entry_cert['domains'];

		$privateAccountKey = $this->readPrivateKeyFile($this->accountKeyPath);
		$accountKeyDetails = openssl_pkey_get_details($privateAccountKey);

		// start domains authentication
		// ----------------------------
		foreach ($domains as $domain) {

			// 1. getting available authentication options
			// -------------------------------------------

			$this->log('Requesting challenge for ' . $domain);

			$response = $this->signedRequest(
				'/acme/new-authz',
				array(
					'resource' => 'new-authz',
					'identifier' => array(
						'type' => 'dns',
						'value' => $domain
					)
				)
			);

			// choose http-01 challenge only
			$challenge = array_reduce($response['challenges'], function ($v, $w) {
				return $v ? $v : ($w['type'] == 'http-01' ? $w : false);
			});
			if (!$challenge) {
				throw new LetsEncrypt\Exception('HTTP Challenge for ' . $domain . ' is not available. Whole response: ' . json_encode($response));
			}

			$this->log('Got challenge token for ' . $domain);
			$location = $this->client->getLastLocation();


			// 2. saving authentication token for web verification
			// ---------------------------------------------------

			$header = array(
				// need to be in precise order!
				'e' => LetsEncrypt\Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['e']),
				'kty' => 'RSA',
				'n' => LetsEncrypt\Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['n'])

			);
			$payload = $challenge['token'] . '.' . LetsEncrypt\Base64UrlSafeEncoder::encode(hash('sha256', json_encode($header), true));

			$this->daoCerts->updateEntry(
				$entry_cert['_id'],
				array(
					'challenge' => array(
						'token' => $challenge['token'],
						'payload' => $payload
					)
				)
			);

			if (!is_null($this->challengeCallback)) {
				$cl = $this->challengeCallback;
				$result = $cl($challenge['token'], $payload);
				if ($result === false) {
					return;
				}
			}

			// 3. verification process itself
			// -------------------------------

			$uri = 'http://' . $domain . '/.well-known/acme-challenge/' . $challenge['token'];

			$this->log('Token for ' . $domain . ' should be available at ' . $uri);

			$this->log('Sending request to challenge');

			// send request to challenge
			$result = $this->signedRequest(
				$challenge['uri'],
				array(
					'resource' => 'challenge',
					'type' => 'http-01',
					'keyAuthorization' => $payload,
					'token' => $challenge['token']
				)
			);

			// waiting loop
			do {
				if (empty($result['status']) || $result['status'] == 'invalid') {
					throw new LetsEncrypt\Exception('Verification ended with error: ' . json_encode($result));
				}
				$ended = !($result['status'] === 'pending');

				if (!$ended) {
					$this->log('Verification pending, sleeping 1s');
					sleep(1);
				}

				$result = $this->client->get($location);

			} while (!$ended);

			$this->log('Verification ended with status: ' . $result['status']);

		}


		// requesting certificate
		// ----------------------

		// generate private key for domain if not exist
		if (empty($entry_cert['cert']['private'])) {
			$key = $this->generateKey();

			$this->daoCerts->updateEntry(
				$entry_cert['_id'],
				array(
					'cert.private' => $key['private'],
					'cert.public' => $key['public']
				)
			);

			// load domain key
			$privateDomainKey = $key['resource'];
		} else {
			$privateDomainKey = $this->readPrivateKey($entry_cert['cert']['private']);
		}

		// ???
		//$this->client->getLastLinks();

		if ($reuseCsr === true && !empty($entry_cert['cert']['csr'])) {
			$csr = $this->getCsrContent($entry_cert['cert']['csr']);
		} else {
			$csr = $this->generateCSR($privateDomainKey, $domains);

			$this->daoCerts->updateEntry(
				$entry_cert['_id'],
				array(
					'cert.csr' => $csr
				)
			);

			$csr = $this->getCsrContent($csr);
		}

		// request certificates creation
		$result = $this->signedRequest(
			'/acme/new-cert',
			array(
				'resource' => 'new-cert',
				'csr' => $csr
			)
		);
		if ($this->client->getLastCode() !== 201) {
			throw new LetsEncrypt\Exception('Invalid response code: ' . $this->client->getLastCode() . ", " . json_encode($result));
		}
		$location = $this->client->getLastLocation();

		// waiting loop
		$certificates = array();
		while (1) {
			$this->client->getLastLinks();

			$result = $this->client->get($location);

			if ($this->client->getLastCode() == 202) {

				$this->log('Certificate generation pending, sleeping 1s');
				sleep(1);

			} else if ($this->client->getLastCode() == 200) {

				$this->log('Got certificate! YAY!');
				$certificates[] = $this->parsePemFromBody($result);


				foreach ($this->client->getLastLinks() as $link) {
					$this->log('Requesting chained cert at ' . $link);
					$result = $this->client->get($link);
					$certificates[] = $this->parsePemFromBody($result);
				}

				break;
			} else {

				throw new LetsEncrypt\Exception('Can\'t get certificate: HTTP code ' . $this->client->getLastCode());

			}
		}

		if (empty($certificates)) {
			throw new LetsEncrypt\Exception('No certificates generated');
		}

		/*
		$this->log("Saving fullchain.pem");
		file_put_contents($domainPath . '/fullchain.pem', implode("\n", $certificates));

		$this->log("Saving cert.pem");
		file_put_contents($domainPath . '/cert.pem', array_shift($certificates));

		$this->log("Saving chain.pem");
		file_put_contents($domainPath . "/chain.pem", implode("\n", $certificates));
		*/

		$cert_info = openssl_x509_parse($certificates[0]);

		$this->daoCerts->updateEntry(
			$entry_cert['_id'],
			array(
				'cert.chain' => $certificates,
				'valid' => array(
					'from' => $cert_info['validFrom_time_t'],
					'to' => $cert_info['validTo_time_t']
				)
			)
		);

		$this->log("Done !!§§!");
	}

	private function readPrivateKeyFile($path) {
		if (($key = openssl_pkey_get_private('file://' . $path)) === FALSE) {
			throw new LetsEncrypt\Exception(openssl_error_string());
		}

		return $key;
	}
	private function readPrivateKey($key_private) {
		if (($key = openssl_pkey_get_private($key_private)) === FALSE) {
			throw new LetsEncrypt\Exception(openssl_error_string());
		}

		return $key;
	}

	private function parsePemFromBody($body) {
		$pem = chunk_split(base64_encode($body), 64, "\n");
		return "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
	}

	private function postNewReg() {
		$this->log('Sending registration to letsencrypt server');

		return $this->signedRequest(
			'/acme/new-reg',
			array('resource' => 'new-reg', 'agreement' => $this->license)
		);
	}

	private function generateCSR($privateKey, array $domains) {
		$domain = reset($domains);

		$san = implode(',', array_map(function ($dns) {
			return 'DNS:' . $dns;
		}, $domains));

		$tmpConf = tmpfile();
		$tmpConfMeta = stream_get_meta_data($tmpConf);
		$tmpConfPath = $tmpConfMeta['uri'];

		// workaround to get SAN working
		fwrite($tmpConf,
			'HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
default_bits = 2048
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = ' . $san . '
keyUsage = nonRepudiation, digitalSignature, keyEncipherment');

		$csr = openssl_csr_new(
			array(
				'CN' => $domain,
				'ST' => $this->state,
				'C' => $this->countryCode,
				'O' => 'Unknown',
			),
			$privateKey,
			array(
				'config' => $tmpConfPath,
				'digest_alg' => 'sha256'
			)
		);

		if (!$csr) throw new \RuntimeException("CSR couldn't be generated! " . openssl_error_string());

		openssl_csr_export($csr, $csr);
		fclose($tmpConf);

		return $csr;
	}

	private function getCsrContent($csr) {
		preg_match('~REQUEST-----(.*)-----END~s', $csr, $matches);

		return trim(LetsEncrypt\Base64UrlSafeEncoder::encode(base64_decode($matches[1])));
	}

	/**
	 * Generate new private/public key pair
	 * Returns array with 'private' and 'public' keys, resource returned by openssl_pkey_new function is
	 * returned in 'resource' array key
	 *
	 * @return array
	 */
	private function generateKey() {
		$res = openssl_pkey_new(array(
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'private_key_bits' => 4096,
		));

		if(!openssl_pkey_export($res, $privateKey)) {
			throw new LetsEncrypt\Exception('Key export failed!');
		}

		$details = openssl_pkey_get_details($res);

		return array(
			'private' => $privateKey,
			'public' => $details['key'],
			'resource' => $res
		);
	}

	private function signedRequest($uri, array $payload) {
		$privateKey = $this->readPrivateKeyFile($this->accountKeyPath);
		$details = openssl_pkey_get_details($privateKey);

		$header = array(
			'alg' => 'RS256',
			'jwk' => array(
				'kty' => 'RSA',
				'n' => LetsEncrypt\Base64UrlSafeEncoder::encode($details['rsa']['n']),
				'e' => LetsEncrypt\Base64UrlSafeEncoder::encode($details['rsa']['e']),
			)
		);

		$protected = $header;
		$protected['nonce'] = $this->client->getLastNonce();


		$payload64 = LetsEncrypt\Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));
		$protected64 = LetsEncrypt\Base64UrlSafeEncoder::encode(json_encode($protected));

		openssl_sign($protected64 . '.' . $payload64, $signed, $privateKey, 'SHA256');

		$signed64 = LetsEncrypt\Base64UrlSafeEncoder::encode($signed);

		$data = array(
			'header' => $header,
			'protected' => $protected64,
			'payload' => $payload64,
			'signature' => $signed64
		);

		$this->log('Sending signed request to ' . $uri);

		return $this->client->post($uri, json_encode($data));
	}

	protected function log($message) {
		if($this->logger) {
			$this->logger->info($message);
		} else {
			echo $message . "\n";
		}
	}
}
