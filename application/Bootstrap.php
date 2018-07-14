<?php
setlocale(LC_ALL, 'en_US.UTF-8');

mb_regex_encoding('UTF-8');
mb_internal_encoding('UTF-8');
mb_detect_order(array('UTF-8', 'ISO-8859-1'));

class Bootstrap extends Yaf_Bootstrap_Abstract {

    protected function _initAutoload() {
        require(ROOT_PATH . '/application/library/vendor/autoload.php');
    }

	protected function _initLogger() {
		$config = Yaf_Application::app()->getConfig()->get('resources.log')->toArray();
		
		$logger = Zend_Log::factory($config);
		\Shelby\Dao\AbstractClass::setLogger($logger);
	}

	protected function _initDao() {
		$mongo = Yaf_Application::app()->getConfig()->get('datastore.mongodb')->toArray();
		\Shelby\Dao\Mongodb\AbstractClass::init($mongo);

        $config = Yaf_Application::app()->getConfig()->offsetGet('resources.mail')->toArray();
        $transport = new Zend_Mail_Transport_Smtp($config['transport']['host'], $config['transport']);
        Zend_Mail::setDefaultTransport($transport);
        Zend_Mail::setDefaultFrom($config['defaultFrom']['email'], $config['defaultFrom']['name']);
	}

	protected function _initSmarty(Yaf_Dispatcher $dispatcher) {
		require(ROOT_PATH . '/application/library/Smarty/Smarty.class.php');

		$config = Yaf_Application::app()->getConfig()->get('smarty')->toArray();
		$smarty = new \View\Frontend($config);

		$dispatcher->setView($smarty);
	}

}
