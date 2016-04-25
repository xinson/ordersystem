<?php

namespace Tests\Controllers;

use Tests\BaseController;
use Common\Library\HttpClient;

class AlipayControllerTest extends BaseController
{

    /**
     *测试 mobilePayRequest 支付宝移动支付 (是否成功)
     *
     */
    public function testMobilePayRequestSuccess(){
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
        $appgame_order_id = '';
        if($res){
            $res_obj = json_decode($res);
            $appgame_order_id = fnGet($res_obj, 'appgame_order_id');
        }


        //支付回调
        $postc = array(
            'out_trade_no' => $appgame_order_id,
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => rand(10000000,99999999),
            'notify_id' => ''
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/alipay/notifyAction', $postc, 'POST');
        $this->assertContains("success",$res);

        //测试 mobilePayRequest 支付宝移动支付 (报错情况)
        $trade_id = substr(md5(rand(100000,999999)),0,8).rand(100000,999999);
        $postb = array(
            "access_token" => "RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl",
            "trade_id" =>  $trade_id,
            "coupon_code" => "",
            "developerurl" => "http://game-cp.com/callback",
            "amount" => '',
            "product_name" => "test",
            "game_server_id" => "1",
            "terminalid" => "test",
            "sign" => "the_signature",
            "extra_data" => "{private_info:'private_info'}"
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/alipay/mobilePayRequest', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('error_msg',$res);
    }
}
