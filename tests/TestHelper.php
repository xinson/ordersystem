<?php

use Phalcon\DI;
use Phalcon\DI\FactoryDefault;

ini_set('display_errors',1);
error_reporting(E_ALL);

//定义环境变量
define('UNIT_TESTING', true );

define('APP_PATH', dirname(__DIR__));
define('PATH_LIBRARY', APP_PATH . '/app/library/');
define('PATH_SERVICES', APP_PATH . '/app/services/');
define('PATH_RESOURCES', APP_PATH . '/app/resources/');

ini_set('include_path', APP_PATH . PATH_SEPARATOR . get_include_path());

// Required for phalcon/incubator
include APP_PATH . "/vendor/autoload.php";
// 加载 function
include APP_PATH . "/app/common/function/function.php";

$loader = new \Phalcon\Loader();
$loaderArray = include APP_PATH . "/vendor/composer/autoload_classmap.php";
$loader->registerClasses($loaderArray);
$loader->registerNamespaces(array(
    'Test' => APP_PATH . '/app/test',
    'Tests' => APP_PATH . '/tests'
), true);
$loader->register();
