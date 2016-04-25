<?php
namespace Common\Library;

abstract class HelperAbstract
{
    private static $_instances = array();

    /**
     * @param string|null $class
     *
     * @return static
     */
    public static function getInstance($class = null)
    {
        $class or $class = get_called_class();
        if (!isset(self::$_instances[$class])) {
            $helper = new $class;
            self::$_instances[$class] = $helper;
        }
        return self::$_instances[$class];
    }
}
