<?php

use Dao\Mongodb\Listing\Quantumsystem\Subscribers;

class AjaxModel extends ModelAbstract {

    public function getUsersList() {
        $users_list = Subscribers::getInstance()->getList();

        $users = [];
        foreach ($users_list as $user) {

            $users[] = [
                $user['_id'],
                $user['name'],
                $user['email'],
                $user['phone'],
                date('m/d/Y H:i:s', $user['date']->toDateTime()->getTimestamp()),
                $user['ip'],
                $user['ua']
            ];
        }

        return json_encode([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            "recordsTotal" => $users_list->count(false),
            "recordsFiltered" => $users_list->count(false),
            'data' => $users
        ]);
    }

}
