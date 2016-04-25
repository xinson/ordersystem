<?php
/**
 * 本文件非配置文件, 修改请到 config 文件夹下
 * 会自动合并原程序的配置
 */

use Phalcon\Config;
!defined('APP_PATH') && define('APP_PATH', dirname(__DIR__));

$testPath = APP_PATH .'/tests/config/*.php';
$testSetting = array();
$testFiles = glob($testPath);
if ($testFiles != false && !empty($testFiles)) foreach ( $testFiles as $testFile ) {
    $testFilename = pathinfo($testFile,PATHINFO_FILENAME);
    if (is_file($testFile)) {
        $loadedTestFile = include $testFile;
        $testSetting[$testFilename] = is_array($loadedTestFile) ? $loadedTestFile : array();
    }
}

$testProductionPath = APP_PATH .'/tests/config/production/*.php';
$testProductionSetting = array();
$testProductionFiles = glob($testProductionPath);
if ($testProductionFiles != false && !empty($testProductionFiles)) foreach ( $testProductionFiles as $testProductionFile ) {
    $testProductionFilename = pathinfo($testProductionFile,PATHINFO_FILENAME);
    if (is_file($testProductionFile)) {
        $loadedProductionTestFile = include $testProductionFile;
        $testProductionSetting[$testProductionFilename] = is_array($loadedProductionTestFile) ? $loadedProductionTestFile : array();
    }
}

$testConfig = new Config($testSetting);
$testProductionConfig = new Config($testProductionSetting);
$testConfig->merge($testProductionConfig);

$defaultPath = APP_PATH . '/app/config.php';
if (is_readable($defaultPath)) {
    $defaultConfig = include $defaultPath;
    return $defaultConfig->merge($testConfig);
}
return $testConfig;
