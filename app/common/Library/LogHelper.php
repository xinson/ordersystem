<?php
namespace Common\Library;

use Config, Exception;
use Common\Exception\NotFoundException;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line;

class LogHelper extends Logger
{
    /**
     * @var FileAdapter[]|\Phalcon\Logger\Adapter[]
     */
    protected static $instance = array();

    /**
     * @var
     */
    protected static $formatter;

    public static function getInstance($adapter = 'file')
    {
        if (!isset(static::$instance[$adapter])) {
            switch ($adapter) {
                case 'file':
                default:
                    $filePath = APP_PATH . '/storage/logs/';
                    if (!is_dir($filePath)) {
                        mkdir($filePath, 0777, true);
                    }
                    $fileName = Config::get('application.log.file');
                    $logger = new FileAdapter($filePath . $fileName);
            }
            $formatter = $logger->getFormatter();
            $formatter instanceof Line and $formatter->setDateFormat('Y-m-d H:i:s');
            static::$instance[$adapter] = $logger;
        }
        return static::$instance[$adapter];
    }

    public static function logException(Exception $e)
    {
        static::write(/*'[' . gethostname() . '] ' .*/
            '[' . fnGet($_SERVER, 'REQUEST_METHOD') . ' ' . fnGet($_SERVER, 'REQUEST_URI') . ']' .
            ($e instanceof NotFoundException ? ' ' . get_class($e) : "\n" . $e->__toString())
        );
    }

    public static function write($message, $level = self::ERROR, $type = '', $destination = '')
    {
        is_array($message) and $message = var_export($message, true);
        $logger = static::getInstance();
        return $logger->log($level, $message);
    }
}
