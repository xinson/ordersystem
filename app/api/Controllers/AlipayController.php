<?php
namespace Api\Controllers;

use Common\Library\EventHelper;
use Pay\Method\PaymentMethodAbstract;
use Exception;
use Common\Library\EventData;
use Api\Events\ProcessPayment;
use Common\Library\Session;

class AlipayController extends BaseController
{

    protected function _init()
    {
        $this->checkUser($this->input['username'],$this->input['client']);
        $this->checkSign($this->input['sign']);
        $this->checkPaymentParameters();
        $session = Session::getInstance();
        $user = $session->getUser()->getData();
        $client = $session->getClient()->getData();
        $this->input['user'] = $user;
        $this->input['client'] = $client;
        $this->input = array_merge($this->input, $this->userInfo);
    }

    public function mobilePayRequest()
    {
        $this->_init();
        $params = array(
            'payment_agent' => 'alipay',
            'method' => 'mobile_alipay',
            'data' => $this->input,
            'suppress_coupon_exception' => true,
        );
        try {
            ProcessPayment::run($event = new EventData($params));
            $result = $event->getData('result');
        } catch (Exception $e) {
            $result = PaymentMethodAbstract::prepareErrorResponse($e);
        }

        if (isset($result[0]['error_code'])) {
            $response = $result[0];
            $statusCode = $result[1];
        } else {
            $response = fnGet($result, 0);
            $statusCode = 200;
        }

        $this->ajaxReturn($response, $statusCode);


    }

    public function mobileWebPayRequest()
    {
        $this->_init();
        $params = array(
            'payment_agent' => 'alipay',
            'method' => 'mobile_web_alipay',
            'data' => $this->input,
            'suppress_coupon_exception' => true,
        );
        try {
            $pay = new ProcessPayment();
            $pay->run($event = new EventData($params));
            $result = $event->getData('result');
        } catch (Exception $e) {
            $result = PaymentMethodAbstract::prepareErrorResponse($e);
        }

        if (isset($result[0]['error_code'])) {
            $response = $result[0];
            $statusCode = $result[1];
        } else {
            $response = $result[0];
            $statusCode = 200;
        }
        $this->ajaxReturn($response, $statusCode);

    }

    public function returnAction()
    {
        $params = array(
            'payment_agent' => 'alipay',
            'method' => 'return_action',
            'data' => $this->input
        );
        $pay = new ProcessPayment();
        $pay->run($event = new EventData($params));
        session_start();
        $_SESSION = array();
        if(isset($_COOKIE[session_name()]))
        {
            setCookie(session_name(),'',time()-3600,'/');
        }
        session_destroy();
        foreach($_COOKIE as $key=>$value){
            setCookie($key,"",time()-60);
        }
        if ($event->getData('result/0/error_code')) {
            EventHelper::listen('before_ajax_return', 'fail');
            echo 'fail';
        }else{
            EventHelper::listen('before_ajax_return', 'success');
            echo 'success';
        }
    }


    public function notifyAction()
    {
        $params = array(
            'payment_agent' => 'alipay',
            'method' => 'notify_action',
            'data' => $this->input
        );
        ProcessPayment::run($event = new EventData($params));
        session_start();
        $_SESSION = array();
        if(isset($_COOKIE[session_name()]))
        {
            setCookie(session_name(),'',time()-3600,'/');
        }
        session_destroy();
        foreach($_COOKIE as $key=>$value){
            setCookie($key,"",time()-60);
        }
        if ($event->getData('result/0/error_code')) {
            EventHelper::listen('before_ajax_return', 'fail');
            echo 'fail';
        }else{
            EventHelper::listen('before_ajax_return', 'success');
            echo 'success';
        }
    }

}
