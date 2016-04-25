<?php
namespace Tests\Controllers;

use Tests\BaseController;
use Common\Library\HttpClient;

class SettingControllerTest extends BaseController
{

    public function testIndex()
    {
        $posta = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings', $posta, 'POST');
        $this->assertContains("password",$res);
    }

    /**
     *测试 changePassword/password  修改/设置支付密码
     *
     */
    public function testChangePasswordSuccess(){
        $posta = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'old_password' => '123456',
            'password' => 'x123456',
            'password_confirmation' => 'x123456',
            'sign' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/changePassword', $posta, 'POST');
        $this->assertContains("success",$res);

        $postb = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'old_password' => 'x123456',
            'password' => '123456',
            'password_confirmation' => '123456',
            'sign' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/changePassword', $postb, 'POST');
        $this->assertContains("success",$res);


        $postc = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'old_password' => 'wrong',
            'password' => '123456',
            'password_confirmation' => '123456',
            'notify_id' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/changePassword', $postc, 'POST');
        $this->assertContains("error_msg",$res);

    }


    /**
     *测试 forgetPassword 忘记支付密码
     *
     */
    public function testForgetPasswordSuccess(){
        $posta = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'password' => 'x123456',
            'password_confirmation' => 'x123456',
            'id_card' => '440444444104252920',
            'owner' => 'test',
            'phone' => '13400003065',
            'cardno' => '6212332008002991611',
            'sign' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/forgetPassword', $posta, 'POST');
        $this->assertContains("success",$res);

        $postb = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'password' => '123456',
            'password_confirmation' => '123456',
            'id_card' => '440444444104252920',
            'owner' => 'test',
            'phone' => '13400003065',
            'cardno' => '6212332008002991611',
            'sign' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/forgetPassword', $postb, 'POST');
        $this->assertContains("success",$res);

        $postc = array(
            'access_token' => 'RtRByZaHLzUhsXNUPw2Th7zMNTn8Ib5MPoi55sYl',
            'password' => '123456',
            'password_confirmation' => 'wrong',
            'id_card' => '440444444104252920',
            'owner' => 'test',
            'phone' => '13400003065',
            'cardno' => '6212332008002991611',
            'sign' => 'the_signature'
        );
        $httpClient = new HttpClient;
        $res = $httpClient->request($this->base_url.'test/settings/forgetPassword', $postc, 'POST');
        $this->assertContains("error_msg",$res);

    }

}
