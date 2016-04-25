<?php

namespace Pay\Method;

use Exception;
use Common\Library\EventData;
use Pay\Models\Order;
use Pay\Exception\OrderException;
use Common\Library\LogHelper as log;
use Common\Library\EventHelper as hook;

class BalancePay extends PaymentMethodAbstract
{

    /**
     * 抵用卷支付/余额支付
     * @param $data
     * @return array
     */
    protected function _processBalancePay($data)
    {
        try {
            $cashToPay = fnGet($data, 'cash_to_pay', fnGet($data, 'amount'));
            $user = fnGet($data, 'user');
            $client = fnGet($data, 'client');
            if (!$user || !$client) {
                throw new Exception(__('用户或客户端无效'));
            }
            $data['cash_to_pay'] = $cashToPay;
            $data['order_prefix'] = 'BP';
            $data['userip'] = get_client_ip();
            $data['callback_url'] = $data['developerurl'];
            $data['currency'] = fnGet($data, 'currency','CNY');
            $data['amount_in_currency'] = fnGet($data, 'amount_in_currency',fnGet($data, 'amount'));
            unset($data['developerurl']);
            $order = Order::prepareOrder($data);
            $order->complete();
            $order->save();
            $order->callback();
            $result = array(
                array(
                    'trade_id' => $order->getData('trade_id'),
                    'appgame_order_id' => $order->getData('appgame_order_id'),
                    'amount' => $order->getData('amount'),
                    'status' => $order->getData('status'),
                ),
                'order' => $order
            );
            // 队列 余额支付请求
            $eventData = new EventData(array(
                'model' => 'BalanceBeanstalkd',
                'method' => 'balancePay',
                'order' => $order,
            ));
            Hook::listen('process_beanstalk', $eventData);
        } catch (OrderException $e) {
            $result = static::prepareErrorResponse($e);
        } catch (Exception $e) {
            Log::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = static::prepareErrorResponse($e, __('服务器内部错误'), 500, 500);
        }
        return $result;
    }

    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $data = fnGet($params, 'data');
        if (method_exists($this, $method)) {
            return $this->$method($data);
        }
        return false;
    }
}
