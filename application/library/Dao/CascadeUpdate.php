<?php

namespace Dao;

use Shelby\Dao\Builder\Search;

class CascadeUpdate {

	private static $running = false;

	/**
	 * - Update Partner, FHGs, Tube-Clips
	 *
	 * @param int $id
	 * @param array $data
	 */
	public static function onSiteUpdate($id, Array $data) {
		if (self::$running === true) {
			return;
		}

		// Do not perform cascade update if at least one of these fields is set
		if (!isset($data['active']) && !isset($data['niches']) &&
			!isset($data['options']) && !isset($data['partner'])) {
			return;
		}

		$entry_site = \Dao\Mongodb\Listing\Sbd\Sites::getInstance()->getEntry($id);
		if ($entry_site->exists() === false) {
			return;
		}

		$daoPartners = \Dao\Mongodb\Listing\Sbd\Partners::getInstance();
		$entry_partner = $daoPartners->getEntry($entry_site['partner']['_id'])->get();
		if (empty($entry_partner)) {
			return;
		}

		self::$running = true;

		// Partner
		$partner_id = $entry_partner['_id'];
		unset($entry_partner['_id'], $entry_partner['search'], $entry_partner['counts'], $entry_partner['_meta']);
		$daoPartners->updateEntry($partner_id, $entry_partner);

		// Fhg and Tube-Clips
		$search = array();
		foreach ($entry_site['niches'] as $stream) {
			$search[] = array('s' => $stream['_id']);
			foreach ($stream['niches'] as $niche) {
				$search['n' . $niche['_id']] = array('n' => $niche['_id']);
				$search[] = array('s' . $stream['_id'] . 'n' => $niche['_id']);
			}
		}
		$data = array(
			'site' => array(
				'_id' => $entry_site['_id'],
				'name' => $entry_site['name'],
				'partner' => array(
					'_id' => $partner_id,
					'name' => $entry_partner['name']
				)
			),
			'search' => array_values($search)
		);

		$extra_params = Search::instance()->equals('site._id', $id);

		try {
			\Dao\Mongodb\Listing\Sbd\Sites\Fhg::getInstance()->updateEntries($data, $extra_params);
		} catch(\Exception $e) {}
		try {
			\Dao\Mongodb\Listing\Sbd\Sites\Fhg\Inactive::getInstance()->updateEntries($data, $extra_params);
		} catch(\Exception $e) {}

		try {
			\Dao\Mongodb\Listing\Sbd\Sites\TubeClips::getInstance()->updateEntries($data, $extra_params);
		} catch(\Exception $e) {}
		try {
			\Dao\Mongodb\Listing\Sbd\Sites\TubeClips\Inactive::getInstance()->updateEntries($data, $extra_params);
		} catch(\Exception $e) {}

		self::$running = false;
	}

	private function __construct() {}

	public static function onSiteDelete($id) {

	}

	/**
	 * Update Sites
	 *
	 * @param $id
	 */
	public static function onPartnerUpdate($id) {
		if (self::$running === true) {
			return;
		}

		self::$running = true;

		// Update all sites for this partner to reflect the changes
		$daoSites = new \Dao\Mongodb\Listing\Sbd\Sites();
		$daoSites->setListFields(array('_id' => 1, 'active' => 1, 'partner' => 1, 'options' => 1, 'niches' => 1));
		$list = $daoSites->getList(null, null,
			Search::instance()
				->equals('partner._id', $id)
		);

		foreach ($list as $site) {
			$sid = $site['_id'];
			unset($site['_id']);
			$daoSites->updateEntry($sid, $site);
		}

		self::$running = false;
	}

}