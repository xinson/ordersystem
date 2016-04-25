<?php
namespace Common\Library;

use Phalcon\Di;

class EventHelper
{
    private static $instance = null;
    private $events = array();

    public function __construct($events)
    {
        $this->events = $events;
    }

    public static function getEventsList()
    {
        return static::getInstance()->events;
    }

    /**
     * @return null | $this
     */
    public static function getInstance()
    {
        if (static::$instance == null) {
            $eventsPath = APP_PATH . '/app/events.php';
            if (file_exists($eventsPath)) {
                $events = include APP_PATH . '/app/events.php';
                if (!is_array($events)) {
                    $events = array();
                }
            } else {
                $events = array();
            }
            static::$instance = new static($events);
        }
        return static::$instance;
    }

    public static function listen($tag, $params = null)
    {
        $instance = static::getInstance();
        if (isset($instance->events[$tag]) && class_exists($instance->events[$tag])) {
            call_user_func(array($instance->events[$tag],'run'),$params);
        }
    }

}
