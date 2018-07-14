<?php
/**
 * @property UsersModel model
 */
class UsersController extends ControllerAbstract {

	public function listAction() {
        $key = trim(strip_tags($this->getRequest()->getParam('key', '')));

        if ($key !== 'fgnnkraj5342,ret354alsbfs_dmbfqw342134') {
            $this->_pageNotFound();
        }
	}

}
