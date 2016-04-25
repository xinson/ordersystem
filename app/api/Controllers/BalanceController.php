<?php

namespace Api\Controllers;

use Api\Events\ProcessPayment;
use Common\Library\Session;
use Common\Library\EventData;
use Pay\Method\PaymentMethodAbstract;
use Exception;

class BalanceController extends BaseController
{

    public function pay()
    {
        $this->input = $this->getInput('');
        $this->checkUser($this->input['username'],$this->input['client']);
        $this->checkSign($this->getInput('sign'));
        $session = Session::getInstance();
        $user = $session->getUser()->getData();
        $client = $session->getClient()->getData();
        unset($this->input['sign']);
        $this->input['user'] = $user;
        $this->input['client'] = $client;

        $this->checkPaymentParameters();

        $this->input = array_merge($this->input, $this->userInfo);

        $params = array(
            'payment_agent' => 'balance_pay',
            'method' => 'balance_pay',
            'data' => $this->input,
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
}
