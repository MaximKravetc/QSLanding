<?php

use Shelby\Dao\Result\Mongodb\Insert;

class IndexModel extends ModelAbstract {

    public function addSubscriber(string $name, string $email, string $ua, string $ip) : Insert {
        return Dao\Mongodb\Listing\Quantumsystem\Subscribers::getInstance()->insertEntry([
            'email' => $email,
            'name'  => $name,
            'ua'    => $ua,
            'ip'    => $ip
        ]);
    }

}
