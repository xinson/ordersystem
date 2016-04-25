<?php

return array(
    'redis' => array(
        'enable' => false,
        'host' => '',
        'port' => '',
        'auth' => '',
        'persistent' => ''
    ),
    'memcached' => array(
        'enable' => false,
        "servers" => array(
            array(
                'host' => '',
                'port' => '',
                'weight' => ''
            )
        )
    ),
    'file' => array(
        'cacheDir' => APP_PATH . '/storage/cache/'
    ),

);
