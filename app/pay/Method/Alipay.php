<?php

namespace Pay\Method;

use Config, Exception, EventData, Log, Order, User;
use Common\Library\EventHelper;
use Common\Library\InputHelper;
use Pay\Exception\OrderException;
use Pay\Method\Alipay\AlipayNotify;
use Pay\Method\Alipay\AlipaySubmit;

class Alipay extends PaymentMethodAbstract
{
    protected $notifyUrl;
    protected $returnUrl;
    protected $config;
    protected $paymentAgent = 'alipay';


    protected function _processNotifyAction()
    {
        try {
            $notifyData = InputHelper::get();
            //验证是否是支付宝发来的消息
            $alipayNotify = new AlipayNotify($this->config);
            $verifyAlipay = $alipayNotify->verifyNotify($notifyData);
            if (!$verifyAlipay) {
                $result = array(array('error_msg' => __('服务器配置设置错误,签名错误'), 'error_code' => 500), 500);
            } else {
                $order = $this->_initOrderByAppgameOrderId(fnGet($notifyData, 'out_trade_no'));
                $status = $order->getData('status');
                $result = array(
                    array(
                        'trade_id' => $order->getData('trade_id'),
                        'appgame_order_id' => $order->getData('appgame_order_id'),
                        'amount' => $order->getData('amount'),
                        'status' => $status
                    )
                );
                $AliPayStatus = fnGet($notifyData, 'trade_status');
                if ($AliPayStatus === 'WAIT_BUYER_PAY') {
                    $result = array('WAIT_BUYER_PAY');
                } else {
                    if ($order->canFinishTransaction()) {
                        if ($AliPayStatus) {
                            ($txnId = fnGet($notifyData, 'trade_no')) and $order->setData('txn_id', $txnId);
                            if (($AliPayStatus === 'TRADE_FINISHED' || $AliPayStatus === 'TRADE_SUCCESS')) {
                                $order->complete(__('支付宝状态更改[异步通知]'));
                            } else {
                                $failureMessage = ' STATUS:' . $AliPayStatus;
                                $order->fail(__('支付宝状态更改[异步通知]:message', array('message' => $failureMessage)));
                            }
                            $order->setOrderData('status_callback_result', $notifyData);
                            if (!empty($failureMessage)) {
                                $order->setOrderData('failure_message', $failureMessage);
                            }
                            $order->save();
                            $order->callback();
                            $result[0]['status'] = $order->getData('status');
                            $eventData = new EventData(array(
                                'model' => 'AlipayBeanstalkd',
                                'method' => 'AlipayCallBack',
                                'order' => $order,
                                'queryResult' => $notifyData
                            ));
                            EventHelper::listen('process_beanstalk', $eventData);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = array(array('error_msg' => __('服务器内部错误'), 'error_code' => 500), 500);
        }
        return $result;
    }

    protected function _processMobileWebAlipay($data)
    {
        try {
            $cashToPay = fnGet($data, 'cash_to_pay', fnGet($data, 'amount'));
            $alipaySubmit = new AlipaySubmit($this->config);
            $data['callback_url'] = $data['developerurl'];
            unset($data['developerurl']);
            $data['cash_to_pay'] = $cashToPay;
            $data['order_prefix'] = 'ALI';
            $data['currency'] = fnGet($data, 'currency','CNY');
            $data['amount_in_currency'] = fnGet($data, 'amount_in_currency',fnGet($data, 'amount'));
            $order = Order::prepareOrder($data);
            $orderData = $order->getOrderData();
            $queryData = array(
                "service" => "alipay.wap.create.direct.pay.by.user",
                "partner" => trim($this->config['partner']),
                "seller_id" => trim($this->config['seller_id']),
                "payment_type" => $this->config['payment_type'],
                "notify_url" => $this->notifyUrl,
                "return_url" => $this->returnUrl,
                "out_trade_no" => $order->getData('appgame_order_id'),
                "subject" => $order->getData('product_name'),
                "total_fee" => $order->getData('cash_to_pay')
            );
            $order->setOrderData(array_merge($queryData, $orderData->getData('data')));
            $requestUrl = $alipaySubmit->buildRequestFormUrl($queryData);
            $orderData->setData('request_data', $queryData);
            $order->save();
            $result = array(
                array(
                    'trade_id' => $order->getData('trade_id'),
                    'appgame_order_id' => $order->getData('appgame_order_id'),
                    'amount' => $order->getData('amount'),
                    'coupon_amount' => $order->getOrderData('coupon_amount') * 1,
                    'balance_amount' => $order->getOrderData('balance_amount') * 1,
                    'cash_to_pay' => $order->getData('cash_to_pay'),
                    'status' => $order->getData('status'),
                    'request_url' => $requestUrl,
                ),
                'order' => $order
            );
            //队列 支付宝手机网站支付请求
            $eventData = new EventData(array(
                'model' => 'AlipayBeanstalkd',
                'method' => 'mobileWebAlipay',
                'order' => $order,
                //'queryResult' => $queryResult
            ));
            EventHelper::listen('process_beanstalk', $eventData);

        } catch (OrderException $e) {
            $result = static::prepareErrorResponse($e);
        } catch (Exception $e) {
            Log::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = static::prepareErrorResponse($e, __('服务器内部错误'), 500, 500);
        }
        return $result;
    }

    /**
     * @param $data
     * @return array
     *
     */
    protected function _processMobileAlipay($data)
    {
        try {
            $user = fnGet($data, 'user');
            $client = fnGet($data, 'client');
            if (!$user || !$client) {
                throw new Exception(__('用户或客户端无效'));
            }
            $cashToPay = fnGet($data, 'cash_to_pay', fnGet($data, 'amount'));
            $data['cash_to_pay'] = $cashToPay;
            $data['order_prefix'] = 'ALI';
            $data['userip'] = get_client_ip();
            $data['callback_url'] = $data['developerurl'];
            $data['currency'] = fnGet($data, 'currency','CNY');
            $data['amount_in_currency'] = fnGet($data, 'amount_in_currency',fnGet($data, 'amount'));
            unset($data['developerurl']);
            $order = Order::prepareOrder($data);
            $order->save();
            $result = array(
                array(
                    'trade_id' => $order->getData('trade_id'),
                    'appgame_order_id' => $order->getData('appgame_order_id'),
                    'amount' => $order->getData('amount'),
                    'coupon_amount' => $order->getOrderData('coupon_amount') * 1,
                    'balance_amount' => $order->getOrderData('balance_amount') * 1,
                    'cash_to_pay' => $order->getData('cash_to_pay'),
                    'status' => $order->getData('status')
                ),
                'order' => $order
            );
            // 队列 支付宝支付请求
            $eventData = new EventData(array(
                'model' => 'AlipayBeanstalkd',
                'method' => 'MobileAlipayRequest',
                'order' => $order,
            ));
            EventHelper::listen('process_beanstalk', $eventData);
        } catch (OrderException $e) {
            $result = static::prepareErrorResponse($e);
        } catch (Exception $e) {
            Log::logException($e);
            $result = static::prepareErrorResponse($e, __('服务器内部错误'), 500, 500);
        }

        return $result;
    }

    public function _processReturnAction()
    {
        try {
            $returnData = InputHelper::get();
            $alipayNotify = new AlipayNotify($this->config);
            $verifyResult = $alipayNotify->verifyReturn($returnData);
            if (!$verifyResult) {
                $result = array(array('error_msg' => __('服务器配置设置错误,签名错误'), 'error_code' => 500), 500);
            } else {
                $order = $this->_initOrderByAppgameOrderId(fnGet($returnData, 'out_trade_no'));
                $status = $order->getData('status');
                $result = array(
                    array(
                        'trade_id' => $order->getData('trade_id'),
                        'appgame_order_id' => $order->getData('appgame_order_id'),
                        'amount' => $order->getData('amount'),
                        'status' => $status
                    )
                );
                if ($order->canFinishTransaction()) {
                    $AliPayStatus = fnGet($returnData, 'trade_status');
                    $isSuccess = fnGet($returnData, 'is_success');
                    if ($AliPayStatus && $isSuccess === 'T') {
                        ($txnId = fnGet($returnData, 'trade_no')) and $order->setData('txn_id', $txnId);
                        if (($AliPayStatus === 'TRADE_FINISHED' || $AliPayStatus === 'TRADE_SUCCESS')) {
                            $order->complete(__('支付宝状态更改[同步通知]'));
                        } else {
                            $failureMessage = ' STATUS:' . $AliPayStatus;
                            $order->fail(__('支付宝状态更改[同步通知]:message', array('message' => $failureMessage)));
                        }
                        $order->setOrderData('status_callback_result', $returnData);
                        if (!empty($failureMessage)) {
                            $order->setOrderData('failure_message', $failureMessage);
                        }
                        $order->save();
                        $order->callback();
                        $result[0]['status'] = $order->getData('status');
                        $eventData = new EventData(array(
                            'model' => 'AlipayBeanstalkd',
                            'method' => 'AlipayReturn',
                            'order' => $order,
                            'queryResult' => $returnData
                        ));
                        EventHelper::listen('process_beanstalk', $eventData);
                    }
                }
            }
        } catch (Exception $e) {
            Log::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = array(array('error_msg' => __('服务器内部错误'), 'error_code' => 500), 500);
        }
        return $result;

    }


    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $data = fnGet($params, 'data');
        if (method_exists($this, $method)) {
            $this->config = (array)Config::get('payment.alipay');
            $this->notifyUrl = url($this->config['notify_url'], array(), Config::get('application.HTTPS'));
            $this->returnUrl = url($this->config['return_url'], array(), Config::get('application.HTTPS'));
            return $this->$method($data);
        }
        return false;
    }
}
