<?php

namespace Coupon\Exception;

use Pay\Exception\OrderException;

class CouponOrderException extends OrderException {
	const ERROR_CODE_INVALID_COUPON = 4101;
}
