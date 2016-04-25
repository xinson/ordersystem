<?php
/**
 * 订单模块.
 * User: dgw
 */

namespace Test\Controllers;

use Api\Controllers\OrderController;
use Common\Library\InputHelper;
use Common\Library\Session;
use Common\Models\Client;
use Common\Models\User;

class TestOrderController extends OrderController
{

    protected function checkSign($sign = '')
    {
        return true;
    }
    public function initialize()
    {
        $this->input = InputHelper::get();
        $user = $this->getDI()->get('db')->fetchOne("select * from `user` where `username` = 'tester2015' ");
        $client = $this->getDI()->get('db')->fetchOne("select * from `client` where `name` = 'tester2015'");
        $this->userInfo = array(
            'user_id' => fnGet($user, 'id'),
            'client_id' => fnGet($client,'id'),
            'username' => fnGet($user, 'username'),
            'session_data' => array()
        );
        S('userInfo.' . fnGet($user, 'id'), $this->userInfo, 3600);
        Session::getInstance()->setUser($user = User::findFirst(fnGet($this->userInfo, 'user_id')));
        Session::getInstance()->setClient($client = Client::findFirst(fnGet($this->userInfo, 'client_id')));
        unset($this->input['sign']);
        $this->input['user'] = $user;
        $this->input['client'] = $client;
        $this->input = array_merge($this->input, $this->userInfo);

    }


}
