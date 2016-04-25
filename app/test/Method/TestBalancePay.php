<?php
namespace Test\Method;

use Pay\Method\BalancePay;
use Common\Library\Session;

class TestBalancePay extends BalancePay {


    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $data = fnGet($params, 'data');
        if (method_exists($this, $method)) {
            Session::getInstance()->getUser()->getBalance()->setData('amount',10)->save();
            return $this->$method($data);
        }
        return false;
    }

}
