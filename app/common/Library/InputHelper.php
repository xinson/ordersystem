<?php
namespace Common\Library;

use Phalcon\Di;

class InputHelper
{
    private static $instances = null;
    /* @var $request \Phalcon\Http\Request */
    private $request;

    public function __construct()
    {
        $this->request = Di::getDefault()->get('request');
    }

    public static function getInstance()
    {
        if (is_null(self::$instances)) {
            self::$instances = new static();
        }
        return self::$instances;
    }

    public static function get($key = null, $default = null)
    {
        $input = static::getInstance()->request->get();
        if (is_null($key)) {
            return $input;
        }
        return fnGet($input,$key,$default);
    }

}
