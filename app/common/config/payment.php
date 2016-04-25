<?php
return array(
    'payment_agents' => array(
        'balance_pay' => array(
            'description' => '余额支付',
            'class' => 'Pay\Method\BalancePay',
            'methods' => array(
                'balance_pay' => array(
                    'description' => '余额支付',
                ),
            ),
        ),
        'alipay' => array(
            'description' => '支付宝支付',
            'class' => 'Pay\Method\Alipay',
            'methods' => array(
                'mobile_alipay' => array(
                    'description' => '移动支付',
                ),
                'mobile_web_alipay' => array(
                    'description' => '手机网站支付',
                )
            )
        ),
    ),
    'alipay' => array(
        'partner' => '',  //合作身份者id，以2088开头的16位纯数字
        'private_key_path' => '', //商户的私钥（后缀是.pen）文件相对路径
        'ali_public_key_path' => '', //支付宝公钥（后缀是.pen）文件相对路径
        'sign_type' => '',   //签名方式 不需修改
        'input_charset' => '',  // //字符编码格式 目前支持 gbk 或 utf-8
        'cacert' => '',  //ca证书路径地址，用于curl中ssl校验 请保证cacert.pem文件在当前文件夹目录中
        'transport' => '', //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        'notify_url' => 'api/alipay/notifyAction',  //异步通知地址
        'return_url' => 'api/alipay/returnAction',  //页面跳转 同步通知 可空
        'payment_type' => 1  //支付类型 仅支持商品购买(1)
    ),
    'payment' => array(
        'callback_max_retry_count' => 9,
        'callback_retry_interval' => array(
            1 => 60,
            2 => 120,
            3 => 600,
            4 => 1800,
            5 => 3600,
            6 => 7200,
            7 => 14400,
            8 => 43200,
            9 => 86400,
        ),
    ),
    'order_statuses' => array(
        'pending' => '待确认',
        'processing' => '处理中',
        'complete' => '成功',
        'failed' => '失败',
    ),
);
