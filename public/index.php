<?php

define('APP_PATH', dirname(__DIR__));
if (empty($_FILES) && !empty($_SERVER['USE_SERVICE'])) {
    require APP_PATH . '/app/client.php';
}

error_reporting(E_ALL | E_STRICT);


/**
 * Read function
 */
include APP_PATH . '/app/common/function/function.php';


/**
 * Read the configuration
 */
$config = include APP_PATH . '/app/config.php';

/**
 * Read services
 */
include APP_PATH . '/app/services.php';
set_exception_handler('exceptionHandler');
set_error_handler('errorHandler');

/**
 * Handle the request
 */
$application = new \Phalcon\Mvc\Application($di);
// 注册模块
$modules = include APP_PATH . '/app/modules.php';
$application->registerModules($modules);

/* @var \Phalcon\Mvc\Router $router */
$router = $di->getShared('router');
$router->handle();
if ($router->getMatchedRoute()) {
    if (Config::get('application.debug') && substr(fnGet($_SERVER, 'REQUEST_URI'), 0, 6) == '/test/') {
        $testModule = new Test\Module();
        $testModule->registerServices($di);
    }
    $controllerClass = ucfirst($router->getModuleName()) . '\\Controllers\\' . $router->getControllerName() . 'Controller';
    $controller = new $controllerClass;
    method_exists($controller, 'initialize') and $controller->initialize();
    method_exists($controller, $method = $router->getActionName()) or throw404Exception();
    $controller->{$method}();
} else {
    throw404Exception();
}
