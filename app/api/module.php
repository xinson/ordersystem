<?php
namespace Api;

use Phalcon\Mvc\Dispatcher;
use Phalcon\DiInterface;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;

class Module
{
    public function registerAutoloaders(DiInterface $di)
    {
        $di->set('loader', $di->get('loader'));
    }

    public function registerServices(DiInterface $di)
    {
        /* @var $dispatcher \Phalcon\Mvc\Dispatcher */
        $dispatcher = $di->get('dispatcher');
        $dispatcher->setDefaultNamespace('Api\Controllers');
        $dispatcher->setActionSuffix('');
        $di->set('dispatcher', $dispatcher);
    }

}
