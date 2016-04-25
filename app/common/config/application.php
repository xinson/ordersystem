<?php

return array(
    'debug' => false,

    'HTTPS' => null, // 是否开启 HTTPS: true - 是; false - 否; null - 跟随访问
    'HTTP_CLIENT_SSL_VERIFY' => false, // HttpClient 是否检验SSL证书和域名，开发环境可设为false，生产环境请设为true
    'VAR_JSONP_HANDLER' => 'callback',
    'DEFAULT_JSONP_HANDLER' =>  'jsonpReturn', // 默认JSONP格式返回的处理方法

    'log' => array(
        'file' => 'application.log',
    ),
);
