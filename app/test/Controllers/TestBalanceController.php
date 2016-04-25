<?php

namespace Test\Controllers;

use Api\Controllers\BalanceController;

class TestBalanceController extends BalanceController
{

    protected function checkSign($sign = '')
    {
        return true;
    }

    public function pay()
    {
        $user = $this->getDI()->get('db')->fetchOne("select * from `user` where `username` = 'tester2015' ");
        $client = $this->getDI()->get('db')->fetchOne("select * from `client` where `name` = 'tester2015'");
        $this->userInfo = array(
            'user_id' => fnGet($user, 'id'),
            'client_id' => fnGet($client,'id'),
            'username' => fnGet($user, 'username'),
            'session_data' => array()
        );
        S('userInfo.' . fnGet($user, 'id'), $this->userInfo, 3600);
        parent::pay();
    }
}
