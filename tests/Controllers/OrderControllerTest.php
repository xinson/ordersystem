<?php
namespace Tests\Controllers;

use Tests\BaseController;
use Common\Library\HttpClient;

class OrderControllerTest extends BaseController
{

    public static $trade_id;

    /**
     *测试 payedClientList 消费过的客户端 (是否成功)
     *
     */
    public function testPayedClientListSuccess()
    {
        $postb = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/order/payedClientList', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('clients', $res);
    }


    /**
     *测试 orderList 订单列表 (是否成功)
     *
     */
    public function testorderListSuccess()
    {
        $postb = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'client_id' => 'current',
            'condition' => json_encode(array('pending', 'complete')),
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/order/orderList', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('order_list', $res);

    }


    /**
     *测试 cancel 取消订单
     *
     */
    public function testCancelSuccess()
    {

        $trade_id = substr(md5(rand(100000,999999)),0,8).rand(100000,999999);
        $postb = array(
            "access_token" => "RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl",
            "trade_id" =>  $trade_id,
            "coupon_code" => "",
            "developerurl" => "http://game-cp.com/callback",
            "amount" => 10,
            "product_name" => "test",
            "game_server_id" => "1",
            "terminalid" => "test",
            "sign" => "the_signature",
            "extra_data" => "{private_info:'private_info'}"
        );

        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/alipay/mobilePayRequest', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('trade_id',$res);


        $postc = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'trade_id' => $trade_id,
            'sign' => 'sign'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/order/cancel', $postc, 'POST');
        $this->assertJson($res);
        $this->assertContains('canceled', $res);
    }

    public function testCancleOrder()
    {
        $postd = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'trade_id' => '---123456',
            'sign' => 'sign'
        );
        $httpClient = new HttpClient();
        $res = $httpClient->request($this->base_url.'test/order/cancel', $postd, 'POST');
        $this->assertJson($res);
        $this->assertContains('error_code',$res);
    }

}
