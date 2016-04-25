<?php
namespace Pay\Models;

use Pay\Exception\OrderException;
use Common\Library\HelperAbstract;

class Helper extends HelperAbstract
{

    /**
     * @param $tradeId
     * @param $clientId
     * @param null $userId
     * @return array | Order
     */
    public function initOrder($tradeId, $clientId, $userId = null)
    {
        $order = new Order();
        /** @var Order $hasOrder */
        $hasOrder = $order->getByTradeId($tradeId, $clientId);
        if (isset($hasOrder->id) && (!$userId || $hasOrder->user_id == $userId)) {
            return $hasOrder;
        }
        throw new OrderException(__('订单不存在'), OrderException::ERROR_CODE_BAD_PARAMETERS);
    }

    /**
     * @param $appgameOrderId
     * @return \Phalcon\Mvc\Model | Order
     */
    public function initOrderByAppgameOrderId($appgameOrderId)
    {
        $order = (new Order)->getByAppgameOrderId($appgameOrderId);
        if (!isset($order->id)) {
            throw new OrderException(__('订单不存在'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
        return $order;
    }

    public function retryOrderCallback()
    {
        $now = time();
        $orders = Order::query()->columns(array('id'))
                ->where('callback_status LIKE :callback_status:')
                ->andWhere('callback_next_retry <= :callback_next_retry: ')
                ->bind(array(
                        'callback_status'=> Order::CALLBACK_STATUS_TRIED . '%',
                        'callback_next_retry' => $now
                ))
                ->limit(10)
                ->execute()
                ->toArray();
        foreach ($orders as $value) {
            $id = fnGet($value,'id');
            $order = Order::findFirst($id);
            /** @var Order $order */
            $order->callback(true);
            $order->save();
            unset($order);
        }
    }
}
