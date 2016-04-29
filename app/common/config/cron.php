<?php
return array(
    'CRON_ON' => false,
    'cron' => array(
        'process_untreated_queue' => array(
            'expression' => '* * * * *',
            'class' => 'Common\Library\QueueHelper',
            'method' => 'processUntreatedQueue'
        ),
		'update_expired_coupons' => array(
            'expression' => '*/5 * * * *',
            'class' => 'Coupon\Models\Helper',
            'method' => 'updateExpiredCoupons'
        ),
        'retry_order_callback' => array(
            'expression' => '* * * * *',
            'class' => 'Pay\Models\Helper',
            'method' => 'retryOrderCallback',
        ),
    )
);
