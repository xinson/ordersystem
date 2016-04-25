<?php
/**
 * Services are globally registered in this file
 *
 * @var \Phalcon\Config $config
 */

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Loader;
use Phalcon\Events\Manager;
use Common\Library\LogHelper;
use Phalcon\Mvc\Dispatcher\Exception;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

/**
 * 注入配置
 */
$di->setShared('config', $config);

/**
 * 注册路由
 */
$routerConfig = require_once APP_PATH . "/app/router.php";
$di->setShared('router', function () use ($routerConfig, $config) {
    $router = new Router(false);
    $router->setDefaultModule($routerConfig['default_module']);
    $router->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);
    foreach ($routerConfig['routes'] as $url => $route) {
        $moduleController = explode('/', $route);
        $_temp = [];
        if (count($moduleController) > 1) {
            $_temp['module'] = current($moduleController);
            list($controller, $action) = explode('@', next($moduleController));
        } else {
            list($controller, $action) = explode('@', $route);
        }
        $_temp['controller'] = $controller;
        $_temp['action'] = $action;
        $router->add($url, $_temp);
        //如果为 debug , 自动添加 Test 模块路由
        if (\Common\Library\ConfigHelper::get('application.debug') && strpos($url, '/api/') !== false) {
            $_temp['module'] = 'test';
            $_temp['controller'] = 'Test' . $_temp['controller'];
            $router->add(str_replace('/api/', '/test/', $url), $_temp);
        }
    }
    return $router;
});

/**
 * 使用 composer classmap
 */

$loaderArray = include APP_PATH . "/vendor/composer/autoload_classmap.php";
$loader = new Loader;
$loader->registerClasses($loaderArray);
$loader->register();
$di->setShared('loader', $loader);

class_alias('Common\Library\ConfigHelper', 'Config');
class_alias('Common\Library\LogHelper', 'Log');
class_alias('Common\Library\EventData', 'EventData');
class_alias('Common\Library\HttpClient', 'HttpClient');
class_alias('Common\Library\Session', 'Session');
class_alias('Common\Models\User', 'User');
class_alias('Common\Models\Client', 'Client');

class_alias('Pay\Models\Order', 'Order');
class_alias('Pay\Models\OrderData', 'OrderData');

/**
 * The URL component is used to generate all kind of urls in the application
 */
/*
$di->setShared('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
});
*/

/**
 * Setting up the view component
 */
$di->setShared('view', function () use ($config) {
    $view = new View();
    $view->registerEngines(array(
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php',
    ));
    return $view;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () use ($config) {
    $dbConfig = $config->database->toArray();
    $adapter = $dbConfig['adapter'];
    unset($dbConfig['adapter']);
    $class = 'Common\\Phalcon\\Db\\Adapter\\Pdo\\' . $adapter;
    return new $class($dbConfig);
});

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
/*
$di->setShared('modelsMetadata', function () {
    return new MetaDataAdapter();
});
*/

/**
 * Register the session flash service with the Twitter Bootstrap classes
 */
/*
$di->set('flash', function () {
    return new Flash(array(
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning'
    ));
});
*/
/**
 * Start the session the first time some component request the session service
 */
/*
$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();

    return $session;
});
/*

/**
 * 设置调度, 通过调度来管理路由
 */
$di->set('dispatcher', function () use ($config) {
    // 创建一个事件管理
    $eventsManager = new Manager();
    $eventsManager->attach("dispatch:beforeException", function ($event, $dispatcher, $exception) use ($config) {
        /* @var $dispatcher \Phalcon\Mvc\Dispatcher */
        //页面404
        if ($exception instanceof Exception) {
            //log
            if (fnGet($config->get('log'), 'NOT_FOUND_LOG')) {
                LogHelper::write("404 Not Found : [ url : " . $_SERVER['REQUEST_URI'] .
                    "] [ dispatcher: " . $dispatcher->getModuleName() . " / " . $dispatcher->getControllerName() . " / " . $dispatcher->getActionName() . " ]");
            }
            send_http_status(404);
            echo json_encode(array(
                'status' => false,
                'message' => '404 Not Found',
            ));
            exit();
        }
    });
    $dispatcher = new Dispatcher();
    $dispatcher->setEventsManager($eventsManager);
    return $dispatcher;
});
