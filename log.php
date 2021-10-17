<?php

require_once "vendor/autoload.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

function log_info($text)
{
    $log = new Logger('line');
    $log->pushHandler(new StreamHandler('./logs/app.log', Logger::INFO));
    $log->info($text);
}
