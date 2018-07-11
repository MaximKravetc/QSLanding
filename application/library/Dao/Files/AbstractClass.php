<?php

namespace Dao\Files;

abstract class AbstractClass {
	
	/**
	 * @var array
	 */
	private static $cloud_storage = array();

	private static $cloud_options = array();

	/**
	 * Returns Cloud Storage adapter singleton
	 *
	 * @param string $bucket
	 * @return \Zend_Cloud_StorageService_Adapter_S3
	 * @throws \Zend_Cloud_StorageService_Exception
	 * @throws \Zend_Service_Amazon_S3_Exception
	 */
	public static function getCloudStorage(string $bucket = '') : \Zend_Cloud_StorageService_Adapter_S3 {
		if (!isset(self::$cloud_storage[$bucket])) {
			/** @var \Zend_Cloud_StorageService_Adapter_S3 $obj */
			$obj = \Zend_Cloud_StorageService_Factory::
				getAdapter(array(
					\Zend_Cloud_StorageService_Factory::STORAGE_ADAPTER_KEY => 'Zend_Cloud_StorageService_Adapter_S3',
					\Zend_Cloud_StorageService_Adapter_S3::AWS_ACCESS_KEY => self::$cloud_options['ACCESS_KEY'],
					\Zend_Cloud_StorageService_Adapter_S3::AWS_SECRET_KEY => self::$cloud_options['SECRET_KEY'],
					\Zend_Cloud_StorageService_Adapter_S3::BUCKET_NAME => $bucket
				));

			$obj->getClient()->setEndpoint('http://' . self::$cloud_options['S3_ENDPOINT']);

			self::$cloud_storage[$bucket] = $obj;
		}
	
		return self::$cloud_storage[$bucket];
	}

	public static function setCloudOptions(array $options) {
		self::$cloud_options = $options;
	}

	public static function getCloudOptions() : array {
		return self::$cloud_options;
	}

	/**
	 * Options for S3 cloud storage helper function
	 * Will return an options for public accessed object with Cache-Control=86400 header
	 *
	 * @param bool $public
	 * @param int $cache
	 * @return array
	 */
	public static function getS3Options(bool $public = true, int $cache = 86400) : array {
		$options = array();

		if ($public === true) {
			$options[\Zend_Cloud_StorageService_Adapter_S3::METADATA]
					[\Zend_Service_Amazon_S3::S3_ACL_HEADER] = \Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ;
		}

		if ($cache > 0) {
			$options[\Zend_Cloud_StorageService_Adapter_S3::METADATA]
					['Cache-Control'] = (int)$cache;
		}

		return $options;
	}
	
}