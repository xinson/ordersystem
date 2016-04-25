<?php

/**
 * 注册 events
 */

return [
    'before_ajax_return' => 'Api\Events\ApiRequestLog',
    'after_sent_cpdata' => 'Api\Events\PaySentHistoryLog'
];
