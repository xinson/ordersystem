<?php
return array(
    'coupon_agents' => array(
        'newbie' => array(
            'name'=>'新手抵用券',
            'description' => '新手抵用卷',
            'type'=>'newbie',
            'amount'=>6, //单位:元
            'activation_wait' => 3600, //激活等待时间,单位:秒
            'expire' => 2592000,  //过期时间,单位:秒. 2592000 = 30天
            'class' => 'Coupon\Type\Newbie',
            'methods' => array(
                'newbie_create' => array(
                    'description' => '创建新手抵用券',
                ),
                'newbie_activate'=>array(
                    'description' => '激活新手抵用券',
                ),
                'coupon_list'=>array(
                    'description' => '抵用券列表',
                ),
            ),
        ),
        'activity' => array(
            'class' => 'Coupon\Type\Activity',
            'activation_wait' => 3600, //激活等待时间,单位:秒
            'coupon_name' => '活动类抵用卷',
            'type' => 'activity',
            'coupon_description' => '活动类抵用卷',
            'methods' => array(
            )
        ),
        'trigger' => array(
            'class' => 'Coupon\Type\Trigger',
            'coupon_name' => '抵用卷',
            'coupon_description' => '抵用卷'
        ),
    ),
);
