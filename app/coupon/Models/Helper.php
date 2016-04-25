<?php

namespace Coupon\Models;

use Common\Library\HelperAbstract;

class Helper extends HelperAbstract
{

    public function updateExpiredCoupons()
    {
        $now = time();
        $activated = Coupon::STATUS_ACTIVATED;
        $new = Coupon::STATUS_ACTIVATED;
        /** @var Coupon $couponList */
        $couponList = Coupon::find(" expire <=  {$now} and ( status = '{$activated}' or status = '{$new}' ) ");
        foreach($couponList as $coupon)
        {
            /** @var Coupon $coupon */
            $coupon->setData('status' , Coupon::STATUS_EXPIRED);
            $coupon->save();
        }
    }
}
