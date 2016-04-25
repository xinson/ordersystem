<?php
namespace Common\Library\Statistics;

use Common\Library\QueueHelper;
use Pay\Models\Order;

class AlipayBeanstalkd
{

    /**
     * 队列 支付宝支付请求
     * @param Order $order
     */
    public function _processMobileAlipayRequest($order)
    {
        $jobData = array(
            //基础信息
            "client_id" => $order->getClient()->getData('client'),
            "username" => $order->getData('username'),
            "time" => $order->getData('created_at'),
            "ip" => $order->getOrderData('userip'),
            "extra_data" => $order->getOrderData('extra_data'),
            //订单信息
            //"action"  => "payment_request",
            "status" => $order->getData('status'),
            "trade_id" => $order->getData('trade_id'),
            "appgame_order_id" => $order->getData('appgame_order_id'),
            "amount" => $order->getData('amount'),
            "product_name" => $order->getData('product_name'),
            //支付平台信息
            "payment_agent" => $order->getData('payment_agent'),
            "payment_method" => $order->getData('payment_method'),
            //支付金额信息
            "coupon_code" => $order->getOrderData('coupon_code'),
            "coupon_amount" => $order->getOrderData('coupon_amount'),
            "balance_amount" => $order->getOrderData('balance_amount'),
            "cash_to_pay" => $order->getOrderData('cash_to_pay'),
            "currency" => $order->getOrderData('currency'),
            "amount_in_currency" => $order->getOrderData('amount_in_currency')
        );
        QueueHelper::Putintube('pay_event_create', $jobData);
    }

    /**
     * 队列 支付宝支付异步通知
     * @param Order $order
     * @param $queryResult
     */
    public function _processAlipayCallBack($order, $queryResult)
    {
        $jobData = array(
            //基础信息
            "client_id" => $order->getClient()->getData('client'),
            "username" => $order->getData('username'),
            "time" => time(),
            "ip" => $order->getOrderData('userip'),
            //支付信息
            "txn_id" => fnGet($queryResult, 'trade_no'),
            "appgame_order_id" => $order->getData('appgame_order_id'),
            "buyer_id" => fnGet($queryResult, 'buyer_id'),
            "buyer_email" => fnGet($queryResult, 'buyer_email'),
            "cash_paid" => $order->getData('cash_paid'),
            "amount" => $order->getData('amount'),
            "callback_result" => $order->getOrderData('status_callback_result'),
            //支付结果
            "error_msg" => $order->getOrderData('failure_message'),
            "status" => $order->getData('status')
        );
        QueueHelper::Putintube('pay_event_callback', $jobData);
    }


    /**
     * 队列 支付宝支付同步通知
     * @param Order $order
     * @param $queryResult
     */
    public function _processAlipayReturn($order, $queryResult)
    {
        $jobData = array(
            //基础信息
            "client_id" => $order->getClient()->getData('client'),
            "username" => $order->getData('username'),
            "time" => time(),
            "ip" => $order->getOrderData('userip'),
            //支付信息
            "txn_id" => fnGet($queryResult, 'trade_no'),
            "cash_paid" => $order->getData('cash_paid'),
            "amount" => $order->getData('amount'),
            "appgame_order_id" => $order->getData('appgame_order_id'),
            "callback_result" => $order->getOrderData('status_callback_result'),
            //支付结果
            "error_msg" => $order->getOrderData('failure_message'),
            "status" => $order->getData('status')
        );
        QueueHelper::Putintube('pay_event_callback', $jobData);
    }


    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $order = fnGet($params, 'order');
        $queryResult = fnGet($params, 'queryResult');
        if (method_exists($this, $method)) {
            return $this->$method($order, $queryResult);
        }
        return false;
    }


}
