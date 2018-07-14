<?php
/**
 * @property IndexModel model
 */
class IndexController extends ControllerAbstract {

	public function indexAction() {
        if ($this->getRequest()->isPost() === true) {
            $email = trim(strip_tags($this->getRequest()->getPost('email', '')));
            $phone = trim(strip_tags($this->getRequest()->getPost('phone', '')));
            $name  = trim(strip_tags($this->getRequest()->getPost('name', '')));
            $ua    = $this->getRequest()->getServer('HTTP_USER_AGENT');
            $ip    = $this->getRequest()->getServer('REMOTE_ADDR');

            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
//                $this->model->addUser($name, $email, $phone, $ip, $ua);

                try {
                    $mailObj = new Zend_Mail('utf-8');
                    $mailObj->addTo('chapligin.vitaly@yandex.ru');
                    $mailObj->setSubject('Quantum System notifier');
                    $mailObj->setBodyHtml( 'Новый пользователь:<p>' . $name . '</p><p>' . $email . '</p><p>' . $phone . '</p>');
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
