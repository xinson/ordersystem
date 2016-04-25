<?php
namespace Tests;

use Common\Library\ConfigHelper;
use Exception;
use Phalcon\DI;
use Phalcon\DI\FactoryDefault;
use Phalcon\Config;
use Phalcon\Test\UnitTestCase as PhalconTestCase;
use Phalcon\Mvc\Url;
use Phalcon\Escaper;

abstract class UnitTestCase extends PhalconTestCase
{
    /**
     * @var \Voice\Cache
     */
    protected $_cache;

    /**
     * @var \Phalcon\Config
     */
    protected $_config;

    /**
     * @var bool
     */
    private $_loaded = false;

    /**
     * Check if the test case is setup properly
     *
     * @throws \PHPUnit_Framework_IncompleteTestError;
     */
    public function __destruct()
    {
        if (!$this->_loaded) {
            throw new \PHPUnit_Framework_IncompleteTestError('Please run parent::setUp().');
        }
    }

    public function setUp()
    {
        $this->checkExtension('phalcon');

        if (!$this->di) {

            DI::reset();
            $di = new FactoryDefault();

            $config = include APP_PATH . '/tests/Config.php';
            $di->setShared('config',function() use ($config){
                return $config;
            });

            $di->setShared('db', function() use ($config) {
                $dbConfig = $config->database->toArray();
                $adapter = $dbConfig['adapter'];
                unset($dbConfig['adapter']);
                $class = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
                return new $class($dbConfig);
            });

            $di->set(
                'url',
                function () {
                    $url = new Url();
                    $url->setBaseUri('/');

                    return $url;
                }
            );

            $di->set(
                'escaper',
                function () {
                    return new Escaper();
                }
            );
            $this->di = $di;
            $this->_loaded = true;
        }

        //制作初始化
        if (method_exists($this,'init')) {
            $this->init();
        }

    }

    /**
     * @return mixed | \Phalcon\Db\Adapter\Pdo\Mysql
     */
    public function getDB()
    {
        try{
            $db = $this->di->getShared('db');
        } catch(Exception $e) {
            exit($e->getMessage());
        }
        return $db;
    }

    public function getConfigByKey($key,$default = null)
    {
        return ConfigHelper::get($key,$default);
    }
}
