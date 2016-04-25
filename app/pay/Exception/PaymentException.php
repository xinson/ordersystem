<?php

namespace Pay\Exception;

use RuntimeException;

class PaymentException extends RuntimeException
{
    const ERROR_CODE_PASSWORD_NOT_MATCH = 5001;
    const ERROR_CODE_PASSWORD_ERROR_TOO_MANY_TIME = 5002;
}
