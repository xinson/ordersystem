<?php
namespace Coupon\Models;

use Common\Models\Model;

/**
 * Class CouponClient
 * @package CouponClient\Model
 *
 */
class CouponClient extends Model
{

    public $client_id;

    public function initialize()
    {
        $this->setSource("coupon_client");
    }
}
