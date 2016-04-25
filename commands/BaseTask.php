<?php
namespace Commands;

use Phalcon\Cli\Task;
use Exception, PDOException;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Common\Library\ConfigHelper;

class BaseTask extends Task
{
    protected $_db = null;

    protected function getDB()
    {
        if ($this->_db == null) {
            try{
                $this->_db = $this->getDI()->get('db');
            } catch(Exception $e) {
                print_r($e);
                exit();
            }
        }
        return $this->_db;
    }

    protected function _log($message, $isError = false)
    {
        $filePath = APP_PATH . '/storage/logs/commands/';
        if (!is_dir($filePath)) mkdir($filePath);
        $filename = date('Y-m-d') . '.log';
        $logger = new FileAdapter($filePath . $filename);
        $level = $isError ? Logger::ERROR : Logger::INFO;
        $className = get_called_class();
        $logger->log("[[ {$className} ]]{$level}: {$message}", $level);
        return $this;
    }

    protected function getConfig($key, $default = null)
    {
        return ConfigHelper::get($key, $default);
    }

}
