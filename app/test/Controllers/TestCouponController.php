<?php
/**
 * 抵用卷模块
 * User: dgw
 * Date: 2015-5-5
 */

namespace Test\Controllers;

use Api\Controllers\CouponController;
use Common\Library\InputHelper;

class TestCouponController extends CouponController
{

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
    }


}
