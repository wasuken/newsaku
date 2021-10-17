<?php

require_once "./vendor/autoload.php";
require_once __DIR__.'/../util/log.php';
require_once __DIR__.'/../util/util.php';

// saveRss -> saveTodayRss
// RSSを取得し、json fileとして保存
// @param string $url
// @return array
function saveTodayRss($url)
{
    $rss_ary = [];
    log_info($url);
    $rss = simplexml_load_file($url);
    foreach ($rss->item as $item) {
        $rss_ary[] = [
            'title' => (string)$item->title,
            'link' =>  (string)$item->link,
            'description ' =>  (string)$item->description,
            'pubDate' => (string)$item->children('http://purl.org/dc/elements/1.1/')->date,
        ];
    }
    $path = todayPath($url);
    $json_str = json_encode($rss_ary);
    file_put_contents($path, $json_str);
    return $rss_ary;
}
// $urlを元にRSSを取得。saveRssにより既にDLしている場合はそちらを優先。
// @param string $url
// @return array
function loadTodayRss($url)
{
    $path = todayPath($url);
    if(file_exists($path) === true){
        return json_decode(file_get_contents($path), true);
    }
    return saveTodayRss($url);
}
// loadTodayRssUseCategory -> loadRssUseCategory
// type, categoryよりURLを取得する。
// @param string $type
// @param string $category
// @param string $path 設定file(json)のpath
// @return string|bool
function loadRssUseCategory($type, $category, $path)
{
    $rsses = json_decode(file_get_contents($path), true);
    foreach($rsses[$type] as $item){
        if($item['title'] === $category){
            return loadTodayRss($item['url']);
        }
    }
    return false;
}
// $pathのjsonから$type下のcategoriesのtitle listを返却する。
// @param string $path
// @param string $type
function listRSSCategories($path, $type = 'hotentry')
{
    // $rsses = json_decode(file_get_contents('./rsses.json'), true);
    $rsses = json_decode(file_get_contents($path), true);
    $rss = $rsses[$type];
    return array_map(function($x){
        return $x['title'];
    }, $rss);
}
