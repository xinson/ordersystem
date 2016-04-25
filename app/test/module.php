<?php
namespace Test;

use Common\Library\ConfigHelper;
use Phalcon\Mvc\Dispatcher;
use Phalcon\DiInterface;
use Phalcon\Config;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;

class Module
{

    public function registerAutoloaders(DiInterface $di)
    {
    }

    public function registerServices(DiInterface $di)
    {
        $moduleConfigDir = __DIR__. '/config';
        if (is_dir($moduleConfigDir)) {

            $configFiles = $moduleConfigDir. '/*.php';
            $moduleSetting = array();
            $moduleConfigFiles = glob($configFiles);
            if ($moduleConfigFiles != false && !empty($moduleConfigFiles)) foreach ( $moduleConfigFiles as $moduleConfigFile ) {
                $moduleConfigFilename = pathinfo($moduleConfigFile,PATHINFO_FILENAME);
                if (is_file($moduleConfigFile)) {
                    $loadedModuleConfigFile = include $moduleConfigFile;
                    $moduleSetting[$moduleConfigFilename] = is_array($loadedModuleConfigFile) ? $loadedModuleConfigFile : array();
                }
            }

            $configProductionFiles = $moduleConfigDir. '/production/*.php';
            $moduleProductionSetting = array();
            $moduleProductionConfigFiles = glob($configProductionFiles);
            if ($moduleProductionConfigFiles != false && !empty($moduleProductionConfigFiles)){
                foreach ( $moduleProductionConfigFiles as $moduleProductionConfigFile ) {
                    $moduleProductionConfigFilename = pathinfo($moduleProductionConfigFile,PATHINFO_FILENAME);
                    if (is_file($moduleProductionConfigFile)) {
                        $loadedProductionModuleConfigFile = include $moduleProductionConfigFile;
                        $moduleProductionSetting[$moduleProductionConfigFilename] = is_array($loadedProductionModuleConfigFile)
                            ? $loadedProductionModuleConfigFile : array();
                    }
                }
            }

            if (!empty($moduleSetting)) {
                /* @var $config Config 读取自身的配置 */
                $config = $di->getShared('config');
                $config->merge((new Config($moduleSetting))->merge(new Config($moduleProductionSetting)));
                $di->setShared('config', $config);
            }
        }

        /**
         * 当开启 debug 模式的时候,才能路由到本模块
         */
        if (ConfigHelper::get('application.debug')) {
            /* @var $dispatcher \Phalcon\Mvc\Dispatcher */
            $dispatcher = $di->get('dispatcher');
            $dispatcher->setDefaultNamespace('Test\Controllers');
            $dispatcher->setActionSuffix('');
            $di->set('dispatcher', $dispatcher);
        }
    }

}
