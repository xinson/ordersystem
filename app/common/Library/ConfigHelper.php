<?php
namespace Common\Library;

use Phalcon\DI;

class ConfigHelper
{
    /**
     * @param $key
     * @param null $defaultValue
     * @return \Phalcon\Config | mixed | null
     */
    public static function get($key, $defaultValue = null)
    {
        /* @var $config \Phalcon\Config */
        $config = DI::getDefault()->getShared('config');
        return fnGet($config, $key, null, '.');
    }
}
