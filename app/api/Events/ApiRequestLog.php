<?php

namespace Api\Events;

use Common\Models\ReceivedHistory;
use Common\Library\InputHelper;

class ApiRequestLog
{

    public static function run($response)
    {
        $request = InputHelper::get();
        $createdAt = fnGet($_SERVER, 'REQUEST_TIME') ?: time();
        $responseAt = time();
        $history = new ReceivedHistory;
        $url = fnGet($request, '_url');
        unset($request['_url']);
        $history->setData(array(
            'command' => $url,
            'request_data' => json_encode($request),
            'response_data' => json_encode($response),
            'created_at' => $createdAt,
            'response_at' => $responseAt,
        ));
        $history->save();
    }
}
