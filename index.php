<?php

require_once "./vendor/autoload.php";

require_once './log.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\PostbackEvent;

use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;

use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;

use LINE\LINEBot\Constant\Flex\ComponentIconSize;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
use LINE\LINEBot\Constant\Flex\ComponentButtonHeight;
use LINE\LINEBot\Constant\Flex\ComponentSpaceSize;
use LINE\LINEBot\Constant\Flex\ComponentGravity;
use LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder;
use LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\Constant\Flex\ComponentLayout;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

function todayPath($url)
{
    $url_hash = sha1($url);
    $df = date('Ymd');
    return "./rss/${url_hash}_${df}.json";
}

function saveRss($url)
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
function loadTodayRss($url)
{
    $path = todayPath($url);
    if(file_exists($path) === true){
        return json_decode(file_get_contents($path), true);
    }
    return saveRss($url);
}
function loadTodayRssUseCategory($type, $category)
{
    $rsses = json_decode(file_get_contents('./rsses.json'), true);
    foreach($rsses[$type] as $item){
        if($item['title'] === $category){
            return loadTodayRss($item['url']);
        }
    }
    return false;
}
function rssToFlexMessage($bot, $rToken, $rss)
{
    $bubbles = [];
    foreach($rss as $r){
        $title = $r['title'];
        if(mb_strlen($title) > 40) $title = mb_substr($title, 0, 39);
        $bubbles[] = BubbleContainerBuilder::builder()
                   ->setBody(
                       new BoxComponentBuilder(ComponentLayout::VERTICAL,[
                           new TextComponentBuilder($title),
                           (new ButtonComponentBuilder($r['link']))
                           ->setAction(new UriTemplateActionBuilder('Go', $r['link'])),
                       ])
                   );
    }
    return $bot->replyMessage(
        $rToken,
        FlexMessageBuilder::builder()
        ->setAltText('articles')
        ->setContents(
            CarouselContainerBuilder::builder()
            ->setContents($bubbles)
        )
    );
}

function listRSSCategories($type = 'hotentry')
{
    $rsses = json_decode(file_get_contents('./rsses.json'), true);
    $rss = $rsses[$type];
    return array_map(function($x){
        return $x['title'];
    }, $rss);
}
function sendCategoriesMessage($bot, $rToken)
{
    $titles = listRssCategories();

    $qreplyButtons = [];
    foreach($titles as $t){
        $qreplyButtons[] = (
            new QuickReplyButtonBuilder(new PostbackTemplateActionBuilder($t, "reply=category&category=${t}", $t))
        );
    }
    $qreply = new QuickReplyMessageBuilder($qreplyButtons);
    return $bot->replyMessage(
        $rToken,
        FlexMessageBuilder::builder()
        ->setContents(
            BubbleContainerBuilder::builder()
            ->setBody(new BoxComponentBuilder(ComponentLayout::VERTICAL, [new TextComponentBuilder('カテゴリーを選択してください。')]))
        )
        ->setAltText('articles')
        ->setQuickReply($qreply)
    );
}

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello, ");
    return $response;
});

$app->post('/callback', function (Request $req, Response $response, $args) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/');
    $dotenv->load();

    $secret = $_ENV['LINE_SECRET'];
    $token = $_ENV['LINE_TOKEN'];

    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($token);
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $secret]);
    $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
    if (empty($signature)) {
        return $res->withStatus(400, 'Bad Request');
    }

    try {
        $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
    } catch (InvalidSignatureException $e) {
        return $res->withStatus(400, 'Invalid signature');
    } catch (InvalidEventRequestException $e) {
        return $res->withStatus(400, "Invalid event request");
    }

    foreach ($events as $event) {
        // log_info(var_export($event, true));
        $rToken = $event->getReplyToken();
        if ($event instanceof MessageEvent) {
            $text = $event->getText();
            switch($text){
            case 'カテゴリ選択':
                $res = sendCategoriesMessage($bot, $rToken);
                break;
            }
        }else if($event instanceof PostbackEvent){
            $pbdata = [];
            parse_str($event->getPostbackData(), $pbdata);
            switch($pbdata['reply']){
            case 'category':
                $rss = loadTodayRssUseCategory('hotentry', $pbdata['category']);
                //log_info(var_export($rss, true));
                log_info(var_export(rssToFlexMessage($bot, $rToken, array_slice($rss, 0, 12)), true));
                //rssToFlexMessage($bot, $rToken, $rss);
                break;
            default:
                log_info('unknown reply');
            }
        }
    }
    return $response;
});

$app->run();
