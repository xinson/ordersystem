<?php
/**
 * 本文件非配置文件
 * 请到 app/common/config 作配置
 */
use Phalcon\Config;
!defined('APP_PATH') && define('APP_PATH', dirname(__DIR__));

$defaultPath = APP_PATH .'/app/common/config/*.php';
$defaultSetting = array();
$defaultFiles = glob($defaultPath);
if ($defaultFiles != false && !empty($defaultFiles)) foreach ( $defaultFiles as $defaultFile ) {
    $defaultFilename = pathinfo($defaultFile,PATHINFO_FILENAME);
    if (is_file($defaultFile)) {
        $loadedDefaultFile = include $defaultFile;
        $defaultSetting[$defaultFilename] = is_array($loadedDefaultFile) ? $loadedDefaultFile : array();
    }
}

$productionPath = APP_PATH .'/app/common/config/production/*.php';
$productionSetting = array();
$productionFiles = glob($productionPath);
if ($productionFiles != false && !empty($productionFiles)) foreach ( $productionFiles as $productionFile ) {
    $productionFilename = pathinfo($productionFile,PATHINFO_FILENAME);
    if (is_file($productionFile)) {
        $loadedProductionFile = include $productionFile;
        $productionSetting[$productionFilename] = is_array($loadedProductionFile) ? $loadedProductionFile : array();
    }
}

$defaultConfig = new Config($defaultSetting);
$productionConfig = new Config($productionSetting);
return $defaultConfig->merge($productionConfig);
