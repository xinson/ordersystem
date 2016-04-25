<?php
namespace Common\Library;

use Phalcon\Cache\Frontend\Data;
use Phalcon\Cache\Backend\Libmemcached as Memcached;
use Phalcon\Cache\Backend\Redis;
use Phalcon\Cache\Backend\File;
use Phalcon\Cache\Backend\Apc;

class CacheHelper
{
    private static $instances = null;
    private $config;
    private $frontendData;
    private $adapter;

    public function __construct()
    {
        $this->config = ConfigHelper::get('cache')->toArray();
        $this->frontendData = new Data();
        $this->adapter = $this->getAdapter();
    }

    public function getAdapter()
    {
        $config = $this->config;
        $fileAdapterConfig = fnGet($config,'file');
        unset($config['file']);
        foreach ($config as $key => $value) {
            if (fnGet($value,'enable') == true && class_exists($className = ucfirst(strtolower($key)))) {
                unset($value['enable']);
                return new $className($this->frontendData, $value);
            }
        }
        //使用默认的file
        if (!is_dir($cacheDir = fnGet($fileAdapterConfig,'cacheDir'))) {
            mkdir($cacheDir, 0777, true);
        }
        return new File($this->frontendData, $fileAdapterConfig);
    }

    public static function getInstance()
    {
        if (is_null(self::$instances)) {
            self::$instances = new static();
        }
        return self::$instances;
    }

    public static function get($key)
    {
        /* @var $instance $this */
        $instance =  static::getInstance();
        $value = $instance->adapter->get($key);
        return $value ? unserialize($value) : null;
    }

    public static function set($key,$value,$expire = 3600)
    {
        /* @var $instance $this */
        $instance =  static::getInstance();
        return $instance->adapter->save($key, serialize($value), $expire);
    }

}
