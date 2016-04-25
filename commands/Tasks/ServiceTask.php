<?php
namespace Commands\Tasks;

use Common\Exception\AjaxReturnException;
use Common\Exception\NotFoundException;
use Common\Library\LogHelper;
use Commands\BaseTask;
use Phalcon\Di;
use Phalcon\Loader;
use swoole_server;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\View;
use Common\Phalcon\Dispatcher;
use Phalcon\Mvc\Application;
use Phalcon\Events\Manager;
use Phalcon\Mvc\Dispatcher\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Router;
use Phalcon\Http\ResponseInterface;
use Phalcon\Http\Response;

class ServiceTask extends BaseTask
{
    /**
     * @var swoole_server
     */
    protected $swoole;
    protected $swoolePort;
    protected $availablePorts = array(9510, 9511);
    protected $config;
    protected $debug = false;
    protected $runDir = '/tmp/pay/';
    protected $linuxServiceScript = '/etc/init.d/order_sysytem';
    protected $delaySave;
    protected $pid;
    protected $managerPid;
    protected $sockFile;
    /** @var  \Phalcon\Mvc\Application */
    protected $application;
    /** @var  \Phalcon\Di */
    protected $di;


    public function mainAction()
    {
        die("php tool Service [ start | stop | restart | reload | status ] \n");
    }


    private function init()
    {
        /** @var swoole_server */
        if (!class_exists('swoole_server', false)) {
            echo " Unable to use service! \n";
            echo " PHP extension swoole not installed! \n";
            die();
        }
        $this->config = $this->getConfig('service');
        $this->debug = $this->config->get('debug');
        substr($this->runDir, -1) == '/' or $this->runDir .= '/';
        is_dir($this->runDir) or mkdir($this->runDir, 0777, true);
    }


    public function startAction()
    {
        $this->init();
        $port = $this->choosePort();
        $this->sendCommand('status', $port, $error);
        if (!$error) {
            echo "Service already started \n";
            return;
        }
        define('SERVICE_MODE', true);
        //swoole 配置
        $this->swoole = $server = new swoole_server($this->sockFile, 0, SWOOLE_PROCESS, SWOOLE_UNIX_STREAM);
        $config = array_merge(array(
            // 固定模式分发策略
            'dispatch_mode' => 2,
            'log_file' => APP_PATH . '/storage/logs/service.log',
            // 启用固定包头协议 http://wiki.swoole.com/wiki/page/484.html
            'open_length_check' => true,
            'package_max_length' => 262144,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
        ), (array)$this->config);
        unset($config['session_driver'], $config['run_dir'], $config['linux_init_script'], $config['debug']);
        $server->set($config);
        //绑定监听
        $server->on('Start', array($this, 'onStart'));
        $server->on('ManagerStart', array($this, 'onManagerStart'));
        $server->on('WorkerStart', array($this, 'onWorkerStart'));
        $server->on('Receive', array($this, 'onReceive'));

        $this->setDiService();

        $this->application = new Application($this->di);
        $modules = include APP_PATH . "/app/modules.php";
        $this->application->registerModules($modules);
        $this->di->setDefault($this->di);
        $server->start();
    }

    public function statusAction($params = array())
    {
        $this->init();
        //加入参数 l , 循环输出连接数
        if (!empty($params) && strtolower(current($params)) == 'l') {
            while (true) {
                $response = $this->sendCommand('status-list', null, $error);
                if ($error) {
                    die("Service not started. \n");
                }
                echo $response;
                sleep(3);
            }
            exit();
        } else {
            $response = $this->sendCommand('status', null, $error);
        }
        if ($error) {
            die("Service not started. \n");
        }
        die($response);
    }

    public function restartAction()
    {
        echo "Stopping service...\n";
        $this->stopAction();
        usleep(5e5);
        echo "Starting service...\n";
        $this->startAction();
    }

    /**
     * Stop service
     * @param string $instance Specify "current" or "old". "current" by default.
     */
    public function stopAction($instance = 'current')
    {
        $this->init();
        //获取当前服务内容
        if ($serviceInfo = $this->getServiceInfo($instance)) {
            list($pid, $managerPid, $port) = array_values($serviceInfo);
            // Get serving connection numbers
            $connections = $this->sendCommand('connections', $port, $error);
            while (!$error && $connections > 0) {
                usleep(5e5);
                $connections = $this->sendCommand('connections', $port, $error);
            }
            // Send TERM signal to master process to stop service
            posix_kill($pid, SIGTERM);
            // Kill master process in Mac OS
            if (PHP_OS == 'Darwin') {
                sleep(1);
                posix_kill($pid, SIGKILL);
            }
        }
        echo "Service stopped. \n";
    }

