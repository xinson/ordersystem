<?php
namespace Tests\Controllers;

use Common\Library\HttpClient;
use Coupon\Models\Coupon;
use Tests\BaseController;

class SupervisorTest extends BaseController
{

    /**
     * 测试 supervisor 测试日期以内登陆获 获取卷
     */
    public function testTheDateLogin()
    {
        $posta = array(
            'amount' => 4,
            'client' => '["test","tester2015"]',
            'create_at' => time()-(86400*6),
            'expire' => time()+86400,
            'type' => 'loginDay',
            'condition' => '',
            'sign' => 'the_signature',
            'number' => 10
        );
        $startDate = time()-(86400*6);
        $endDate = time()+86400;
        $condition = '{"triggerClient":["test","tester2015"],"startDate":'.$startDate.', "endDate":'.$endDate.', "day":1, "continuous":"true"}';
        $posta['condition'] = $condition;
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/internalCoupon/triggerCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('rule_id', $res);


        $jobData = array(
            'tube' => 'login',
            'data' => array(
                "user_id" => 1028047,
                "username" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "client_id" => "tester2015",
                "channel_id" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "tube" => "login"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);
        $data = $this->getDI()->get('db')->fetchOne('SELECT *  FROM `coupon` WHERE `type` = "loginDay" AND `status` = "activated" AND `create_at` <'.time());
        $this->assertContains('loginDay', $data);
    }

    /**
     * 测试 supervisor 测试固定日期登陆获取 获取卷
     */
    public function testFixedDateLogin()
    {
        $posta = array(
            'amount' => 4,
            'client' => '["test","tester2015"]',
            'create_at' => time(),
            'expire' => time()+86400,
            'type' => 'fixedDate',
            'condition' => '',
            'sign' => 'the_signature',
            'number' => 10
        );
        $startDate1 = time();
        $endDate1 = time()+86400;
        $startDate2 = time()+86400*3;
        $endDate2 = time()+86400*5;
        $condition = '{"triggerClient":["test","tester2015"],"dateBetween":[{"startDate":'.$startDate1.',"endDate":'.$endDate1.'},{"startDate":'.$startDate2.',"endDate":'.$endDate2.'}]}';
        $posta['condition'] = $condition;
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/internalCoupon/triggerCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('rule_id', $res);

        $jobData = array(
            'tube' => 'login',
            'data' => array(
                "user_id" => 1028047,
                "username" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "client_id" => "tester2015",
                "channel_id" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "tube" => "login"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);
        $data = $this->getDI()->get('db')->fetchOne('SELECT *  FROM `coupon` WHERE `type` = "fixedDate" AND `status` = "activated" AND `create_at` <'.time());
        $this->assertContains('fixedDate', $data);
    }

    /**
     * 测试 supervisor 测试注册 获取卷
     */
    public function testRegisterTime()
    {
        $jobData = array(
            'tube' => 'register',
            'data' => array(
                "user_id" => 1028047,
                "username" => "tester2015",
                "time" => time()-86400*10,
                "ip" => "192.168.177.121",
                "client_id" => "tester2015",
                "channel_id" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "tube" => "register"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);
        $this->assertContains('register', $jobData);

    }


    /**
     * 测试 supervisor 测试在线 获取卷
     */
    public function testOnlineTime()
    {
        $posta = array(
            'amount' => 4,
            'client' => '["test","tester2015"]',
            'create_at' => time()-86400*2,
            'expire' => time()+86400*3,
            'type' => 'onlineTime',
            'condition' => '',
            'sign' => 'the_signature',
            'number' => 10
        );
        $startDate = time()-86400*2;
        $endDate = time()+86400*3;
        $condition = '{"triggerClient":["test","tester2015"],"startDate":'.$startDate.', "endDate":'.$endDate.', "signDay":2, "duration":1}';
        $posta['condition'] = $condition;
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/internalCoupon/triggerCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('rule_id', $res);

        $jobData = array(
            'tube' => 'heartbeat',
            'data' => array(
                "uname" => "tester2015",
                "appkey" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "channel" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "tube" => "heartbeat"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);

        $jobData = array(
            'tube' => 'heartbeat',
            'data' => array(
                "uname" => "tester2015",
                "appkey" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "channel" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "tube" => "heartbeat"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);

        $data = $this->getDI()->get('db')->fetchOne('SELECT *  FROM `coupon` WHERE `type` = "heartbeat" AND `status` = "activated" AND `create_at` <'.time());
        $this->assertContains('heartbeat', $data);

    }

    /**
     * 测试 supervisor 测试单次充值 获取卷
     */
    public function testSingleMoneyTime()
    {
        $posta = array(
            'amount' => 4,
            'client' => '["test","tester2015"]',
            'create_at' => time()-86400*2,
            'expire' => time()+86400*3,
            'type' => 'singleMoney',
            'condition' => '',
            'sign' => 'the_signature',
            'number' => 10
        );
        $startDate = time()-86400*2;
        $endDate = time()+86400*3;
        $condition = '{"triggerClient":["test","tester2015"],"startDate":'.$startDate.', "endDate":'.$endDate.', "money":1}';
        $posta['condition'] = $condition;
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/internalCoupon/triggerCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('rule_id', $res);

        $jobData = array(
            'tube' => 'pay_event_callback',
            'data' => array(
                "user_id" => 1028047,
                "username" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "client_id" => "tester2015",
                "channel_id" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "status" => 'complete',
                "amount" => 1.0,
                "tube" => "pay_event_callback"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);
        $data = $this->getDI()->get('db')->fetchOne('SELECT *  FROM `coupon` WHERE `type` = "singleMoney" AND `status` = "activated" AND `create_at` <'.time());
        $this->assertContains('singleMoney', $data);
    }

    /**
     * 测试 supervisor 测试充值总额 获取卷
     */
    public function testSumMoneyTime()
    {
        $posta = array(
            'amount' => 4,
            'client' => '["test","tester2015"]',
            'create_at' => time()-86400*2,
            'expire' => time()+86400*3,
            'type' => 'sumMoney',
            'condition' => '',
            'sign' => 'the_signature',
            'number' => 10
        );
        $startDate = time()-86400*2;
        $endDate = time()+86400*3;
        $condition = '{"triggerClient":["test","tester2015"],"startDate":'.$startDate.', "endDate":'.$endDate.', "money":1}';
        $posta['condition'] = $condition;
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/internalCoupon/triggerCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('rule_id', $res);

        $jobData = array(
            'tube' => 'pay_event_callback',
            'data' => array(
                "user_id" => 1028047,
                "username" => "tester2015",
                "time" => time(),
                "ip" => "192.168.177.121",
                "client_id" => "tester2015",
                "channel_id" => "shenghao_test2",
                "device_id" => "000000000000000",
                "sdk_version" => "1.0.0",
                "extra_data" => [],
                "status" => 'complete',
                "amount" => 1.0,
                "tube" => "pay_event_callback"
            )
        );
        $coupon = new Coupon();
        $coupon->eventTable($jobData);
        $data = $this->getDI()->get('db')->fetchOne('SELECT *  FROM `coupon` WHERE `type` = "sumMoney" AND `status` = "activated" AND `create_at` <'.time());
        $this->assertContains('sumMoney', $data);
    }


}
