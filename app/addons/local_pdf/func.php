<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

Tygh::$app['class_loader']->add('', __DIR__);