    /**
     * reload Service
     */
    public function reloadAction()
    {
        $this->init();
        $currentServiceInfo = $this->getServiceInfo('current');
        $this->updateServiceInfo('shift');
        $this->choosePort(true);
        $port = $this->choosePort();
        $this->sendCommand('status', $port, $error);
        if ($error) {
            $this->startAction();
        }else{
            //处理 新端口没有关闭的情况
            $this->updateServiceInfo('current', array(
                'pid' => $currentServiceInfo['pid'],
                'manager_pid' => $currentServiceInfo['manager_pid'],
                'port' => $port,
            ));
        }

        $retry = 0;
        $this->sendCommand('status', $port, $error);
        while ($error && $retry < 20) {
            usleep(5e5);
            ++$retry;
            $this->sendCommand('status', $port, $error);
        }
        if ($error) {
            print_r('Service reload failed: ' . http_build_query($error)."\n");
            return;
        }
        echo("Stopping old service...\n");
        $this->stopAction('old');
        echo "Service reloaded.\n";
    }

    /**
     * install Service
     */
    public function installAction()
    {
        $this->init();
        $serviceName = basename($this->linuxServiceScript);
        $sourceFile = APP_PATH.'/order_sysytem';
        $scriptContent = strtr(file_get_contents($sourceFile), array(
            '__PHP_ARTISAN_PWD__' => APP_PATH,
            '__SERVICE_NAME__' => $serviceName,
            '__USER__' => $user = $this->config['user'],
            '__GROUP__' => $group = $this->config['group'],
        ));
        try {
            file_put_contents($this->linuxServiceScript, $scriptContent);
            system('chmod +x ' . $this->linuxServiceScript);
            exec('update-rc.d ' . $serviceName . ' defaults 90');
            chown($this->runDir, $user);
            chgrp($this->runDir, $group);
            echo 'Service installed.'."\n";
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
            echo 'Service not installed.'."\n";
        }
    }

    /**
     * uninstall Service
     */
    public function uninstallAction()
    {
        if(!is_file($this->linuxServiceScript)){
            echo 'Service not installed.'."\n";
            return;
        }
        try {
            $serviceName = basename($this->linuxServiceScript);
            exec('update-rc.d -f ' . $serviceName . ' remove');
            unlink($this->linuxServiceScript);
            echo 'Service uninstalled.'."\n";
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
            echo 'Service not uninstalled.'."\n";
        }
    }

    /**
     * Send command to service command handler
     *
     * @param string $command
     * @param integer $port
     * @param $error
     * @return string
     */
    public function sendCommand($command, $port = null, &$error = null)
    {
        $port or $port = $this->choosePort();
        $sockFile = $this->runDir . "command-{$port}.sock";
        //连接 $sockFile 获取进程状态
        if (!$socket = @stream_socket_client('unix://' . $sockFile, $errNo, $errStr, 5)) {
            $error = array('err' => $errNo, 'message' => $errStr);
            return "$errStr ($errNo)";

        } else {
            $error = false;
            fwrite($socket, $command, strlen($command));
            $response = fread($socket, 8192);
            fclose($socket);
            return $response;
        }
    }

