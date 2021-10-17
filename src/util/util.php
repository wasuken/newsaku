<?php

require_once "./vendor/autoload.php";
require_once __DIR__.'/../util/log.php';

function todayPath($url)
{
    $rss_path = $_ENV['RSS_PATH'];
    $url_hash = sha1($url);
    $df = date('Ymd');
    return "./${rss_path}/${url_hash}_${df}.json";
}
