<?php

namespace Shelby\Service;

class ClouDNS {

	const TYPE_A = 'A';
	const TYPE_AAAA = 'AAAA';
	const TYPE_CNAME = 'CNAME';

	private $api_id = '';
	private $api_pass = '';

	public function __construct($id, $pass) {
		$this->api_id = $id;
		$this->api_pass = $pass;
	}

	/**
	 * Gets a list with zones you have or zone names matching a keyword.
	 * The method work with pagination.
	 *
	 * @param int $page
	 * @param int $rows
	 * @return array
	 */
	public function listZones($page = 1, $rows = 100) {
		$fields = array(
			'page' => $page,
			'rows-per-page' => $rows
		);

		return $this->sendRequest('list-zones', $fields);
	}

	/**
	 * List of records in the domain zone
	 * Note: This function is available only for master zones.
	 *
	 * @param string $domain
	 * @return array
	 */
	public function listRecords($domain) {
		$fields = array(
			'domain-name' => $domain
		);

		return $this->sendRequest('records', $fields);
	}

	/**
	 * Delete record of your domain zone.
	 *
	 * @param string $domain
	 * @param int $record_id
	 * @return array
	 */
	public function deleteRecord($domain, $record_id) {
		$fields = array(
			'domain-name' => $domain,
			'record-id' => $record_id
		);

		return $this->sendRequest('delete-record', $fields);
	}

	public function addARecord($domain, $host, $ip, $ttl = 3600) {
		$fields = array(
			'domain-name' => $domain,
			'host' => $host,
			'record-type' => self::TYPE_A,
			'record' => $ip,
			'ttl' => $ttl
		);

		return $this->sendRequest('add-record', $fields);
	}

	/**
	 * Perform an API Call and return unserialized result
	 *
	 * @param string $call
	 * @param array $fields
	 * @return array
	 */
	private function sendRequest($call, array $fields) {
		$fields['auth-id'] = $this->api_id;
		$fields['auth-password'] = $this->api_pass;

		$fields_string = http_build_query($fields);

		$ch = curl_init('https://api.cloudns.net/dns/' . $call . '.json');

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);

		$json = curl_exec($ch);
		curl_close($ch);

		return json_decode($json, true);
	}

}