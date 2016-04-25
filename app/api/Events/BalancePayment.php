<?php

namespace Api\Events;

use Common\Library\EventData;
use Pay\Exception\OrderException;
use Pay\Models\Order;

/**
 * Class BalancePayment
 * @package Pay\Behavior
 */
class BalancePayment
{

    /**
     * 附加使用余额
     * @param EventData $event
     */
    public static function use_balance(EventData $event)
    {
        /** @var Order $order */
        $order = $event->getData('order');
        /** @var \Common\Models\User $user */
        $user = $order->getUser();
        $balanceAmount = $user->getBalance()->getData('amount');
        if ((int)$balanceAmount === 0) {
            throw new OrderException(__('用户余额不足'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
        if ($order->getData('cash_to_pay') >= $balanceAmount) {
            $newAmount = $order->getData('cash_to_pay') - $balanceAmount;
            $order->setData('cash_to_pay', $newAmount)
                ->setOrderData('balance_amount', $balanceAmount);
            $user->getBalance()->sub($balanceAmount, '订单#' . $order->getData('appgame_order_id') . ' 附加使用余额',
                $order->getData('appgame_order_id'));
        } else {
            throw new OrderException(__('订单金额小于用户余额,不能附加使用余额'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
    }


    /**
     * 使用余额支付
     * @param EventData $event
     */
    public static function balance_pay(EventData $event)
    {
        /** @var Order $order */
        $order = $event->getData('order');
        /** @var \Common\Models\User $user */
        $user = $order->getUser();
        $balanceAmount = $user->getBalance()->getAmount();
        $cashToPay = $order->getData('cash_to_pay');
        if ($balanceAmount < $cashToPay) {
            throw new OrderException(__('用户余额不足'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
        $order->setData('cash_to_pay', 0)->setOrderData('balance_amount', $cashToPay);
        $user->getBalance()->sub($cashToPay, '订单#' . $order->getData('appgame_order_id') . '使用余额支付',
            $order->getData('appgame_order_id'));
    }


}
