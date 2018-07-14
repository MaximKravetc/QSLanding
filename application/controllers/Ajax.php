<?php
/**
 * @property AjaxModel model
 */
class AjaxController extends ControllerAbstract {

	public function usersAction() {
        Yaf_Application::app()->getDispatcher()->disableView();

        echo $this->model->getUsersList();
    }

}
