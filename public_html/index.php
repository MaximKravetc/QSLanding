<?php

define('ROOT_PATH', dirname(__DIR__));

$app  = new Yaf_Application(ROOT_PATH . '/application/config.ini');

if ($app->environ() != 'product') {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
}

ini_set('include_path', ROOT_PATH . '/application/library');

$app->bootstrap()->run();
