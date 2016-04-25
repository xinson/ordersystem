<?php

namespace Pay\Method;

use Exception, Pay\Models\Order;
use Pay\Models\Helper;
use Pay\Exception\OrderException;
use Common\Models\User as UserModel;

abstract class PaymentMethodAbstract
{
    /**
     * @var Helper
     */
    protected $helper;
    /**
     * @var Order
     */
    protected $order;
    protected $paymentAgent;
    protected $user = null;
    protected $sandbox = false;

    public function __construct()
    {
        $this->_helper = Helper::getInstance('Pay\Models\Helper');
    }

    abstract public function process($params);

    protected function _initOrder($tradeId, $clientId, $userId)
    {
        if ($this->order === null) {
            $this->order = $this->_helper->initOrder($tradeId, $clientId, $userId);
        }
        if ($this->order->getData('payment_agent') != $this->paymentAgent) {
            throw new OrderException(__('支付提供商不匹配'), OrderException::ERROR_CODE_PAYMENT_AGENT_NOT_MATCH);
        }
        return $this->order;
    }

    protected function _initOrderByAppgameOrderId($appgameOrderId)
    {
        if ($this->order === null) {
            $this->order = $this->_helper->initOrderByAppgameOrderId($appgameOrderId);
        }
        if ($this->order->getData('payment_agent') != $this->paymentAgent) {
            throw new OrderException(__('支付提供商不匹配'), OrderException::ERROR_CODE_PAYMENT_AGENT_NOT_MATCH);
        }
        return $this->order;
    }

    public static function prepareErrorResponse(
        Exception $e,
        $errorMessage = null,
        $errorCode = null,
        $statusCode = null
    ) {
        $errorMessage === null and $errorMessage = $e->getMessage();
        $errorCode === null and $errorCode = (string)$e->getCode();
        if ($statusCode === null) {
            if (($prefix = $errorCode{0}) && in_array($prefix, array(4, 5))) {
                $statusCode = $prefix . '00';
            } else {
                $statusCode = 500;
            }
        }
        $result = array(array('error_msg' => $errorMessage, 'error_code' => $errorCode), $statusCode);
        return $result;
    }

    protected function _initUser($userId)
    {
        if ($this->user === null) {
            $this->user = (new UserModel())->findFirst($userId) ?: null;
        }
        return $this->user;
    }

}
