<?php
namespace Common\Library\Statistics;

use Common\Library\QueueHelper;
use Pay\Models\Order;


class BalanceBeanstalkd
{

    /**
     * 队列 余额支付请求
     *
     * @param Order $order
     */
    protected function _processBalancePay($order)
    {
        $jobData = array(
            //基础信息
            "client_id" => $order->getClient()->getData('client'),
            "username" => $order->getData('username'),
            "time" => $order->getData('created_at'),
            "ip" => $order->getData('userip'),
            "extra_data" => $order->getOrderData('extra_data'),
            //订单信息
            //"action"  => "payment_request",
            "trade_id" => $order->getData('trade_id'),
            "appgame_order_id" => $order->getData('appgame_order_id'),
            "product_name" => $order->getData('product_name'),
            "amount" => $order->getData('amount'),
            "status" => $order->getData('status'),
            //支付平台信息
            "payment_agent" => $order->getData('payment_agent'),
            "payment_method" => $order->getData('payment_method'),
            //支付金额信息
            "coupon_code" => $order->getOrderData('coupon_code'),
            "coupon_amount" => $order->getOrderData('coupon_amount'),
            "balance_amount" => $order->getOrderData('balance_amount'),
            "cash_to_pay" => $order->getOrderData('cash_to_pay'),
            "cash_paid" => $order->getData('cash_paid'),
            "currency" => $order->getOrderData('currency'),
            "amount_in_currency" => $order->getOrderData('amount_in_currency')
        );
        QueueHelper::Putintube('pay_event_create', $jobData);
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
