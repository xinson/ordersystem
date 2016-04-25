<?php

/* 注入DB */
$di->set('db', function () use ($config) {
    $dbConfig = $config->database->toArray();
    $adapter = $dbConfig['adapter'];
    unset($dbConfig['adapter']);
    $class = "Phalcon\\Db\\Adapter\\Pdo\\" . $adapter;
    return new $class($dbConfig);
});
