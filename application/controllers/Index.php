<?php
/**
 * @property IndexModel model
 */
class IndexController extends ControllerAbstract {

	public function indexAction() {
        if ($this->getRequest()->isPost() === true) {
            $email = trim(strip_tags($this->getRequest()->getPost('email', '')));
            $name = trim(strip_tags($this->getRequest()->getPost('name', '')));
            $ua = $this->getRequest()->getServer('HTTP_USER_AGENT');
            $ip = $this->getRequest()->getServer('REMOTE_ADDR');

            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->model->addSubscriber($name, $email, $ua, $ip);

                try {
                    $mailObj = new Zend_Mail('utf-8');
                    $mailObj->addTo('m.v.kravetc@gmail.com');
                    $mailObj->setFrom('m.v.kravetc@gmail.com');
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
