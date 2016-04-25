<?php
namespace Test\Controllers;

use Api\Controllers\AlipayController;

class TestAlipayController extends AlipayController
{

    protected function checkSign($sign = '')
    {
        return true;
    }

    protected function _init()
    {
        $user = $this->getDb()->fetchOne("select * from `user` where `username` = 'tester2015' ");
        $client = $this->getDb()->fetchOne("select * from `client` where `name` = 'tester2015'");
        $this->userInfo = array(
            'user_id' => fnGet($user, 'id'),
            'client_id' => fnGet($client,'id'),
            'username' => fnGet($user, 'username'),
            'session_data' => array()
        );
        S('userInfo.' . fnGet($user, 'id'), $this->userInfo, 3600);

        parent::_init();
    }

}
