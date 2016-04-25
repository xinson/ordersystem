<?php
/**
 * 模块注册
 */

return array(
    'common' => array(
        'className' => 'Common\Module',
        'path' => APP_PATH . '/app/common/module.php',
    ),
    'api' => array(
        'className' => 'Api\Module',
        'path' => APP_PATH . '/app/api/module.php',
    ),
    'coupon' => array(
        'className' => 'Coupon\Module',
        'path' => APP_PATH . '/app/coupon/module.php',
    ),
    'pay' => array(
        'className' => 'Pay\Module',
        'path' => APP_PATH . '/app/pay/module.php',
    ),
    'test' => array(
        'className' => 'Test\Module',
        'path' => APP_PATH . '/app/test/module.php',
    )
);
