<?php
/**
 * 抵用券错误模块.
 * User: dgw
 * Date: 15-5-12
 */

namespace Coupon\Exception;

use RuntimeException;

class CouponException extends RuntimeException {
    const ERROR_CODE_COUPON_EXISTS = 4001;
    const ERROR_CODE_NEWBIE_FAILD = 4002;
    const ERROR_CODE_NEWBIE_NOT_EXISTS = 4003;
    const ERROR_CODE_NEWBIE_EXPIRED = 4004;
}
