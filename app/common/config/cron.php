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
        'update_processing_yeepay_order_status' => array(
            'expression' => '* * * * *',
            'class' => 'Pay\Method\Yeepay\HelperModel',
            'method' => 'updateProcessingOrderStatus',
        ),
        'retry_order_callback' => array(
            'expression' => '* * * * *',
            'class' => 'Pay\Models\Helper',
            'method' => 'retryOrderCallback',
        ),
    )
);
