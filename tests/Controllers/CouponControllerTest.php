<?php

namespace Tests\Controllers;

use Tests\BaseController;
use Common\Library\HttpClient;

class CouponControllerTest extends BaseController
{

    public static $coupon_code;
    public static $PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCpOa4XwP3TLWnuhdRXbsHJ6bJM
/6wDNeR7zWOge/NePhjERRe/1ZOxC8hyo0hdi3pKvVOABIicQQ72UZxoEuAohMPL
/oSy9c0kdf3UyCzHtMB0MpPcjpVNcf5d+zOzQ6w8QC2H6y4+qSFbte8rEIkM+ljh
RzP1y9ohrnpf8BHd6QIDAQAB
-----END PUBLIC KEY-----';

    /**
     *测试 newbieCreate 创建、激活新手抵用卷 (是否成功)
     *
     */
    public function testNewbieCreateSuccess()
    {
        $this->getDB()->delete('coupon', "type = 'newbie'");
        $posta = array(
            "access_token" => "RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl"
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/coupon/newbieCreate', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('activation_wait', $res);

        //激活
        $postb = array(
            "access_token" => "RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl"
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/coupon/newbieActivate', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('status', $res);
    }


    /**
     *测试 couponList 抵用卷列表 (是否成功)
     *
     */
    public function testCouponListSuccess()
    {
        $posta = array(
            "access_token" => "RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl",
            "client_id" => "current",
            "position" => "0",
            "page_size" => "20"
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/coupon/couponList', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('coupon_list', $res);
        if ($res) {
            $res_obj = json_decode($res);
            self::$coupon_code = $res_obj->coupon_list[0]->coupon_code;
        }
    }


    /**
     *测试  使用抵用卷--储蓄卡支付 (是否成功)
     *
     */
    public function testCouponPaySuccess()
    {
        $httpClient = new HttpClient;
        $public_key = openssl_pkey_get_public(self::$PUBLIC_KEY);
        $cardno = openssl_public_encrypt(440444444104252920, $cardno, $public_key) ? base64_encode($cardno) : null;
        $idcard = openssl_public_encrypt(440444444104252920, $idcard, $public_key) ? base64_encode($idcard) : null;
        $owner = openssl_public_encrypt('test', $owner, $public_key) ? base64_encode($owner) : null;
        $phone = openssl_public_encrypt('13666666666', $phone, $public_key) ? base64_encode($phone) : null;
        $trade_id = substr(md5(rand(100000, 999999)), 0, 8) . rand(100000, 999999);
        $posta = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'developerurl' => 'http://client.local/callback',
            'cardno' => $cardno,
            'idcard' => $idcard,
            'owner' => $owner,
            'phone' => $phone,
            'amount' => '6',
            'product_name' => 'test',
            'game_server_id' => '1',
            'terminalid' => 'test',
            'trade_id' => $trade_id,
            'coupon_code' => self::$coupon_code
        );
        $res = $httpClient->request($this->base_url . 'test/yeepay/debitPayRequest', $posta, 'POST');
        $this->assertJson($res);
        $this->assertContains('trade_id', $res);


        //确认支付
        $postb = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            "trade_id" => $trade_id,
            "sign" => 'the_signature',
            "validatecode" => ""
        );
        $res = $httpClient->request($this->base_url . 'test/yeepay/confirmPay', $postb, 'POST');
        $this->assertJson($res);
        $this->assertContains('trade_id', $res);


        //查询支付
        $postd = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            "trade_id" => $trade_id,
            "sign" => 'the_signature',
            "validatecode" => ""
        );
        $res = $httpClient->request($this->base_url . 'test/yeepay/queryOrderStatus', $postd, 'POST');
        $this->assertJson($res);
        $this->assertContains('processing', $res);
        if ($res) {
            $res_obj = json_decode($res);
            $appgame_order_id = $res_obj->appgame_order_id;
        }


        //支付回调
        $postc = array(
            "amount" => "0.01",
            "bank" => "工商银行贷记卡",
            "bankcardtype" => "1",
            "bindid" => "",
            "bindvalidthru" => "",
            "identityid" => "",
            "identitytype" => "",
            "lastno" => "5420",
            "merchantaccount" => "YB01000000258",
            "orderid" => $appgame_order_id,
            "status" => 1,
            "closetime" => "",
            "yborderid" => 'YB' . rand(1000000, 9999999),
            "sign" => ""
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url . 'test/yeepay/callback', $postc, 'POST');
        $this->assertContains('ok', $res);

    }

}
