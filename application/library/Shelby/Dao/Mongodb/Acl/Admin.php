<?PHP

namespace Shelby\Dao\Mongodb\Acl;

use Shelby\Dao\Builder\Search;

class Admin extends \Shelby\Dao\Mongodb\AbstractClass {

	/**
	 * @var \Zend_Acl
	 */
	private $_acl;

	/**
	 * Available controllers and actions array
	 *
	 * @var array
	 */
	private $_resources_actions = array();

	/**
	 * Access control lists initialization
	 *
	 * @param $username string
	 */
	public function start($username) {
		$this->_acl = new \Zend_Acl();
		$this->_resources_actions = array();

		$resources_dp = new \Shelby\Dao\Mongodb\Listing\Acl\Resources();
		$groups_dp = new \Shelby\Dao\Mongodb\Listing\Acl\Groups();
		$admins_dp = new \Shelby\Dao\Mongodb\Listing\Acl\Admins();
		
		$user = $admins_dp->getEntryByLogin($username);

		if (empty($user) || empty($user['groups'])) {
			return;
		}
		
		$resources = $resources_dp->getList();

		// Add resources list to the ACL
		foreach ($resources as $res_el) {
			$this->_acl->addResource(new \Zend_Acl_Resource($res_el['_id']));
		}

		$groups = $groups_dp->getList(null, null,
				Search::instance()->in('_id', $user['groups'])
			)->get();

		foreach ($groups as $grp) {
			$this->_acl->addRole(new \Zend_Acl_Role($grp['name']));

			foreach ($grp['resources'] as $res_key => $res_el) {
				$actions = array_keys($res_el);
				$this->_acl->allow($grp['name'], $res_key, $actions);
				if (isset($this->_resources_actions[$res_key])) {
					$this->_resources_actions[$res_key] =
						array_merge($this->_resources_actions[$res_key], $res_el);
				} else {
					$this->_resources_actions[$res_key] = $res_el;
				}
			}
		}
	}
	
	/**
	 * Check user permissions for specified controller and action
	 *
	 * @param string $resource
	 * @param string $action
	 * @return boolean
	 */
	public function isAllowed($resource, $action) {
		$roles = $this->_acl->getRoles();
		
		try {
			foreach ($roles as $role) {
				$res = $this->_acl->isAllowed($role, strtolower($resource), strtolower($action));
				if ($res === true) {
					return true;
				}
			}
		} catch (\Zend_Acl_Exception $e) {
			// Resource not found
			return false;
		}
		
		return false;
	}
	

	/**
	 * Returns list of all available controllers and actions for the current user
	 *
	 * @return array
	 */
	public function getAllowedResourcesList() {
		return $this->_resources_actions;
	}
	
}