<?php
return array(
    'payment_agents' => array(
        'balance_pay' => array(
            'description' => '余额支付',
            'class' => 'Test\Method\TestBalancePay',
            'methods' => array(
                'balance_pay' => array(
                    'description' => '余额支付',
                ),
            ),
        ),
        'alipay' => array(
            'description' => '支付宝支付',
            'class' => 'Test\Method\TestAlipay',
            'methods' => array(
                'mobile_alipay' => array(
                    'description' => '移动支付',
                ),
                'mobile_web_alipay' => array(
                    'description' => '手机网站支付',
                )
            )
        ),
    )
);
