<?php

return [

    'default_module' => 'api',
    'routes' => [
        /* Common */
        '/' => 'Index@index',

        /* Balance */
        '/api/balance/pay' => 'Balance@pay',

        /* Alipay */
        '/api/alipay/mobilePayRequest' => 'Alipay@mobilePayRequest',
        '/api/Alipay/mobilePayRequest' => 'Alipay@mobilePayRequest',
        '/api/alipay/mobileWebPayRequest' => 'Alipay@mobileWebPayRequest',
        '/api/Alipay/mobileWebPayRequest' => 'Alipay@mobileWebPayRequest',
        '/api/alipay/notifyAction' => 'Alipay@notifyAction',
        '/api/alipay/returnAction' => 'Alipay@returnAction',

        /* coupon */
        '/api/coupon/couponList' => 'Coupon@couponList',
        '/api/coupon/newbieCreate' => 'Coupon@newbieCreate',
        '/api/coupon/newbieActivate' => 'Coupon@newbieActivate',

        /* Setting */
        '/api/settings' => 'Settings@index',
        '/api/settings/password' => 'Settings@password',
        '/api/settings/changePassword' => 'Settings@changePassword',
        '/api/settings/forgetPassword' => 'Settings@forgetPassword',

        /* order */
        '/api/order/payedClientList' => 'Order@payedClientList',
        '/api/order/orderList' => 'Order@orderList',
        '/api/order/cancel' => 'Order@cancel',
    ],

];
