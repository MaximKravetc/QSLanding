<?php

class IndexModel extends ModelAbstract {

    public function addUser($name, $email, $phone, $ip, $ua) {
        \Dao\Mongodb\Listing\Quantumsystem\Subscribers::getInstance()->insertEntry([
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
            'ip'    => $ip,
            'ua'    => $ua
        ]);
    }

}
