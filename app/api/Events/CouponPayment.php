<?php

namespace Api\Events;


use Common\Library\EventData, Pay\Models\Order, Exception, Common\Library\LogHelper;
use Coupon\Exception\CouponOrderException;

class CouponPayment
{

    public static function after_process_payment(EventData $event)
    {
        $order = $event->getData('result/order');
        if ($order instanceof Order) {
            if ($coupon = $order->getCoupon()) {
                $coupon->setData('order_id', $order->getId());
                $coupon->save();
            }
            /** @var \Common\Models\User\BalanceHistory $balance_history */
            if ($balance_history = $order->getBalanceHistory()) {
                $balance_history->setData('order_id', $order->getId());
                $balance_history->setData('client_id', $order->getData('client_id'));
                $balance_history->save();
            }
        }
    }

    public static function order_preparation(EventData $event)
    {
        $order = $event->getData('order');
        if ($order instanceof Order && $order->getCouponCode()) {
            try {
                if ($coupon = $order->getCoupon()) {
                    $coupon->redeem($order);
                } else {
                    throw new CouponOrderException(__('抵用券无效'), CouponOrderException::ERROR_CODE_INVALID_COUPON);
                }
            } catch (Exception $e) {
                if ($event->getData('suppress_coupon_exception')) {
                    LogHelper::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                } else {
                    throw $e;
                }
            }
        }
    }

    public static function order_cancel(EventData $event)
    {
        /* @var Order $order */
        $order = $event->getData('order');
        if ($order instanceof Order) {
            if ($coupon = $order->getCoupon()) {
                $coupon->refund();
                $coupon->save();
            }
            if ($balanceAmount = $order->getOrderData('balance_amount')) {
                /** @var \Common\Models\User $user*/
                $user = $order->getUser();
                $user->getBalance()->plus($balanceAmount, '订单#' . $order->getData('appgame_order_id') . ' 失败,返还余额',
                    $order->getData('appgame_order_id'), $order->getId(), $order->getData('client_id'));
            }
        }
    }

    public static function order_fail(EventData $event)
    {
        self::order_cancel($event);
    }
}
