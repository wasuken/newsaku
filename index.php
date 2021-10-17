<?php

require_once "./vendor/autoload.php";

require_once __DIR__.'/src/io/rss.php';
require_once __DIR__.'/src/line/msg.php';
require_once __DIR__.'/src/util/util.php';
require_once __DIR__.'/src/util/log.php';

$dotenv = Dotenv\Dotenv::createImmutable('./');
$dotenv->load();

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\PostbackEvent;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->post('/callback', function (Request $req, Response $response, $args) {
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
                $res = replyCategoriesMessage($bot, $rToken);
                break;
            }
        }else if($event instanceof PostbackEvent){
            $pbdata = [];
            parse_str($event->getPostbackData(), $pbdata);
            switch($pbdata['reply']){
            case 'category':
                $rss = loadRssUseCategory(
                    'hotentry',
                    $pbdata['category'],
                    $_ENV['RSS_CONFIG_PATH']
                );
                // log_info(var_export($rss, true));
                $resp = replyFlexMessageFromRss($bot, $rToken, array_slice($rss, 0, 12));
                // log_info(var_export($resp, true));
                break;
            default:
                log_info('unknown reply');
            }
        }
    }
    return $response;
});

$app->run();