    public function startCommandHandler()
    {
        $sockFile = $this->runDir . "command-{$this->swoolePort}.sock";
        file_exists($sockFile) and unlink($sockFile);
        ini_set('html_errors', 0);
        if (!$commandServer = stream_socket_server('unix://' . $sockFile, $errNo, $errStr)) {
            echo "Command handler start failed: {$errStr} ({$errNo}) \n";
        } else {
            swoole_event_add($commandServer, function ($commandServer) {
                $conn = stream_socket_accept($commandServer, 0);
                swoole_event_add($conn, function ($conn) {
                    $command = fread($conn, 128);
                    swoole_event_set($conn, null, function ($conn) use ($command) {
                        $server = $this->swoole;
                        switch ($command) {
                            case 'status':
                                $labels = array(
                                    'start_time' => 'Service started at',
                                    'connection_num' => 'Current connections',
                                    'request_count' => 'Total requests',
                                );
                                $stats = $server->stats();
                                $result = "Service is running. PID: {$server->master_pid}";
                                foreach ($labels as $k => $label) {
                                    $v = $stats[$k];
                                    $k == 'start_time' and $v = date('Y-n-j H:i:s (e)', $v);
                                    $result .= "\n{$label}: {$v}";
                                }
                                $result .= "\nWorkers: {$this->config['worker_num']}\n";
                                break;
                            case 'status-list':
                                $labels = array(
                                    'start_time' => 'Service started at',
                                    'connection_num' => 'Current connections',
                                    'request_count' => 'Total requests',
                                );

                                $result = '';
                                $stats = $server->stats();
                                $result .= "PID: {$server->master_pid} ; ";
                                foreach ($labels as $k => $label) {
                                    $v = $stats[$k];
                                    if ($k != 'start_time') {
                                        $result .= " {$label}: {$v} ; ";
                                    }
                                }
                                $result .= date("Y-m-d H:i:s", time()) . "\n";
                                break;
                            case 'connections':
                                $stats = $server->stats();
                                $result = $stats['connection_num'];
                                break;
                            case 'shutdown':
                            default:
                                $result = 'Bad command';
                        }
                        fwrite($conn, $result, strlen($result));
                        swoole_event_del($conn);
                    }, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
                });
            });
            echo "Command handler started. \n";
        }
    }

    /**
     * Choose a port in (9510, 9511) to serve
     * @param bool $swap Set to true swap port, otherwise remain previous port
     * @return integer Return previous port
     */
    protected function choosePort($swap = false)
    {
        //是否存在service-port.php
        $portSaved = is_file($portFile = $this->runDir . 'service-port.php');
        //没有service-port.php 获取$this->availablePorts 的第一个值
        $port = $previousPort = ($portSaved ? include($portFile) : reset($this->availablePorts));
        if ($swap) {
            //合并两个数组来创建一个新数组 第一个为key 第二个为值
            $availablePorts = array_combine($this->availablePorts, $this->availablePorts);
            unset($availablePorts[$port]);
            $port = reset($availablePorts);
            $portSaved = false;
        }
        $this->swoolePort = $port;
        $this->sockFile = $this->runDir . 'service-' . $port . '.sock';
        $portSaved or file_put_contents($portFile, sprintf('<?php return %d;', $port));
        return $previousPort;
    }


    /**
     * Get service info, including pid, manager pid and port
     *
     * @param string $instance Specify instance name (e.g. "current", "old").
     * If not specified, return combined info of all instances.
     * @return array|null An array containing service info
     */
    protected function getServiceInfo($instance = null)
    {
        $file = $this->runDir . 'service-info.php';
        $info = is_file($file) ? include($file) : array();
        return $instance ? fnGet($info, $instance) : $info;
    }

    /**
     * @param $key
     * @param null $data
     * @return $this
     */
    protected function updateServiceInfo($key, $data = null)
    {
        $info = $this->getServiceInfo();
        if ($key == 'shift') {
            if (isset($info['current'])) {
                $info['old'] = $info['current'];
                unset($info['current']);
            }
        } else {
            $info[$key] = $data;
        }
        $file = $this->runDir . 'service-info.php';
        file_put_contents($file, sprintf('<?php return %s;', var_export($info, true)));
        //重置字节码缓存的内容
        opcache_reset();
        //清除文件状态缓存
        clearstatcache();
        return $this;
    }

    /**
     * Service 启动完毕
     * @param swoole_server $server
     */
    public function onStart(swoole_server $server)
    {
        @cli_set_process_title('order_sysytem: master process/reactor threads; port=' . $this->swoolePort);
        $this->pid = $server->master_pid;   //主进程的PID，通过向主进程发送SIGTERM信号可安全关闭服务器
        $this->managerPid = $server->manager_pid;   //管理进程的PID，通过向管理进程发送SIGUSR1信号可实现柔性重启
        $this->startCommandHandler();
        $this->updateServiceInfo('current', array(
            'pid' => $this->pid,
            'manager_pid' => $this->managerPid,
            'port' => $this->swoolePort,
        ));
        echo "pid = {$this->pid}; port = {$this->swoolePort}. \n";
        echo "Service started. \n";
    }

    public function onManagerStart(swoole_server $server)
    {
        @cli_set_process_title('order_sysytem: manager process');
    }

    public function onWorkerStart(swoole_server $server, $workerId)
    {
        @cli_set_process_title('order_sysytem: worker process ' . $workerId);
    }

    /**
     * 接受到用户请求
     * @param swoole_server $server
     * @param $fd
     * @param $fromId
     * @param $data
     */
    public function onReceive(swoole_server $server, $fd, $fromId, $data)
    {
        $length = unpack('N', $data)[1];
        $data = unserialize(substr($data, -$length));

        $this->di->getService('request')->resolve($_REQUEST = $_GET = $_POST = fnGet($data, 'request'));
        $this->di->getService('cookies')->resolve($_COOKIE = fnGet($data, 'cookies'));
        $_SERVER = fnGet($data, 'server');

        try {
            $response = $this->LiteHandler();
        } catch (AjaxReturnException $e) {
            $response = $e->getResponse();
            LogHelper::write('AjaxReturnException Response'.http_build_query($response), LogHelper::ERROR);
        } catch (NotFoundException $e) {
            $response = $e->getResponse();
            LogHelper::write('NotFoundException Response'.http_build_query($response), LogHelper::ERROR);
        } catch (Exception $e) {
            $response = new Response($e->getMessage(), $e->getCode());
            LogHelper::write('Exception Response'.http_build_query($response), LogHelper::ERROR);
        }

        $result = serialize(array(
            'headers' => $response->getHeaders()->toArray(),
            'body' => $response->getContent(),
            'meta' => $this->debug ? $this->getDebugInfo($server) : array('service' => 1),
        ));
        $packageHead = pack('N', strlen($result));
        $server->send($fd, $packageHead . $result, $fromId);
    }

    /**
     * @param swoole_server $server
     * @return array
     */
    protected function getDebugInfo(swoole_server $server)
    {
        /** @var \Phalcon\Session\Adapter $session */
        //$session = DI::getDefault()->getShared('session');
        return array(
            'service' => 1,
            'port' => $this->swoolePort,
            'mem' => round(memory_get_usage() / 1024 / 1024, 2) . 'M',
            'worker-id' => $server->worker_id,
            'status' => json_encode($server->stats()),
            //'session-id' => json_encode($session->getId()),
            //'session-data' => json_encode($session),
        );
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    protected function LiteHandler()
    {
        /* @var $di Di */
        $dependencyInjector =  DI::getDefault();

        $app = $this->application;
        $eventsManager = $app->_eventsManager;
        if (is_object($eventsManager)) {
            if ($eventsManager->fire("application:boot", $app) === false) {
                return false;
            }
        }

        $router = $dependencyInjector->getShared("router");
        $router->handle();

        $moduleName = $router->getModuleName();
        if (!$moduleName) {
            $moduleName = $app->_defaultModule;
        }

        $moduleObject = null;

        if ($moduleName) {

            if (is_object($eventsManager)) {
                if ($eventsManager->fire("application:beforeStartModule", $app, $moduleName) === false) {
                    return false;
                }
            }

            $module = $app->getModule($moduleName);

            if (!is_array($module) && !is_object($module)) {
                throw new Exception("Invalid module definition");
            }

            if (is_array($module)) {
                if (!isset($module["className"])) {
                    $className = "Module";
                } else {
                    $className = $module["className"];
                }

                if (isset($module["path"])) {
                    $path = $module["path"];
                    if (!class_exists($className, false)) {
                        if (file_exists($path)) {
                            require $path;
                        } else {
                            throw new Exception("Module definition path '" . $path . "' doesn't exist");
                        }
                    }
                }

                $moduleObject = $dependencyInjector->get($className);
                $moduleObject->registerAutoloaders($dependencyInjector);
                $moduleObject->registerServices($dependencyInjector);

            } else {
                if ($module instanceof \Closure) {
                    $moduleObject = call_user_func_array($module, [$dependencyInjector]);
                } else {
                    throw new Exception("Invalid module definition");
                }
            }

            if (is_object($eventsManager)) {
                $eventsManager->fire("application:afterStartModule", $app, $moduleObject);
            }

        }

        $implicitView = false;
        if ($implicitView === true) {
            $view = $dependencyInjector->getShared("view");
        }

        $dispatcher = $dependencyInjector->getShared("dispatcher");
        $dispatcher->setModuleName($router->getModuleName());
        $dispatcher->setNamespaceName($router->getNamespaceName());
        $dispatcher->setControllerName($router->getControllerName());
        $dispatcher->setActionName($router->getActionName());
        $dispatcher->setParams($router->getParams());

        if ($implicitView === true) {
            $view->start();
        }

        if (is_object($eventsManager)) {
            if ($eventsManager->fire("application:beforeHandleRequest", $app, $dispatcher) === false) {
                return false;
            }
        }

        $controller = $dispatcher->dispatch();
        $possibleResponse = $dispatcher->getReturnedValue();
        if (is_bool($possibleResponse) && $possibleResponse == false) {
            $response = $dependencyInjector->getShared("response");
        } else {
            if (is_object($possibleResponse)) {
                $returnedResponse = $possibleResponse instanceof ResponseInterface;
            } else {
                $returnedResponse = false;
            }

            if (is_object($eventsManager)) {
                $eventsManager->fire("application:afterHandleRequest", $app, $controller);
            }

            if ($returnedResponse === false) {
                if ($implicitView === true) {
                    if (is_object($controller)) {

                        $renderStatus = true;

                        if (is_object($eventsManager)) {
                            $renderStatus = $eventsManager->fire("application:viewRender", $app, $view);
                        }

                        if ($renderStatus !== false) {

                            $view->render(
                                $dispatcher->getControllerName(),
                                $dispatcher->getActionName(),
                                $dispatcher->getParams()
                            );
                        }
                    }
                }
            }

            if ($implicitView === true) {
                $view->finish();
            }

            if ($returnedResponse === false) {
                $response = $dependencyInjector->getShared("response");
                if ($implicitView === true) {
                    $response->setContent($view->getContent());
                }

            } else {
                $response = $possibleResponse;
            }
        }

        if (is_object($eventsManager)) {
            $eventsManager->fire("application:beforeSendResponse", $app, $response);
        }
        return $response;
    }

    protected function setDiService()
    {
        $appconfig = DI::getDefault()->getShared('config');
        DI::reset();
        $this->di = new FactoryDefault();
        $this->di->setShared('config', function () use ($appconfig) {
            return $appconfig;
        });


        $this->di->setShared('view', function () use ($appconfig) {
            $view = new View();
            $view->registerEngines(array(
                '.phtml' => 'Phalcon\Mvc\View\Engine\Php',
            ));
            return $view;
        });


        $routerConfig = require_once APP_PATH . "/app/router.php";
        $this->di->set('router', function () use ($routerConfig, $appconfig) {
            $router = new Router();
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
        $this->di->set('dispatcher', function () use ($appconfig) {
            $eventsManager = new Manager();
            $eventsManager->attach("dispatch:beforeException",
                function ($event, $dispatcher, $exception) use ($appconfig) {
                    /* @var $dispatcher \Phalcon\Mvc\Dispatcher */
                    //页面404
                    if ($exception instanceof Exception) {
                        //log
                        if (fnGet($appconfig->get('log'), 'NOT_FOUND_LOG')) {
                            LogHelper::write("404 Not Found : [ url : " . $_SERVER['REQUEST_URI'] .
                                "] [ dispatcher: " . $dispatcher->getModuleName() . " / " . $dispatcher->getControllerName() . " / " . $dispatcher->getActionName() . " ]");
                        }
                        throw new NotFoundException(json_encode(array(
                            'status' => false,
                            'message' => '404 Not Found'
                        )), 404);
                    }
                });
            $dispatcher = new Dispatcher();
            $dispatcher->setEventsManager($eventsManager);
            return $dispatcher;
        });

        $loaderArray = include APP_PATH . "/vendor/composer/autoload_classmap.php";
        $loader = new Loader;
        $loader->registerClasses($loaderArray);
        $loader->register();
        $this->di->setShared('loader', $loader);

        class_alias('Common\Library\ConfigHelper', 'Config');
        class_alias('Common\Library\LogHelper', 'Log');
        class_alias('Common\Library\EventData', 'EventData');
        class_alias('Common\Library\HttpClient', 'HttpClient');
        class_alias('Common\Library\Session', 'Session');
        class_alias('Common\Models\User', 'User');
        class_alias('Common\Models\Client', 'Client');
        class_alias('Pay\Models\Order', 'Order');
        class_alias('Pay\Models\OrderData', 'OrderData');

        $this->di->set('db', function () use ($appconfig) {
            $dbConfig = $appconfig->database->toArray();
            $adapter = $dbConfig['adapter'];
            unset($dbConfig['adapter']);
            $class = 'Phalcon\\Db\\Adapter\\Pdo\\' . $adapter;
            return new $class($dbConfig);
        });
    }

}
