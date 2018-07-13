<?php
/**
 * @property IndexModel model
 */
class IndexController extends ControllerAbstract {

	public function indexAction() {
        if ($this->getRequest()->isPost() === true) {
            $email = trim(strip_tags($this->getRequest()->getPost('email', '')));
            $name = trim(strip_tags($this->getRequest()->getPost('name', '')));

            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {

                try {
                    $mailObj = new Zend_Mail('utf-8');
                    $mailObj->addTo('chapligin.vitaly@yandex.ru');
                    $mailObj->setFrom('chapligin.vitaly@yandex.ru');
                    $mailObj->setSubject('Новый пользователь:');
                    $mailObj->setBodyHtml( '<p>' . $name . '</p><p>' . $email . '</p>');
                    $mailObj->send();
                } catch (Exception $e) {
                    echo $e->getMessage() . PHP_EOL;
                    exit(-1);
                }

                $this->_setLastActionResult(true);
            }

        }
	}

}
