<?php

namespace Api\Events;

use Common\Models\SentHistory;

class PaySentHistoryLog
{

    public static function run($params)
    {
        $requestData = fnGet($params, 'requestData');
        $responseData = fnGet($params, 'responseData');
        $orderId = fnGet($params, 'orderId');
        $createdAt = fnGet($_SERVER, 'REQUEST_TIME') ?: time();
        $updatedAt = time();
        /** @var  SentHistory $history */
        $history = new SentHistory;
        /** @var  SentHistory $hasHistory */
        $hasHistory = $history->getByOrderId($orderId);
        $request = $response = array();
        if(isset($hasHistory->id)){
            $history = $hasHistory;
            $request = json_decode($hasHistory->getData('request_data'),true);
            $response = json_decode($hasHistory->getData('response_data'),true);
        }
        $time = explode(' ', microtime());
        $request[$time[1] . substr($time[0], 1)] = $requestData;
        $response[$time[1] . substr($time[0], 1)] = $responseData;
        $history->setData('order_id', $orderId);
        $history->setData('request_data', json_encode($request));
        $history->setData('response_data', json_encode($response));
        !empty($history->getData('created_at')) ?: $history->setData('created_at', $createdAt);
        $history->setData('updated_at', $updatedAt);
        $history->save();
    }
}
