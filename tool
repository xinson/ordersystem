#!/usr/bin/env php
<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI,
    Phalcon\Cli\Console as ConsoleApp;

// 使用CLI工厂类作为默认的服务容器
$di = new CliDI();

// 定义应用目录路径
defined('APP_PATH') || define('APP_PATH', realpath(dirname(__FILE__)));

/**
 * 注册类自动加载器
 */
include APP_PATH .'/commands/Loader.php';

// 加载配置文件（如果存在）
if (is_readable(APP_PATH . '/app/config.php')) {
    $config = include APP_PATH . '/app/config.php';
    $di->setShared('config', $config);
}

// 加载自定义函数
if (is_readable(APP_PATH . '/app/common/function/function.php')) {
    include APP_PATH . '/app/common/function/function.php';
}

// 加载注入
include APP_PATH .'/commands/Services.php';

//设置调度
$di->set('dispatcher',function(){
    $dispatcher = new \Phalcon\Cli\Dispatcher();
    $dispatcher->setDefaultNamespace('Commands\Tasks');
    return $dispatcher;
});

// 创建console应用
$console = new ConsoleApp();
$console->setDI($di);
//$di->setShared('app', $console);

/**
 * 处理console应用参数
 */
$arguments = array();
foreach ($argv as $k => $arg) {
    if ($k == 1) {
        $arguments['task'] = $arg;
    } elseif ($k == 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

// 定义全局的参数， 设定当前任务及动作
define('CURRENT_TASK',   (isset($argv[1]) ? $argv[1] : null));
define('CURRENT_ACTION', (isset($argv[2]) ? $argv[2] : null));

try {
    // 处理参数
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    echo $e->getMessage()."\n\n";
    exit(255);
}
