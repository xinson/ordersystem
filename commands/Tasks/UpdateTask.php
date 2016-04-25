<?php
namespace Commands\Tasks;

use Commands\BaseTask;
use Exception, PDOException;

class UpdateTask extends BaseTask
{

    public $modules;
    protected $_initialized;
    protected $_logFile;
    protected $_moduleVersions = null;
    protected $_needToFlushTableCache = false;

    public function mainAction()
    {
        $module = isset($params[0]) ? $params[0] : null;
        $this->getModules();
        if ($this->modules) {
            foreach ($this->modules as $name => $path) {
                if ($module == null || strtolower($module) == strtolower($name)) {
                    $this->_updateModule(ucfirst(strtolower($name)));
                }
            }
        }
        echo "\n Update complete! \n\n";
    }

    protected function getModules()
    {
        return $this->modules = include APP_PATH . '/app/modules.php';
    }

    protected function _updateModule($module)
    {
        $workingVersion = $this->_getModuleVersion($module);
        if (is_dir($dir = APP_PATH. '/app/' . $module . '/update/')) {
            foreach (scandir($dir) as $entry) {
                if (substr($entry, 0, 1) != '.' &&
                    substr($entry, -4) == '.php' &&
                    ($fileVersion = substr($entry, 0, -4)) &&
                    version_compare($fileVersion, $workingVersion, '>') &&
                    is_file($file = realpath($dir . $entry))
                ) {
                    $this->_runUpdateFile($file)
                        ->_updateModuleVersion($module, $fileVersion);
                }
            }
        }
        return $this;
    }

    protected function _runUpdateFile($file)
    {
        $this->_log(sprintf('Start process: "%s"', $file));
        $db = $this->getDB();
        $db->begin();
        try {
            include $file;
            $db->commit();
            $this->_needToFlushTableCache = true;
        } catch (\Exception $e) {
            $db->rollback();
            $this->_log($e->getCode() . ' ' . $e->getMessage(), true);
            $this->_log($e->getTraceAsString(), true);
            throw $e;
        }
        $this->_log(sprintf('End process: "%s"', $file));
        return $this;
    }

    protected function _updateModuleVersion($module, $version)
    {
        $module = ucfirst(strtolower($module));
        $db = $this->getDB();
        $table = 'modules';
        $options = " module = '$module' ";
        try{
            $this->_getModuleVersion($module) ?
                $db->update($table, array('version'), array($version), $options) :
                (($this->_moduleVersions[$module] = array('version'=> $version )) and $db->insert($table, array('module','version'), array($module,$version)));
        } catch (Exception $e) {
            print_r($e);
            exit();
        }
    }

    protected function _getModuleVersion($module = null)
    {
        if ($this->_moduleVersions === null) {
            $this->_moduleVersions = array();
            $tableName = 'modules';
            $db = $this->getDB();
            try {
                $data = $db->fetchAll("SELECT * FROM {$tableName}");
                foreach ($data as $row) {
                    $this->_moduleVersions[fnGet($row, 'module')] = $row;
                }
            } catch (PDOException $e) {
                /* Base table or view not found */
                $db->execute("
                    CREATE TABLE {$tableName} (
                        `module` varchar(255) NOT NULL,
                        `version` varchar(255) NOT NULL,
                        PRIMARY KEY (`module`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    "
                );
            }
        }
        return $module ? fnGet($this->_moduleVersions, $module . '/version') : $this->_moduleVersions;
    }

}
